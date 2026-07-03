<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubscriptionController extends Controller
{
    /**
     * GET /api/subscriptions/plans — "Plans" screen
     */
    public function plans(): JsonResponse
    {
        return response()->json(SubscriptionPlan::orderBy('sort_order')->get());
    }

    /**
     * GET /api/subscriptions/current — Profile screen "Current Plan" card
     */
    public function current(Request $request): JsonResponse
    {
        $subscription = $request->user()->activeSubscription;

        if (! $subscription) {
            return response()->json(['message' => 'No active subscription'], 404);
        }

        return response()->json($subscription);
    }

    /**
     * POST /api/subscriptions/subscribe — Step 4 of onboarding (Free Trial / Basic / Premium / Professional)
     * Payment is handled via Stripe per the SOW; this stub creates the local subscription
     * record and expects a Stripe PaymentIntent/Subscription to already be confirmed client-side
     * (or wired via webhook — see TODO below).
     */
    public function subscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan' => ['required', Rule::in(['free_trial', 'basic', 'premium', 'professional'])],
            'billing_cycle' => ['required', Rule::in(['trial', 'monthly'])],
            'stripe_payment_method_id' => ['required_unless:plan,free_trial', 'nullable', 'string'],
        ]);

        $user = $request->user();

        $prices = [
            'free_trial' => 0,
            'basic' => 0.99,
            'premium' => 4.99,
            'professional' => 9.99,
        ];

        // TODO: integrate real Stripe subscription creation here, e.g.:
        // $stripeSubscription = app(StripeService::class)->subscribe($user, $validated['plan'], $validated['stripe_payment_method_id']);

        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan' => $validated['plan'],
            'price' => $prices[$validated['plan']],
            'billing_cycle' => $validated['billing_cycle'],
            'status' => $validated['plan'] === 'free_trial' ? 'trial' : 'active',
            'trial_days' => $validated['plan'] === 'free_trial' ? 30 : null,
            'started_at' => now(),
            'renews_at' => $validated['plan'] === 'free_trial' ? now()->addDays(30) : now()->addMonth(),
            // 'stripe_subscription_id' => $stripeSubscription->id,
        ]);

        $user->update([
            'subscription_plan' => $validated['plan'],
            'subscription_status' => $subscription->status,
        ]);

        return response()->json($subscription, 201);
    }

    /**
     * POST /api/subscriptions/cancel
     */
    public function cancel(Request $request): JsonResponse
    {
        $subscription = $request->user()->activeSubscription;

        if (! $subscription) {
            return response()->json(['message' => 'No active subscription'], 404);
        }

        // TODO: cancel on Stripe's side too: app(StripeService::class)->cancel($subscription->stripe_subscription_id);

        $subscription->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        $request->user()->update(['subscription_status' => 'cancelled']);

        return response()->json(['message' => 'Subscription cancelled']);
    }
}
