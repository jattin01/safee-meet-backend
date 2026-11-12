<?php

namespace App\Jobs;

use App\Models\SubscriptionPlan;
use App\Services\StripeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Makes one plan's Stripe Prices and Product name match the admin values,
 * then puts its running subscribers on the current Prices. Queued as a
 * retry when the sync during an admin save fails (Stripe down, timeout),
 * and run for every plan by the daily stripe:sync-plan-prices check.
 */
class SyncPlanStripePrices implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900, 3600];

    public function __construct(public int $planId) {}

    public function handle(StripeService $stripe): void
    {
        $plan = SubscriptionPlan::withTrashed()->find($this->planId);

        if (! $plan || $plan->trashed()) {
            return; // deleted plans keep archived Prices
        }

        foreach (['monthly', 'yearly'] as $billingCycle) {
            $stripe->syncPlanPrice($plan, $billingCycle);
            // Also catches subscribers left on an older Price by an earlier
            // failed move, even when the Price itself already matched.
            MovePlanSubscribersToCurrentPrice::dispatchSync($plan->id, $billingCycle);
        }

        $stripe->syncPlanProductName($plan);
    }
}
