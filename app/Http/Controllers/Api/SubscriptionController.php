<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SubscriptionController extends Controller
{
    /**
     * GET /api/subscriptions/plans — "Plans" screen.
     * Everything is catalog-driven from subscription_plans.
     */
    public function plans(): JsonResponse
    {
        return response()->json(
            SubscriptionPlan::active()->orderBy('sort_order')->get()
        );
    }

    /**
     * GET /api/subscriptions/current — Profile "Current Plan" card.
     */
    public function current(Request $request): JsonResponse
    {
        $subscription = $request->user()->activeSubscription()->with('plan')->first();

        if (! $subscription) {
            return response()->json(['message' => 'No active subscription'], 404);
        }

        return response()->json($subscription);
    }

    /**
     * POST /api/subscriptions/subscribe — pick a plan (by slug).
     * Price / trial length come from the catalog row, never hardcoded.
     * Payment: this creates the local record; wire the real Stripe call at
     * the TODO before flipping a paid plan to 'active'.
     */
    public function subscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan_slug' => ['required', 'string', Rule::exists('subscription_plans', 'slug')->where('is_active', true)],
            'billing_cycle' => ['required', Rule::in(['monthly', 'yearly'])],
            'stripe_payment_method_id' => ['nullable', 'string'],
        ]);

        $user = $request->user();
        $plan = SubscriptionPlan::where('slug', $validated['plan_slug'])->firstOrFail();

        $isTrial = (int) $plan->trial_days > 0;
        $price = $validated['billing_cycle'] === 'yearly' ? $plan->yearly_price : $plan->monthly_price;

        // A paid plan needs a payment method up front; a plan starting with a
        // free trial does not.
        if (! $isTrial && (float) $price > 0 && empty($validated['stripe_payment_method_id'])) {
            return response()->json([
                'message' => 'A payment method is required for this plan.',
            ], 422);
        }

        $subscription = DB::transaction(function () use ($user, $plan, $validated, $isTrial, $price) {
            // TODO: create the real Stripe subscription here (with trial_period_days
            // = $plan->trial_days when $isTrial) and store its id below.

            $subscription = Subscription::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'price' => $price,
                'billing_cycle' => $validated['billing_cycle'],
                'status' => $isTrial ? 'trial' : 'active',
                'trial_days' => $plan->trial_days,
                'started_at' => now(),
                'renews_at' => $isTrial ? now()->addDays($plan->trial_days) : now()->addMonth(),
                // 'stripe_subscription_id' => $stripeSubscription->id,
            ]);

            $user->update([
                'plan_id' => $plan->id,
                'subscription_status' => $subscription->status,
            ]);

            return $subscription;
        });

        return response()->json($subscription->load('plan'), 201);
    }

    /**
     * POST /api/subscriptions/cancel
     */
    public function cancel(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscription = $user->activeSubscription;

        if (! $subscription) {
            return response()->json(['message' => 'No active subscription'], 404);
        }

        // TODO: cancel on Stripe too: app(StripeService::class)->cancel($subscription->stripe_subscription_id);

        DB::transaction(function () use ($user, $subscription) {
            $subscription->update(['status' => 'cancelled', 'cancelled_at' => now()]);
            $user->update(['subscription_status' => 'cancelled']);
        });

        return response()->json(['message' => 'Subscription cancelled']);
    }
}
