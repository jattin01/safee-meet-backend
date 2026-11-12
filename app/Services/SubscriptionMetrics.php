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
     * - Stripe-billed subscriptions renew at the plan's current (discounted)
     *   price: a price change moves them onto it. That is what counts.
     * - Others: subscriptions.price stores the first payment, after any
     *   coupon; the coupon is one-off, so price + discount recurs.
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
            ->with('plan:id,monthly_price,yearly_price')
            ->get(['id', 'plan_id', 'price', 'billing_cycle', 'stripe_subscription_id']);

        $discounts = CouponRedemption::query()
            ->whereIn('subscription_id', $subscriptions->pluck('id'))
            ->selectRaw('subscription_id, SUM(discount_amount) as total')
            ->groupBy('subscription_id')
            ->pluck('total', 'subscription_id');

        $mrr = $subscriptions->sum(function (Subscription $subscription) use ($discounts) {
            $recurring = $subscription->stripe_subscription_id && $subscription->plan
                ? (float) ($subscription->billing_cycle === 'yearly' ? $subscription->plan->yearly_price : $subscription->plan->monthly_price)
                : (float) $subscription->price + (float) ($discounts[$subscription->id] ?? 0);

            return $subscription->billing_cycle === 'yearly' ? $recurring / 12 : $recurring;
        });

        return [
            'mrr' => round($mrr, 2),
            'arr' => round($mrr * 12, 2),
            'active_count' => $subscriptions->count(),
        ];
    }
}
