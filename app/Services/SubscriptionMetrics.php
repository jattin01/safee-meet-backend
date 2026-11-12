<?php

namespace App\Services;

use App\Models\CouponRedemption;
use App\Models\Subscription;

class SubscriptionMetrics
{
    /**
     * Monthly recurring revenue from paid, running subscriptions.
     *
     * - Only status 'active' counts: trials are not paying yet, and
     *   expired / cancelled / incomplete rows collect nothing.
     * - subscriptions.price stores the first payment, after any coupon. A
     *   coupon applies to the first invoice only, so renewals bill
     *   price + coupon discount; that recurring amount is what counts.
     * - Yearly plans are normalized to a monthly figure (amount / 12).
     *
     * Single source for every MRR shown in the admin panel, so the Plans and
     * Revenue pages always agree.
     *
     * @return array{mrr: float, arr: float, active_count: int}
     */
    public function recurringRevenue(): array
    {
        $subscriptions = Subscription::query()
            ->where('status', 'active')
            ->get(['id', 'price', 'billing_cycle']);

        $discounts = CouponRedemption::query()
            ->whereIn('subscription_id', $subscriptions->pluck('id'))
            ->selectRaw('subscription_id, SUM(discount_amount) as total')
            ->groupBy('subscription_id')
            ->pluck('total', 'subscription_id');

        $mrr = $subscriptions->sum(function (Subscription $subscription) use ($discounts) {
            $recurring = (float) $subscription->price + (float) ($discounts[$subscription->id] ?? 0);

            return $subscription->billing_cycle === 'yearly' ? $recurring / 12 : $recurring;
        });

        return [
            'mrr' => round($mrr, 2),
            'arr' => round($mrr * 12, 2),
            'active_count' => $subscriptions->count(),
        ];
    }
}
