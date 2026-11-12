<?php

namespace App\Jobs;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\StripeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Puts every running Stripe subscription of a plan + billing cycle on the
 * plan's current Stripe Price, so each renewal bills the admin-set
 * (discounted) price, new subscriber or old, up or down. Safe to re-run:
 * subscriptions already on the Price are skipped.
 */
class MovePlanSubscribersToCurrentPrice implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $planId, public string $billingCycle) {}

    public function handle(StripeService $stripe): void
    {
        $plan = SubscriptionPlan::withTrashed()->find($this->planId);
        $priceId = $plan ? $stripe->resolvePriceId($plan, $this->billingCycle) : null;

        if (! $priceId) {
            return;
        }

        $moved = 0;
        $failed = 0;

        Subscription::query()
            ->where('plan_id', $plan->id)
            ->where('billing_cycle', $this->billingCycle)
            ->where('status', 'active')
            ->whereNotNull('stripe_subscription_id')
            ->each(function (Subscription $subscription) use ($stripe, $priceId, $plan, &$moved, &$failed) {
                // One bad subscription must not stop the rest from moving.
                try {
                    $moved += (int) $stripe->moveSubscriptionToPrice($subscription->stripe_subscription_id, $priceId);
                } catch (\Throwable $e) {
                    $failed++;
                    Log::error('Could not move subscription to the plan\'s current price.', [
                        'plan_id' => $plan->id, 'subscription_id' => $subscription->id,
                        'stripe_subscription_id' => $subscription->stripe_subscription_id, 'error' => $e->getMessage(),
                    ]);
                }
            });

        Log::info('Moved plan subscribers to the current price.', [
            'plan_id' => $plan->id, 'billing_cycle' => $this->billingCycle,
            'price_id' => $priceId, 'moved' => $moved, 'failed' => $failed,
        ]);
    }
}
