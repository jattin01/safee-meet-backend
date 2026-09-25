<?php

namespace App\Services;

use App\Models\Incident;
use App\Models\Meeting;
use App\Models\Payment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use App\Models\UserVerification;
use Carbon\CarbonImmutable;

class AdminDashboardService
{
    /**
     * Data shared by the admin and super-admin dashboard views.
     *
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return [
            'totalUsers' => User::query()->count(),
            'activeMeetings' => Meeting::query()
                ->whereIn('status', ['scheduled', 'pending_approval', 'active', 'live'])
                ->count(),
            'verificationCount' => UserVerification::query()->count(),
            'criminalVerificationCount' => User::query()
                ->whereIn('verification_level', ['level2', 'professional'])
                ->count(),
            'openSosCount' => Incident::query()->sos()->open()->count(),
            'totalSubscribers' => UserSubscription::query()->count(),
            'meetingCount' => Meeting::query()->count(),
            'incidentCount' => Incident::query()->count(),
            'meetings' => Meeting::query()
                ->with(['host', 'guest'])
                ->latest('created_at')
                ->limit(10)
                ->get(),
            'recentUsers' => User::query()
                ->with(['plan', 'userVerification'])
                ->latest('created_at')
                ->limit(5)
                ->get(),
            'recentTransactions' => $this->recentTransactions(),
            'engagementTrend' => $this->engagementTrend(),
            'planSubscriberCounts' => $this->planSubscriberCounts(),
            'topHosts' => User::query()
                ->withCount('meetings')
                ->whereHas('meetings')
                ->orderByDesc('meetings_count')
                ->orderByDesc('rating')
                ->limit(3)
                ->get(),
        ];
    }

    /**
     * The 5 most recent transactions, built the same way as the Revenue &
     * Reports page (see RevenueController::filteredTransactions) so the
     * figures shown on the dashboard always match that page.
     */
    private function recentTransactions(): \Illuminate\Support\Collection
    {
        $transactionDate = 'COALESCE(payments.paid_at, payments.created_at, user_subscriptions.created_at)';
        $paymentStatus = 'COALESCE(payments.status, ?)';

        return UserSubscription::query()
            ->join('users', 'users.id', '=', 'user_subscriptions.user_id')
            ->leftJoin('subscriptions', function ($join) {
                $join->on('subscriptions.subscription_id', '=', 'user_subscriptions.subscription_id')
                    ->whereNull('subscriptions.deleted_at');
            })
            ->leftJoin('payments', function ($join) {
                $join->on('payments.subscription_id', '=', 'subscriptions.id')
                    ->whereNull('payments.deleted_at');
            })
            ->leftJoin('subscription_plans', 'subscription_plans.id', '=', 'user_subscriptions.plan_id')
            ->select([
                'user_subscriptions.price',
                'user_subscriptions.billing_cycle',
                'user_subscriptions.status as subscription_status',
                'users.name as user_name',
                'users.id as user_id',
                'subscription_plans.name as plan_name',
                'user_subscriptions.stripe_customer_id',
                'user_subscriptions.stripe_subscription_id',
                'payments.stripe_payment_intent_id',
                'payments.stripe_invoice_id',
                'payments.currency',
            ])
            ->selectRaw("{$paymentStatus} as payment_status", ['no_payment'])
            ->selectRaw("{$transactionDate} as transaction_date")
            ->withCasts(['transaction_date' => 'datetime'])
            ->orderByRaw("{$transactionDate} DESC")
            ->orderByDesc('payments.id')
            ->orderByDesc('user_subscriptions.id')
            ->limit(5)
            ->get();
    }

