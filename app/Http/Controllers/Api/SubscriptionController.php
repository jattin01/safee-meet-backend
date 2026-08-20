<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Feature;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SubscriptionController extends Controller
{
    public function __construct(private readonly StripeService $stripe)
    {
    }

    /**
     * GET /api/subscriptions/plans — "Plans" screen.
     * Everything is catalog-driven from subscription_plans.
     */
    public function plans(): JsonResponse
    {
        $plans = SubscriptionPlan::active()
            ->orderBy('sort_order')
            ->with('comparisonFeatures:id,slug,name,type')
            ->get();

        $plans->each(function (SubscriptionPlan $plan) {
            // Enrich the free-text `features` list with each matching row's
            // id/slug from the features table (matched by name), so the
            // client can link back to the catalog instead of just showing text.
            $catalog = $plan->comparisonFeatures->keyBy('name');

            $plan->features = collect($plan->features)->map(function ($feature) use ($catalog) {
                $match = $catalog->get($feature);

                return [
                    'id' => $match?->id,
                    'slug' => $match?->slug,
                    'name' => $feature,
                    'type' => $match?->type,
                    // plan_features table se value
                    'value' => $match?->pivot?->value,
                ]; 
            })->values();

            $plan->unsetRelation('comparisonFeatures');
        });

        return response()->json($plans);
    }

    /**
     * GET /api/subscriptions/comparison — feature comparison matrix.
     * Returns active plans (columns) and features grouped into sections (rows),
     * each row carrying every plan's included/value so the client can render a
     * complete grid without extra lookups. Missing = not included.
     */
    public function comparison(): JsonResponse
    {
        $plans = SubscriptionPlan::active()
            ->orderBy('sort_order')
            ->get(['id', 'slug', 'name', 'monthly_price', 'yearly_price', 'trial_days']);

        // Baseline cell for every plan, overridden where a mapping exists.
        $baseline = $plans->mapWithKeys(fn ($plan) => [
            $plan->slug => ['included' => false, 'value' => null],
        ])->all();

        $features = Feature::active()
            ->orderBy('sort_order')
            ->with(['plans:id,slug'])
            ->get();

        $groups = $features
            ->groupBy('group')
            ->map(fn ($groupFeatures, $groupName) => [
                'name' => $groupName,
                'features' => $groupFeatures->map(function (Feature $feature) use ($baseline) {
                    $cells = $baseline;
                    foreach ($feature->plans as $plan) {
                        $cells[$plan->slug] = [
                            'included' => (bool) $plan->pivot->included,
                            'value' => $plan->pivot->value,
                        ];
                    }

                    return [
                        'slug' => $feature->slug,
                        'name' => $feature->name,
                        'type' => $feature->type,
                        'plans' => $cells,
                    ];
                })->values(),
            ])
            ->values();

        return response()->json([
            'plans' => $plans,
            'groups' => $groups,
        ]);
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
     * Free-trial plans stay local-only (no card needed); paid plans create a
     * real Stripe subscription and may come back needing 3DS confirmation.
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

        // Close out any still-open subscription (trial, active, or a stalled
        // payment-pending one) before starting a new one. Without this, a
        // retried or upgraded subscribe() call creates a parallel Stripe
        // subscription that keeps billing independently of the old one.
        $stillOpen = $user->subscriptions()->whereIn('status', ['trial', 'active', 'incomplete'])->get();
        foreach ($stillOpen as $old) {
            if ($old->stripe_subscription_id) {
                try {
                    $this->stripe->cancelSubscription($old->stripe_subscription_id);
                } catch (\Throwable $e) {
                    // Already cancelled/expired on Stripe's side — nothing to do.
                }
            }
            $old->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        }

        $isTrial = (int) $plan->trial_days > 0;
        $price = $validated['billing_cycle'] === 'yearly' ? $plan->yearly_price : $plan->monthly_price;

        // A paid plan needs a payment method up front; a plan starting with a
        // free trial does not.
        if (! $isTrial && (float) $price > 0 && empty($validated['stripe_payment_method_id'])) {
            return response()->json([
                'message' => 'A payment method is required for this plan.',
            ], 422);
        }

        $needsStripe = ! $isTrial && (float) $price > 0;
        $stripeCustomer = null;
        $stripeSubscription = null;

        if ($needsStripe) {
            $priceId = $this->stripe->resolvePriceId($plan, $validated['billing_cycle']);

            if (! $priceId) {
                return response()->json([
                    'message' => "Plan '{$plan->slug}' has no Stripe price configured for {$validated['billing_cycle']} billing.",
                ], 422);
            }

            $stripeCustomer = $this->stripe->createOrGetCustomer($user);
            $stripeSubscription = $this->stripe->createSubscription(
                $stripeCustomer,
                $priceId,
                $validated['stripe_payment_method_id'],
                null,
            );
        }

        // client_secret is always "{payment_intent_id}_secret_{...}" — Stripe
        // stopped exposing latest_invoice.payment_intent directly, so this is
        // the only place we can recover the id the payment_intent.* webhook
        // events will reference.
        $confirmationSecret = $stripeSubscription?->latest_invoice?->confirmation_secret;
        $paymentIntentId = $confirmationSecret
            ? strstr($confirmationSecret->client_secret, '_secret_', true)
            : null;

        $subscription = DB::transaction(function () use ($user, $plan, $validated, $isTrial, $price, $stripeCustomer, $stripeSubscription, $paymentIntentId) {
            // A freshly created Stripe subscription is 'incomplete' until its
            // first invoice is paid — only 'active'/'trialing' from Stripe
            // may grant entitlements; everything else must stay non-active
            // here too, or scopeActive() would let an unpaid user in.
            $status = match (true) {
                $isTrial => 'trial',
                $stripeSubscription?->status === 'active' => 'active',
                default => 'incomplete',
            };

            $subscription = Subscription::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'price' => $price,
                'billing_cycle' => $validated['billing_cycle'],
                'status' => $status,
                'trial_days' => $plan->trial_days,
                'started_at' => now(),
                'renews_at' => match (true) {
                    $isTrial => now()->addDays($plan->trial_days),
                    $validated['billing_cycle'] === 'yearly' => now()->addYear(),
                    default => now()->addMonth(),
                },
                'stripe_customer_id' => $stripeCustomer?->id,
                'stripe_subscription_id' => $stripeSubscription?->id,
            ]);

            if ($paymentIntentId) {
                Payment::create([
                    'user_id' => $user->id,
                    'subscription_id' => $subscription->id,
                    'stripe_payment_intent_id' => $paymentIntentId,
                    'amount' => (int) round($price * 100),
                    'status' => 'pending',
                ]);
            }

            $user->update([
                'plan_id' => $plan->id,
                'subscription_status' => $subscription->status,
            ]);

            return $subscription;
        });

        $response = $subscription->load('plan')->toArray();

        // App must confirm this client_secret via Stripe SDK (confirmPayment)
        // before the subscription becomes chargeable; the webhook then flips
        // the local status to 'active' once invoice.paid fires.
        if ($confirmationSecret) {
            $response['payment_intent_client_secret'] = $confirmationSecret->client_secret;
            $response['payment_intent_status'] = $stripeSubscription->status;
        }

        return response()->json($response, 201);
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

        if ($subscription->stripe_subscription_id) {
            $this->stripe->cancelSubscription($subscription->stripe_subscription_id);
        }

        DB::transaction(function () use ($user, $subscription) {
            $subscription->update(['status' => 'cancelled', 'cancelled_at' => now()]);
            $user->update(['subscription_status' => 'cancelled']);
        });

        return response()->json(['message' => 'Subscription cancelled']);
    }
}
