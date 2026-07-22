<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;

class SubscriptionService
{
    /**
     * Puts a freshly-registered user onto the free-trial plan: creates a trial
     * subscription lasting the plan's trial_days (default 100) and points the
     * user at it. Idempotent — skips if the user already has any subscription,
     * and no-ops safely if the free_trial plan hasn't been seeded yet.
     *
     * When the trial ends it is expired (not auto-charged) by
     * ProcessSubscriptionTrials; the user must then pay to keep searching/chatting.
     */
    public function startFreeTrial(User $user): void
    {
        $plan = SubscriptionPlan::where('slug', 'free_trial')->first();

        if (! $plan || $user->subscriptions()->exists()) {
            return;
        }

        $trialDays = (int) ($plan->trial_days ?: 100);

        Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'price' => $plan->monthly_price,
            'billing_cycle' => 'monthly',
            'status' => 'trial',
            'trial_days' => $trialDays,
            'started_at' => now(),
            'renews_at' => now()->addDays($trialDays),
        ]);

        // saveQuietly: we're already inside the user's created event; avoid
        // firing further model events for this housekeeping update.
        $user->plan_id = $plan->id;
        $user->subscription_status = 'trial';
        $user->saveQuietly();
    }
}