    /**
     * Total subscribers per active plan, for the "Total Subscribers by Plan"
     * card: name, subscriber count, share of all subscriptions, revenue
     * earned from succeeded payments on that plan, and a color.
     *
     * @return array<int, array{name: string, count: int, value: int, revenue: float, color: string}>
     */
    private function planSubscriberCounts(): array
    {
        $palette = ['#0ab39c', '#299cdb', '#f7b84b', '#f06548', '#7367f0', '#20c997'];

        $countsByPlan = UserSubscription::query()
            ->selectRaw('plan_id, COUNT(*) as total')
            ->groupBy('plan_id')
            ->pluck('total', 'plan_id');

        $totalSubscriptions = $countsByPlan->sum();

        $revenueByPlan = Payment::query()
            ->join('subscriptions', 'subscriptions.id', '=', 'payments.subscription_id')
            ->join('user_subscriptions', 'user_subscriptions.subscription_id', '=', 'subscriptions.subscription_id')
            ->where('payments.status', 'succeeded')
            ->selectRaw('user_subscriptions.plan_id, SUM(payments.amount) as total')
            ->groupBy('user_subscriptions.plan_id')
            ->pluck('total', 'plan_id');

        return SubscriptionPlan::query()
            ->active()
            ->orderBy('sort_order')
            ->get()
            ->map(function (SubscriptionPlan $plan, int $index) use ($countsByPlan, $totalSubscriptions, $revenueByPlan, $palette) {
                $count = (int) ($countsByPlan[$plan->id] ?? 0);

                return [
                    'name' => $plan->name,
                    'count' => $count,
                    'value' => $totalSubscriptions > 0 ? (int) round(($count / $totalSubscriptions) * 100) : 0,
                    'revenue' => round((float) ($revenueByPlan[$plan->id] ?? 0) / 100, 2),
                    'color' => $palette[$index % count($palette)],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Engagement trend for the chart, broken down by day (last 30 days),
     * month (last 12 months), and year (last 5 years): new users, new
     * meetings, new incidents, and revenue from succeeded payments.
     *
     * @return array<string, array{labels: array<int, string>, users: array<int, int>, meetings: array<int, int>, incidents: array<int, int>, revenue: array<int, float>}>
     */
    private function engagementTrend(): array
    {
        $now = CarbonImmutable::now();

        return [
            'day' => $this->engagementTrendFor(
                $now->subDays(29)->startOfDay(),
                $now->endOfDay(),
                '%Y-%m-%d',
                'Y-m-d',
                'd M',
                'addDay'
            ),
            'month' => $this->engagementTrendFor(
                $now->startOfMonth()->subMonths(11),
                $now->endOfMonth(),
                '%Y-%m',
                'Y-m',
                'M Y',
                'addMonthNoOverflow'
            ),
            'year' => $this->engagementTrendFor(
                $now->startOfYear()->subYears(4),
                $now->endOfYear(),
                '%Y',
                'Y',
                'Y',
                'addYearNoOverflow'
            ),
        ];
    }

    /**
     * @return array{labels: array<int, string>, users: array<int, int>, meetings: array<int, int>, incidents: array<int, int>, revenue: array<int, float>}
     */
    private function engagementTrendFor(
        CarbonImmutable $start,
        CarbonImmutable $end,
        string $sqlFormat,
        string $bucketFormat,
        string $labelFormat,
        string $step
    ): array {
        $usersByBucket = User::query()
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw("DATE_FORMAT(created_at, ?) as bucket, COUNT(*) as total", [$sqlFormat])
            ->groupBy('bucket')
            ->pluck('total', 'bucket');

        $meetingsByBucket = Meeting::query()
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw("DATE_FORMAT(created_at, ?) as bucket, COUNT(*) as total", [$sqlFormat])
            ->groupBy('bucket')
            ->pluck('total', 'bucket');

        $incidentsByBucket = Incident::query()
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw("DATE_FORMAT(created_at, ?) as bucket, COUNT(*) as total", [$sqlFormat])
            ->groupBy('bucket')
            ->pluck('total', 'bucket');

        $revenueByBucket = Payment::query()
            ->where('status', 'succeeded')
            ->whereBetween('paid_at', [$start, $end])
            ->selectRaw("DATE_FORMAT(paid_at, ?) as bucket, SUM(amount) as total", [$sqlFormat])
            ->groupBy('bucket')
            ->pluck('total', 'bucket');

        $labels = [];
        $users = [];
        $meetings = [];
        $incidents = [];
        $revenue = [];

        $cursor = $start;
        while ($cursor->lte($end)) {
            $bucket = $cursor->format($bucketFormat);

            $labels[] = $cursor->format($labelFormat);
            $users[] = (int) ($usersByBucket[$bucket] ?? 0);
            $meetings[] = (int) ($meetingsByBucket[$bucket] ?? 0);
            $incidents[] = (int) ($incidentsByBucket[$bucket] ?? 0);
            $revenue[] = round((float) ($revenueByBucket[$bucket] ?? 0) / 100, 2);

            $cursor = $cursor->{$step}();
        }

        return compact('labels', 'users', 'meetings', 'incidents', 'revenue');
    }
}
