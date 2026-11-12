<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    /**
     * Puts the user on $plan: closes whatever they were on before, creates the
     * subscription + its frozen feature snapshot, records the coupon, and
     * points the user at the new plan. Called by subscribe() for trial/free
     * plans, and by the invoice.paid webhook for paid plans only once the
     * first payment has cleared — so backing out of the payment sheet leaves
     * the user's current plan untouched and writes nothing locally.
     *
     * Idempotent per stripe_subscription_id: a repeated webhook delivery gets
     * the existing row back (wasRecentlyCreated = false).
     */
    public function activate(
        User $user,
        SubscriptionPlan $plan,
        array $attributes,
        ?Coupon $coupon = null,
        float $couponDiscount = 0.0,
    ): Subscription {
        $replaced = collect();

        $subscription = DB::transaction(function () use ($user, $plan, $attributes, $coupon, $couponDiscount, &$replaced) {
            // Serializes concurrent webhook deliveries for the same user.
            User::whereKey($user->id)->lockForUpdate()->first();

            $stripeSubscriptionId = $attributes['stripe_subscription_id'] ?? null;

            if ($stripeSubscriptionId
                && $existing = Subscription::where('stripe_subscription_id', $stripeSubscriptionId)->first()) {
                return $existing;
            }

            // The old records remain as history; the model's updated hook
            // cancels their user_subscriptions snapshots too.
            $replaced = $user->subscriptions()->whereIn('status', ['trial', 'active', 'incomplete'])->get();

            foreach ($replaced as $old) {
                $old->update(['status' => 'cancelled', 'cancelled_at' => now()]);
            }

            $subscription = Subscription::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'trial_days' => $plan->trial_days,
                'started_at' => now(),
                ...$attributes,
            ]);

            if ($coupon) {
                CouponRedemption::create([
                    'coupon_id' => $coupon->id,
                    'user_id' => $user->id,
                    'subscription_id' => $subscription->id,
                    'discount_amount' => $couponDiscount,
                    'redeemed_at' => now(),
                ]);

                $coupon->increment('times_redeemed');
            }

            // Freeze the plan's current matrix; later admin edits affect new
            // purchases only.
            UserSubscription::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'subscription_id' => $subscription->subscription_id,
                'price' => $subscription->price,
                'billing_cycle' => $subscription->billing_cycle,
                'status' => $subscription->status,
                'trial_days' => $subscription->trial_days,
                'started_at' => $subscription->started_at,
                'renews_at' => $subscription->renews_at,
                'cancelled_at' => null,
                'stripe_customer_id' => $subscription->stripe_customer_id,
                'stripe_subscription_id' => $subscription->stripe_subscription_id,
                ...app(PlanEntitlements::class)->snapshotFor($plan),
            ]);

            $user->update([
                'plan_id' => $plan->id,
                'subscription_status' => $subscription->status,
            ]);

            return $subscription;
        });

        // Stop billing the replaced plans only after the new one is committed.
        foreach ($replaced as $old) {
            if ($old->stripe_subscription_id && $old->stripe_subscription_id !== $subscription->stripe_subscription_id) {
                try {
                    app(StripeService::class)->cancelSubscription($old->stripe_subscription_id);
                } catch (\Throwable $e) {
                    // Already cancelled or expired on Stripe.
                }
            }
        }

        return $subscription;
    }
}
