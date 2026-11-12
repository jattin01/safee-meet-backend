<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Feature;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\StripeService;
use App\Services\SubscriptionService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Stripe\Exception\ApiConnectionException;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\CardException;
use Stripe\Exception\RateLimitException;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly StripeService $stripe,
        private readonly SubscriptionService $subscriptions,
    ) {}

    /**
     * GET /api/subscriptions/plans — "Plans" screen.
     * Everything is catalog-driven from subscription_plans, filtered to the
     * caller's account_type (normal/employer; plans tagged 'both' show for
     * everyone). Falls back to no filtering for guests, since there's no
     * account_type to filter by.
     */
    public function plans(Request $request): JsonResponse
    {
        $plans = SubscriptionPlan::active()
            ->when($request->user(), fn ($query, $user) => $query->forAccountType($user->account_type))
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
    public function comparison(Request $request): JsonResponse
    {
        $plans = SubscriptionPlan::active()
            ->when($request->user(), fn ($query, $user) => $query->forAccountType($user->account_type))
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
     *
     * Always 200. With no running plan, `plan` is null and `status` says why
     * ('none' = never subscribed, or 'expired' / 'cancelled'), so the app can
     * show the empty state + "Choose a plan" without treating it as an error.
     */
    public function current(Request $request): JsonResponse
    {
        $user = $request->user();

        $subscription = $user
            ->activeSubscription()
            ->with('plan.comparisonFeatures:id,slug,name,type')
            ->first();

        if (! $subscription) {
            $status = in_array($user->subscription_status, ['expired', 'cancelled'], true)
                ? $user->subscription_status
                : 'none';

            return response()->json([
                'status' => $status,
                'plan' => null,
                'message' => 'No active subscription',
            ]);
        }

        $plan = $subscription->plan;
        $catalogFeatures = $plan->comparisonFeatures->map(fn (Feature $feature) => [
            'id' => $feature->id,
            'slug' => $feature->slug,
            'name' => $feature->name,
            'type' => $feature->type,
            'value' => $feature->pivot->value,
        ])->values();

        $plan->features = $catalogFeatures;

        $plan->unsetRelation('comparisonFeatures');

        return response()->json($subscription);
    }

    /**
     * POST /api/subscriptions/subscribe — pick a plan (by slug).
     * Price / trial length come from the catalog row, never hardcoded.
     * Free-trial plans stay local-only (no card needed); paid plans create a
     * real Stripe subscription and may come back needing 3DS confirmation.
     */
    // public function subscribe(Request $request): JsonResponse
    // {
    //     $validated = $request->validate([
    //         'plan_slug' => ['required', 'string', Rule::exists('subscription_plans', 'slug')->where('is_active', true)],
    //         'billing_cycle' => ['required', Rule::in(['monthly', 'yearly'])],
    //         'stripe_payment_method_id' => ['nullable', 'string'],
    //     ]);

    //     $user = $request->user();
    //     $plan = SubscriptionPlan::where('slug', $validated['plan_slug'])->firstOrFail();

    //     // Close out any still-open subscription (trial, active, or a stalled
    //     // payment-pending one) before starting a new one. Without this, a
    //     // retried or upgraded subscribe() call creates a parallel Stripe
    //     // subscription that keeps billing independently of the old one.
    //     $stillOpen = $user->subscriptions()->whereIn('status', ['trial', 'active', 'incomplete'])->get();
    //     foreach ($stillOpen as $old) {
    //         if ($old->stripe_subscription_id) {
    //             try {
    //                 $this->stripe->cancelSubscription($old->stripe_subscription_id);
    //             } catch (\Throwable $e) {
    //                 // Already cancelled/expired on Stripe's side — nothing to do.
    //             }
    //         }
    //         $old->update(['status' => 'cancelled', 'cancelled_at' => now()]);
    //     }

    //     $isTrial = (int) $plan->trial_days > 0;
    //     $price = $validated['billing_cycle'] === 'yearly' ? $plan->yearly_price : $plan->monthly_price;

    //     // A paid plan needs a payment method up front; a plan starting with a
    //     // free trial does not.
    //     if (! $isTrial && (float) $price > 0 && empty($validated['stripe_payment_method_id'])) {
    //         return response()->json([
    //             'message' => 'A payment method is required for this plan.',
    //         ], 422);
    //     }

    //     $needsStripe = ! $isTrial && (float) $price > 0;
    //     $stripeCustomer = null;
    //     $stripeSubscription = null;

    //     if ($needsStripe) {
    //         $priceId = $this->stripe->resolvePriceId($plan, $validated['billing_cycle']);

    //         if (! $priceId) {
    //             return response()->json([
    //                 'message' => "Plan '{$plan->slug}' has no Stripe price configured for {$validated['billing_cycle']} billing.",
    //             ], 422);
    //         }

    //         $stripeCustomer = $this->stripe->createOrGetCustomer($user);
    //         $stripeSubscription = $this->stripe->createSubscription(
    //             $stripeCustomer,
    //             $priceId,
    //             $validated['stripe_payment_method_id'],
    //             null,
    //         );
    //     }

    //     // client_secret is always "{payment_intent_id}_secret_{...}" — Stripe
    //     // stopped exposing latest_invoice.payment_intent directly, so this is
    //     // the only place we can recover the id the payment_intent.* webhook
    //     // events will reference.
    //     $confirmationSecret = $stripeSubscription?->latest_invoice?->confirmation_secret;
    //     $paymentIntentId = $confirmationSecret
    //         ? strstr($confirmationSecret->client_secret, '_secret_', true)
    //         : null;

    //     $subscription = DB::transaction(function () use ($user, $plan, $validated, $isTrial, $price, $stripeCustomer, $stripeSubscription, $paymentIntentId) {
    //         // A freshly created Stripe subscription is 'incomplete' until its
    //         // first invoice is paid — only 'active'/'trialing' from Stripe
    //         // may grant entitlements; everything else must stay non-active
    //         // here too, or scopeActive() would let an unpaid user in.
    //         $status = match (true) {
    //             $isTrial => 'trial',
    //             $stripeSubscription?->status === 'active' => 'active',
    //             default => 'incomplete',
    //         };

    //         $subscription = Subscription::create([
    //             'user_id' => $user->id,
    //             'plan_id' => $plan->id,
    //             'price' => $price,
    //             'billing_cycle' => $validated['billing_cycle'],
    //             'status' => $status,
    //             'trial_days' => $plan->trial_days,
    //             'started_at' => now(),
    //             'renews_at' => match (true) {
    //                 $isTrial => now()->addDays($plan->trial_days),
    //                 $validated['billing_cycle'] === 'yearly' => now()->addYear(),
    //                 default => now()->addMonth(),
    //             },
    //             'stripe_customer_id' => $stripeCustomer?->id,
    //             'stripe_subscription_id' => $stripeSubscription?->id,
    //         ]);

    //         if ($paymentIntentId) {
    //             Payment::create([
    //                 'user_id' => $user->id,
    //                 'subscription_id' => $subscription->id,
    //                 'stripe_payment_intent_id' => $paymentIntentId,
    //                 'amount' => (int) round($price * 100),
    //                 'status' => 'pending',
    //             ]);
    //         }

    //         $user->update([
    //             'plan_id' => $plan->id,
    //             'subscription_status' => $subscription->status,
    //         ]);

    //         return $subscription;
    //     });

    //     $response = $subscription->load('plan')->toArray();

    //     // App must confirm this client_secret via Stripe SDK (confirmPayment)
    //     // before the subscription becomes chargeable; the webhook then flips
    //     // the local status to 'active' once invoice.paid fires.
    //     if ($confirmationSecret) {
    //         $response['payment_intent_client_secret'] = $confirmationSecret->client_secret;
    //         $response['payment_intent_status'] = $stripeSubscription->status;
    //     }

    //     return response()->json($response, 201);
    // }
    public function subscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan_slug' => ['required', 'string', Rule::exists('subscription_plans', 'slug')->where('is_active', true)],
            'billing_cycle' => ['required', Rule::in(['monthly', 'yearly'])],
            'stripe_payment_method_id' => ['nullable', 'string'],
            'coupon_code' => ['nullable', 'string', 'max:40'],
        ]);

        $user = $request->user();
        $plan = SubscriptionPlan::where('slug', $validated['plan_slug'])->with('comparisonFeatures')->firstOrFail();

        // Enforce the same account_type targeting that plans()/comparison() use
        // to decide what's shown — 'both' is open to everyone, otherwise the
        // plan's account_type must match the buyer's, so a normal user can't
        // buy an employer-only plan (or vice versa) even by calling the API
        // directly with a known slug.
        if ($plan->account_type !== 'both' && $plan->account_type !== $user->account_type) {
            return response()->json([
                'message' => 'This plan is not available for your account type.',
            ], 422);
        }

        $appliedCoupon = null;
        $couponDiscount = 0.0;

        if (! empty($validated['coupon_code'])) {
            $appliedCoupon = Coupon::where('code', strtoupper($validated['coupon_code']))->first();

            if (! $appliedCoupon) {
                return response()->json(['message' => 'Invalid coupon code.'], 422);
            }

            $couponError = $appliedCoupon->eligibilityError($user->id, $plan, $validated['billing_cycle']);

            if ($couponError) {
                return response()->json(['message' => $couponError], 422);
            }
        }

        $isTrial = (int) $plan->trial_days > 0;
        $isYearly = $validated['billing_cycle'] === 'yearly';

        // The admin-set (discounted) price — the Stripe Price is kept in sync
        // with it below, so this is exactly what the user is charged.
        $price = (float) ($isYearly ? $plan->yearly_price : $plan->monthly_price);

        if ($appliedCoupon) {
            $couponDiscount = $appliedCoupon->discountFor($price);
            $price = round($price - $couponDiscount, 2);
        }

        $needsStripe = ! $isTrial && $price > 0;

        /*
        |--------------------------------------------------------------------------
        | Trial / free plans — no payment, active immediately
        |--------------------------------------------------------------------------
        */

        if (! $needsStripe) {
            $subscription = $this->subscriptions->activate($user, $plan, [
                'price' => $price,
                'billing_cycle' => $validated['billing_cycle'],
                'status' => $isTrial ? 'trial' : 'active',
                'renews_at' => match (true) {
                    $isTrial => now()->addDays($plan->trial_days),
                    $isYearly => now()->addYear(),
                    default => now()->addMonth(),
                },
            ], $appliedCoupon, $couponDiscount);

            return response()->json($subscription->load('plan')->toArray(), 201);
        }

        /*
        |--------------------------------------------------------------------------
        | Paid plans — create the Stripe subscription only
        |--------------------------------------------------------------------------
        | Nothing is written locally here. The current plan stays as-is, and the
        | subscription, snapshot, payment and coupon redemption are created by
        | the invoice.paid webhook once the first payment succeeds. Backing out
        | of the payment sheet therefore leaves no trace beyond an unpaid Stripe
        | subscription, which is cancelled on the next attempt (or expires).
        */

        if (empty($validated['stripe_payment_method_id'])) {
            return response()->json([
                'message' => 'A payment method is required for this plan.',
            ], 422);
        }

        $metadata = [
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'billing_cycle' => $validated['billing_cycle'],
            'price' => $price,
            'coupon_id' => $appliedCoupon?->id,
            'coupon_discount' => $couponDiscount,
        ];

        try {
            // One checkout per user at a time (double tap, two devices): the
            // second request waits, then sees the first one's result.
            return $this->subscriptions->withLock('checkout:user:'.$user->id, 15, fn () => $this->startPaidCheckout(
                $user, $plan, $validated['billing_cycle'], $validated['stripe_payment_method_id'], $price, $metadata, $couponDiscount,
            ));
        } catch (LockTimeoutException) {
            return response()->json([
                'message' => 'Another purchase is already in progress. Please wait a moment and check your plan.',
                'code' => 'checkout_in_progress',
            ], 409);
        } catch (CardException $e) {
            // The card was refused while attaching it (expired, declined,
            // incorrect details): the user can fix it and try again.
            Log::info('Subscribe: card refused by Stripe', ['user_id' => $user->id, 'code' => $e->getStripeCode()]);

            return response()->json([
                'message' => $e->getMessage() ?: 'Your card was declined. Please try another card.',
                'code' => $e->getStripeCode() ?? 'card_declined',
            ], 422);
        } catch (ApiConnectionException|RateLimitException $e) {
            report($e);

            return response()->json([
                'message' => 'Payment service is temporarily unavailable. Please try again in a moment.',
                'code' => 'payment_service_unavailable',
            ], 503);
        } catch (ApiErrorException $e) {
            report($e);

            return response()->json([
                'message' => 'We could not start the payment. Please try again.',
                'code' => 'payment_error',
            ], 502);
        }
    }

    /**
     * The Stripe half of a paid subscribe(), run under the user's checkout
     * lock. If Stripe already holds a paid subscription of this user that
     * we have not recorded (its invoice.paid webhook is late), that one is
     * activated now and no second charge is started.
     */
    private function startPaidCheckout(
        User $user,
        SubscriptionPlan $plan,
        string $billingCycle,
        string $paymentMethodId,
        float $price,
        array $metadata,
        float $couponDiscount,
    ): JsonResponse {
        // Self-heals plans added or repriced before Stripe sync existed, so
        // the Stripe Price always matches the admin-set amount.
        $this->stripe->syncPlanPrice($plan, $billingCycle);
        $priceId = $this->stripe->resolvePriceId($plan, $billingCycle);

        if (! $priceId) {
            return response()->json([
                'message' => "Plan '{$plan->slug}' has no Stripe price configured for {$billingCycle} billing.",
            ], 422);
        }

        $stripeCustomer = $this->stripe->createOrGetCustomer($user);

        if ($paid = $this->stripe->paidUnrecordedSubscription($stripeCustomer, $user)) {
            $activated = $this->subscriptions->reconcilePaidStripeSubscription($paid);

            if ($activated) {
                Log::info('Subscribe: payment already received, activated instead of charging again', [
                    'user_id' => $user->id, 'stripe_subscription_id' => $paid->id,
                ]);

                return response()->json([
                    'message' => 'Payment already received. Your plan is now active.',
                    'code' => 'payment_already_received',
                    'subscription' => $activated->load('plan')->toArray(),
                ], 200);
            }
        }

        $this->stripe->cancelIncompleteSubscriptions($stripeCustomer);

        $stripeSubscription = $this->stripe->createSubscription(
            $stripeCustomer,
            $priceId,
            $paymentMethodId,
            $metadata,
            (int) round($couponDiscount * 100),
        );

        // App must confirm this client_secret via Stripe SDK (confirmPayment);
        // the invoice.paid webhook then activates the plan.
        return response()->json([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'price' => number_format($price, 2, '.', ''),
            'billing_cycle' => $billingCycle,
            'status' => 'incomplete',
            'stripe_customer_id' => $stripeCustomer->id,
            'stripe_subscription_id' => $stripeSubscription->id,
            'plan' => $plan->withoutRelations()->toArray(),
            'payment_intent_client_secret' => $stripeSubscription->latest_invoice?->confirmation_secret?->client_secret,
            // Kept for app compatibility: despite the name this has always
            // been the Stripe SUBSCRIPTION status ('incomplete' until paid).
            // The app confirms with the client secret either way; 3DS is
            // handled by the Stripe SDK during that confirmation.
            'payment_intent_status' => $stripeSubscription->status,
            'stripe_subscription_status' => $stripeSubscription->status,
            'invoice_status' => $stripeSubscription->latest_invoice?->status,
        ], 201);
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
            $subscription->userSubscription()->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);
            $user->update(['subscription_status' => 'cancelled']);
        });

        return response()->json(['message' => 'Subscription cancelled']);
    }
}
