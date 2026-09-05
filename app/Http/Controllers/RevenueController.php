<?php

namespace App\Http\Controllers;

use App\Models\UserSubscription;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RevenueController extends Controller
{
    // A payment-less subscription (e.g. still on its free trial) has no
    // row in `payments` at all. Surface it under this synthetic status
    // instead of silently dropping it from an inner join.
    private const NO_PAYMENT_STATUS = 'no_payment';

    public function index(Request $request)
    {
        [$filteredQuery, $paymentStatuses, $validated] = $this->filteredTransactions($request);

        $transactions = $filteredQuery
            ->paginate(15)
            ->withQueryString();

        $data = [
            'transactions' => $transactions,
            'paymentStatuses' => $paymentStatuses,
            'filters' => $validated,
        ];

        // The filter form and pagination links submit here via fetch() with
        // this header set, so only the table/pagination fragment is needed —
        // the surrounding page (header, chart, filter inputs) stays put.
        if ($request->ajax()) {
            return view('revenue.partials.transactions-table', $data);
        }

        return view('revenue.index', $data);
    }

    /**
     * Export the currently filtered transactions as a CSV download. Shares
     * the exact same filtering logic as index() (minus pagination) so the
     * export always matches what's on screen.
     */
    public function export(Request $request)
    {
        [$filteredQuery] = $this->filteredTransactions($request);

        $filename = 'revenue-transactions-'.now()->format('Y-m-d-His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $columns = ['S.No.', 'Subscription ID', 'Username', 'Mobile', 'Price', 'Currency', 'Transaction ID', 'Payment Status', 'Date'];

        return response()->streamDownload(function () use ($filteredQuery, $columns) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $columns);

            $rowNumber = 0;
            $filteredQuery->chunk(500, function ($transactions) use ($handle, &$rowNumber) {
                foreach ($transactions as $transaction) {
                    $rowNumber++;
                    $transactionId = $transaction->stripe_payment_intent_id ?: $transaction->stripe_invoice_id;
                    $statusLabel = match ($transaction->payment_status) {
                        'succeeded' => 'Paid / Successful',
                        'no_payment' => 'No Payment',
                        default => \Illuminate\Support\Str::headline($transaction->payment_status),
                    };

                    fputcsv($handle, [
                        $rowNumber,
                        $transaction->subscription_id,
                        $transaction->user_name ?: '—',
                        $transaction->mobile ?: '—',
                        number_format((float) $transaction->price, 2),
                        strtoupper($transaction->currency ?: 'USD'),
                        $transactionId ?: '—',
                        $statusLabel,
                        optional($transaction->transaction_date)->format('d M Y, h:i A') ?? '—',
                    ]);
                }
            });

            fclose($handle);
        }, $filename, $headers);
    }

    /**
     * Builds the shared, filtered UserSubscription query plus the payment
     * status options and validated filter values — used by both index()
     * (paginated view) and export() (full CSV dump) so filtering logic
     * never drifts between the two.
     *
     * @return array{0: \Illuminate\Database\Eloquent\Builder, 1: \Illuminate\Support\Collection, 2: array}
     */
    private function filteredTransactions(Request $request): array
    {
        // A subscription with no payment row (e.g. an active trial) must
        // still be listed, so the query below left-joins payments/subscriptions
        // rather than requiring a match.
        $transactionDate = 'COALESCE(payments.paid_at, payments.created_at, user_subscriptions.created_at)';
        $paymentStatus = 'COALESCE(payments.status, ?)';

        $baseQuery = fn () => UserSubscription::query()
            ->join('users', 'users.id', '=', 'user_subscriptions.user_id')
            ->leftJoin('subscriptions', function ($join) {
                $join->on('subscriptions.subscription_id', '=', 'user_subscriptions.subscription_id')
                    ->whereNull('subscriptions.deleted_at');
            })
            ->leftJoin('payments', function ($join) {
                $join->on('payments.subscription_id', '=', 'subscriptions.id')
                    ->whereNull('payments.deleted_at');
            });

        // Canonical statuses from the `payments.status` enum (see the
        // create_payments_table migration) are always offered, even before
        // any transaction with that status exists yet. Any status actually
        // present in the user_subscriptions-driven data set — including the
        // synthetic "no payment" one — is appended on top, so a value added
        // to the enum later (or found unexpectedly) still shows up without
        // a code change.
        $canonicalStatuses = ['pending', 'succeeded', 'failed', 'refunded'];

        $paymentStatuses = $baseQuery()
            ->selectRaw("{$paymentStatus} as status", [self::NO_PAYMENT_STATUS])
            ->distinct()
            ->pluck('status')
            ->filter()
            ->merge($canonicalStatuses)
            ->unique()
            ->sort()
            ->values();

        $validated = $request->validate([
            'payment_status' => ['nullable', 'string', Rule::in($paymentStatuses->all())],
            'username' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);

        $query = $baseQuery()
            ->select([
                'user_subscriptions.id',
                'user_subscriptions.subscription_id',
                'user_subscriptions.price',
                'users.name as user_name',
                'users.phone as mobile',
                'payments.id as payment_id',
                'payments.stripe_payment_intent_id',
                'payments.stripe_invoice_id',
                'payments.currency',
            ])
            ->selectRaw("{$paymentStatus} as payment_status", [self::NO_PAYMENT_STATUS])
            ->selectRaw("{$transactionDate} as transaction_date")
            ->withCasts(['transaction_date' => 'datetime'])
            ->when(
                $validated['payment_status'] ?? null,
                fn ($query, $status) => $status === self::NO_PAYMENT_STATUS
                    ? $query->whereNull('payments.status')
                    : $query->where('payments.status', $status),
            )
            ->when(
                $validated['username'] ?? null,
                fn ($query, $username) => $query->where('users.name', 'like', '%'.addcslashes($username, '%_\\').'%'),
            )
            ->when(
                $validated['start_date'] ?? null,
                fn ($query, $date) => $query->whereRaw(
                    "{$transactionDate} >= ?",
                    [CarbonImmutable::createFromFormat('Y-m-d', $date)->startOfDay()],
                ),
            )
            ->when(
                $validated['end_date'] ?? null,
                fn ($query, $date) => $query->whereRaw(
                    "{$transactionDate} < ?",
                    [CarbonImmutable::createFromFormat('Y-m-d', $date)->addDay()->startOfDay()],
                ),
            )
            ->orderByRaw("{$transactionDate} DESC")
            ->orderByDesc('payments.id')
            // Tiebreaker so offset-based pagination/chunking (export) never
            // skips or repeats a row when the two orderings above tie.
            ->orderByDesc('user_subscriptions.id');

        return [$query, $paymentStatuses, $validated];
    }

    /**
     * Username suggestions for the live-search dropdown on the Username
     * filter — scoped to users who actually appear in user_subscriptions,
     * so every suggestion is guaranteed to return at least one transaction.
     */
    public function usernames(Request $request)
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $usernames = UserSubscription::query()
            ->join('users', 'users.id', '=', 'user_subscriptions.user_id')
            ->when(
                $validated['q'] ?? null,
                fn ($query, $q) => $query->where('users.name', 'like', '%'.addcslashes($q, '%_\\').'%'),
            )
            ->whereNotNull('users.name')
            ->distinct()
            ->orderBy('users.name')
            ->limit(10)
            ->pluck('users.name');

        return response()->json(['usernames' => $usernames]);
    }
}
