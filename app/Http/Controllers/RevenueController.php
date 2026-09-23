<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\UserSubscription;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RevenueController extends Controller
{
    // A payment-less subscription (e.g. still on its free trial) has no
    // row in `payments` at all. Surface it under this synthetic status
    // instead of silently dropping it from an inner join.
    private const NO_PAYMENT_STATUS = 'no_payment';

    // Subscription lifecycle statuses that represent money actually being
    // recognised as recurring revenue right now. Trial/incomplete rows carry
    // no collected revenue yet, so they're excluded from MRR/ARR.
    private const RECURRING_STATUSES = ['active'];

    private const DATE_PRESETS = [
        'today', 'yesterday', 'last_7_days', 'last_30_days',
        'this_month', 'last_month', 'this_year', 'custom',
    ];

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

        $rangeValidated = $request->validate([
            'period' => ['nullable', 'string', Rule::in(self::DATE_PRESETS)],
            'range_start' => ['nullable', 'date_format:Y-m-d'],
            'range_end' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:range_start'],
        ]);

        [$rangeStart, $rangeEnd, $period] = $this->resolveDateRange($rangeValidated);

        $data['summary'] = $this->summaryMetrics($rangeStart, $rangeEnd);
        $data['period'] = $period;
        $data['rangeStart'] = $rangeStart->format('Y-m-d');
        $data['rangeEnd'] = $rangeEnd->format('Y-m-d');

        return view('revenue.index', $data);
    }

    /**
     * JSON revenue-trend data for the chart, grouped by day/month/year and
     * scoped to the given date range. Zero-revenue buckets are included so
     * the chart never silently skips a day/month/year.
     */
    public function trend(Request $request)
    {
        $validated = $request->validate([
            'granularity' => ['required', 'string', Rule::in(['daily', 'monthly', 'yearly'])],
            'period' => ['nullable', 'string', Rule::in(self::DATE_PRESETS)],
            'range_start' => ['nullable', 'date_format:Y-m-d'],
            'range_end' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:range_start'],
        ]);

        [$start, $end] = $this->resolveDateRange($validated);

        return response()->json([
            'granularity' => $validated['granularity'],
            'range_start' => $start->format('Y-m-d'),
            'range_end' => $end->format('Y-m-d'),
            'points' => $this->trendData($validated['granularity'], $start, $end),
        ]);
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

        $columns = [
            'S.No.', 'Subscription ID', 'Username', 'Mobile', 'Plan Name', 'Billing Cycle',
            'Price', 'Currency', 'Transaction ID', 'Payment Status', 'Subscription Status',
            'Payment Date', 'Start Date', 'End Date',
        ];

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
                        $transaction->plan_name ?: '—',
                        \Illuminate\Support\Str::headline($transaction->billing_cycle ?: '—'),
                        number_format((float) $transaction->price, 2),
                        strtoupper($transaction->currency ?: 'USD'),
                        $transactionId ?: '—',
                        $statusLabel,
                        \Illuminate\Support\Str::headline($transaction->subscription_status ?: '—'),
                        optional($transaction->transaction_date)->format('d M Y, h:i A') ?? '—',
                        optional($transaction->started_at)->format('d M Y') ?? '—',
                        optional($transaction->renews_at)->format('d M Y') ?? '—',
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
            })
            ->leftJoin('subscription_plans', 'subscription_plans.id', '=', 'user_subscriptions.plan_id');

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

        $subscriptionStatuses = ['incomplete', 'trial', 'active', 'expired', 'cancelled'];
        $billingCycles = ['trial', 'monthly', 'yearly'];

        $validated = $request->validate([
            'payment_status' => ['nullable', 'string', Rule::in($paymentStatuses->all())],
            'search' => ['nullable', 'string', 'max:255'],
            'subscription_status' => ['nullable', 'string', Rule::in($subscriptionStatuses)],
            'billing_cycle' => ['nullable', 'string', Rule::in($billingCycles)],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);

        $query = $baseQuery()
            ->select([
                'user_subscriptions.id',
                'user_subscriptions.subscription_id',
                'user_subscriptions.price',
                'user_subscriptions.billing_cycle',
                'user_subscriptions.status as subscription_status',
                'user_subscriptions.started_at',
                'user_subscriptions.renews_at',
                'users.name as user_name',
                'users.phone as mobile',
                'subscription_plans.name as plan_name',
                'payments.id as payment_id',
                'payments.stripe_payment_intent_id',
                'payments.stripe_invoice_id',
                'payments.currency',
            ])
            ->selectRaw("{$paymentStatus} as payment_status", [self::NO_PAYMENT_STATUS])
            ->selectRaw("{$transactionDate} as transaction_date")
            ->withCasts([
                'transaction_date' => 'datetime',
                'started_at' => 'datetime',
                'renews_at' => 'datetime',
            ])
            ->when(
                $validated['payment_status'] ?? null,
                fn ($query, $status) => $status === self::NO_PAYMENT_STATUS
                    ? $query->whereNull('payments.status')
                    : $query->where('payments.status', $status),
            )
            ->when(
                $validated['search'] ?? null,
                function ($query, $search) {
                    $term = '%'.addcslashes($search, '%_\\').'%';

                    $query->where(function ($query) use ($term) {
                        $query->where('users.name', 'like', $term)
                            ->orWhere('users.phone', 'like', $term)
                            ->orWhere('subscription_plans.name', 'like', $term);
                    });
                },
            )
            ->when(
                $validated['subscription_status'] ?? null,
                fn ($query, $status) => $query->where('user_subscriptions.status', $status),
            )
            ->when(
                $validated['billing_cycle'] ?? null,
                fn ($query, $cycle) => $query->where('user_subscriptions.billing_cycle', $cycle),
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
     * Turns a preset name (or an explicit custom range) into a concrete
     * [start, end] CarbonImmutable pair, inclusive of both boundaries, using
     * the app's configured timezone rather than a hardcoded one.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: string}
     */
    private function resolveDateRange(array $validated): array
    {
        $tz = config('app.timezone');
        $now = CarbonImmutable::now($tz);
        $period = $validated['period'] ?? 'last_30_days';

        [$start, $end] = match ($period) {
            'today' => [$now->startOfDay(), $now->endOfDay()],
            'yesterday' => [$now->subDay()->startOfDay(), $now->subDay()->endOfDay()],
            'last_7_days' => [$now->subDays(6)->startOfDay(), $now->endOfDay()],
            'this_month' => [$now->startOfMonth(), $now->endOfMonth()],
            'last_month' => [$now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth()],
            'this_year' => [$now->startOfYear(), $now->endOfYear()],
            'custom' => [
                isset($validated['range_start'])
                    ? CarbonImmutable::createFromFormat('Y-m-d', $validated['range_start'], $tz)->startOfDay()
                    : $now->subDays(29)->startOfDay(),
                isset($validated['range_end'])
                    ? CarbonImmutable::createFromFormat('Y-m-d', $validated['range_end'], $tz)->endOfDay()
                    : $now->endOfDay(),
            ],
            default => [$now->subDays(29)->startOfDay(), $now->endOfDay()],
        };

        if ($end->lt($start)) {
            $end = $start->endOfDay();
        }

        return [$start, $end, $period];
    }

    /**
     * Dynamic summary cards. Every figure comes from `payments` (collected
     * money, minor units -> divided by 100) or `user_subscriptions` (current
     * subscription lifecycle state) — nothing here is estimated.
     */
    private function summaryMetrics(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $paidWindow = fn () => Payment::query()
            ->where('status', 'succeeded')
            ->whereBetween('paid_at', [$start, $end]);

        $totalRevenueMinor = (int) $paidWindow()->sum('amount');

        $paymentCounts = Payment::query()
            ->whereBetween(DB::raw('COALESCE(paid_at, created_at)'), [$start, $end])
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        // MRR: only currently-active (paid, non-trial) subscriptions count as
        // recurring revenue. Yearly plans are normalized to a monthly figure
        // so they're comparable with monthly ones; trial/incomplete/expired/
        // cancelled rows contribute $0 since no recurring money is being
        // collected for them right now.
        $activeSubscriptions = UserSubscription::query()
            ->whereIn('status', self::RECURRING_STATUSES)
            ->select(['price', 'billing_cycle'])
            ->get();

        $mrr = $activeSubscriptions->sum(function (UserSubscription $subscription) {
            $price = (float) $subscription->price;

            return $subscription->billing_cycle === 'yearly' ? $price / 12 : $price;
        });

        $arr = $mrr * 12;

        $activeCount = $activeSubscriptions->count();

        return [
            'total_revenue' => $totalRevenueMinor / 100,
            'mrr' => round($mrr, 2),
            'arr' => round($arr, 2),
            'active_subscriptions' => $activeCount,
            'payments' => [
                'succeeded' => (int) ($paymentCounts['succeeded'] ?? 0),
                'pending' => (int) ($paymentCounts['pending'] ?? 0),
                'failed' => (int) ($paymentCounts['failed'] ?? 0),
                'refunded' => (int) ($paymentCounts['refunded'] ?? 0),
            ],
        ];
    }

    /**
     * Revenue-trend series for the chart: SUM(amount) of succeeded payments
     * grouped by day/month/year, with zero-revenue buckets filled in so the
     * chart never has a silently missing point.
     *
     * @return array<int, array{label: string, value: float}>
     */
    private function trendData(string $granularity, CarbonImmutable $start, CarbonImmutable $end): array
    {
        [$sqlFormat, $labelFormat, $step] = match ($granularity) {
            'daily' => ['%Y-%m-%d', 'd M', 'addDay'],
            'monthly' => ['%Y-%m', 'M Y', 'addMonthNoOverflow'],
            'yearly' => ['%Y', 'Y', 'addYearNoOverflow'],
        };

        $rows = Payment::query()
            ->where('status', 'succeeded')
            ->whereBetween('paid_at', [$start, $end])
            ->selectRaw('DATE_FORMAT(paid_at, ?) as bucket, SUM(amount) as total', [$sqlFormat])
            ->groupBy('bucket')
            ->pluck('total', 'bucket');

        $points = [];
        $cursor = match ($granularity) {
            'daily' => $start->startOfDay(),
            'monthly' => $start->startOfMonth(),
            'yearly' => $start->startOfYear(),
        };

        $bucketFormat = match ($granularity) {
            'daily' => 'Y-m-d',
            'monthly' => 'Y-m',
            'yearly' => 'Y',
        };

        while ($cursor->lte($end)) {
            $bucket = $cursor->format($bucketFormat);

            $points[] = [
                'label' => $cursor->format($labelFormat),
                'value' => round((int) ($rows[$bucket] ?? 0) / 100, 2),
            ];

            $cursor = $cursor->{$step}();
        }

        return $points;
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
