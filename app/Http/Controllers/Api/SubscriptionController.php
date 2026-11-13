<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Feature;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\PlanChangePolicy;
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
use Stripe\Exception\InvalidRequestException;
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

        // What the signed-in user may do with each plan right now, so the app
        // can disable a button instead of letting the purchase fail.
        $current = $request->user()?->activeSubscription()->with('plan')->first();
        $policy = app(PlanChangePolicy::class);

        $plans->each(function (SubscriptionPlan $plan) use ($request, $current, $policy) {
            if ($request->user()) {
                $plan->plan_change = collect(['monthly', 'yearly'])->mapWithKeys(function ($cycle) use ($current, $policy, $plan) {
                    $change = $policy->decide($current, $plan, $cycle);

                    return [$cycle => ['allowed' => $change['type'] !== PlanChangePolicy::BLOCKED] + $change];
                })->all();
            }

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

        // Plan-change rules (downgrade / same plan / yearly upgrade).
        $current = $user->activeSubscription()->with('plan')->first();
        $change = app(PlanChangePolicy::class)->decide($current, $plan, $validated['billing_cycle']);

        if ($change['type'] === PlanChangePolicy::BLOCKED) {
            return $this->planChangeBlocked($change);
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
        $prorated = $needsStripe && $change['type'] === PlanChangePolicy::PRORATED_UPGRADE;

        if ($prorated && $appliedCoupon) {
            return response()->json([
                'message' => 'Coupons cannot be used for a plan upgrade.',
                'code' => 'coupon_not_allowed_for_upgrade',
            ], 422);
        }

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

        // A prorated upgrade charges the card already on file unless a new
        // one is given.
        if (! $prorated && empty($validated['stripe_payment_method_id'])) {
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
            return $this->subscriptions->withLock('checkout:user:'.$user->id, 15, fn () => $prorated
                ? $this->startProratedUpgrade($user, $plan, $validated['stripe_payment_method_id'] ?? null)
                : $this->startPaidCheckout(
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
     * Yearly -> higher yearly plan, run under the user's checkout lock. The
     * same Stripe subscription moves to the new plan's price with proration:
     * only the difference for the rest of the year is charged now and the
     * renewal date stays. If the charge goes through right away the plan is
     * switched here; if it needs 3DS (or was declined) the app confirms the
     * returned client secret, the user stays on the current plan meanwhile,
     * and invoice.paid switches it once paid.
     */
    private function startProratedUpgrade(User $user, SubscriptionPlan $plan, ?string $paymentMethodId): JsonResponse
    {
        // Re-read under the lock: a parallel request may have upgraded already.
        $current = $user->activeSubscription()->with('plan')->first();
        $change = app(PlanChangePolicy::class)->decide($current, $plan, 'yearly');

        if ($change['type'] === PlanChangePolicy::BLOCKED) {
            return $this->planChangeBlocked($change);
        }

        $this->stripe->syncPlanPrice($plan, 'yearly');
        $priceId = $this->stripe->resolvePriceId($plan, 'yearly');

        if (! $priceId) {
            return response()->json([
                'message' => "Plan '{$plan->slug}' has no Stripe price configured for yearly billing.",
            ], 422);
        }

        try {
            $stripeSubscription = $this->stripe->upgradeSubscription($current->stripe_subscription_id, $priceId, $paymentMethodId);
        } catch (\DomainException $e) {
            Log::warning('Upgrade refused: Stripe subscription not active', ['user_id' => $user->id, 'message' => $e->getMessage()]);

            return response()->json([
                'message' => 'Your current plan cannot be upgraded right now. Please try again later or contact support.',
                'code' => 'upgrade_not_possible',
            ], 409);
        }

        $invoice = $stripeSubscription->latest_invoice;

        if (is_object($invoice) && $invoice->status === 'paid') {
            // Charged off-session right away: switch now, without waiting
            // for the webhook (which then finds it already recorded).
            $this->subscriptions->applyPaidInvoice($invoice);
            $upgraded = $user->activeSubscription()->with('plan')->first();

            return response()->json([
                'message' => 'Your plan has been upgraded.',
                'code' => 'upgraded',
                'change_type' => PlanChangePolicy::PRORATED_UPGRADE,
                'amount_charged' => number_format(((int) $invoice->amount_paid) / 100, 2, '.', ''),
                'subscription' => $upgraded?->toArray(),
            ], 200);
        }

        $clientSecret = is_object($invoice) ? ($invoice->confirmation_secret->client_secret ?? null) : null;

        if (! $clientSecret || $invoice->status !== 'open') {
            Log::error('Upgrade left no payable invoice', [
                'user_id' => $user->id, 'stripe_subscription_id' => $stripeSubscription->id,
                'invoice_status' => is_object($invoice) ? $invoice->status : null,
            ]);

            return response()->json([
                'message' => 'We could not start the upgrade payment. Please try again.',
                'code' => 'payment_error',
            ], 502);
        }

        // Same shape as a new checkout, so the app confirms it the same way.
        return response()->json([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'price' => number_format((float) $plan->yearly_price, 2, '.', ''),
            'billing_cycle' => 'yearly',
            'status' => 'incomplete',
            'change_type' => PlanChangePolicy::PRORATED_UPGRADE,
            'amount_due' => number_format(((int) $invoice->amount_due) / 100, 2, '.', ''),
            'stripe_customer_id' => $current->stripe_customer_id,
            'stripe_subscription_id' => $stripeSubscription->id,
            'plan' => $plan->withoutRelations()->toArray(),
            'payment_intent_client_secret' => $clientSecret,
            'payment_intent_status' => 'incomplete',
            'stripe_subscription_status' => $stripeSubscription->status,
            'invoice_status' => $invoice->status,
        ], 201);
    }

    /**
     * GET /api/subscriptions/change-preview?plan_slug=&billing_cycle= — what
     * buying this plan would charge now. Prorated yearly upgrades are priced
     * by Stripe; a new checkout is the full price, and the days left on the
     * current plan (not carried over) are returned for the app's warning.
     */
    public function changePreview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan_slug' => ['required', 'string', Rule::exists('subscription_plans', 'slug')->where('is_active', true)],
            'billing_cycle' => ['required', Rule::in(['monthly', 'yearly'])],
        ]);

        $user = $request->user();
        $plan = SubscriptionPlan::where('slug', $validated['plan_slug'])->firstOrFail();
        $current = $user->activeSubscription()->with('plan')->first();
        $policy = app(PlanChangePolicy::class);
        $change = $policy->decide($current, $plan, $validated['billing_cycle']);

        if ($change['type'] === PlanChangePolicy::BLOCKED) {
            return $this->planChangeBlocked($change);
        }

        $price = (float) ($validated['billing_cycle'] === 'yearly' ? $plan->yearly_price : $plan->monthly_price);

        if ($change['type'] === PlanChangePolicy::PRORATED_UPGRADE) {
            try {
                $this->stripe->syncPlanPrice($plan, 'yearly');
                [$amountDue, $currency] = $this->stripe->previewUpgrade(
                    $current->stripe_subscription_id, $this->stripe->resolvePriceId($plan, 'yearly'),
                );
            } catch (ApiErrorException $e) {
                report($e);

                return response()->json([
                    'message' => 'We could not calculate the upgrade price right now. Please try again.',
                    'code' => 'payment_service_unavailable',
                ], 503);
            }

            return response()->json([
                'change_type' => $change['type'],
                'plan_price' => number_format($price, 2, '.', ''),
                'amount_due_now' => number_format($amountDue / 100, 2, '.', ''),
                'currency' => $currency,
                'renews_at' => $current->renews_at?->toIso8601String(),
                'unused_days_lost' => 0,
            ]);
        }

        $isTrial = (int) $plan->trial_days > 0;
        $paidRunning = $current && $current->status === 'active' && (float) $current->price > 0;

        return response()->json([
            'change_type' => $change['type'],
            'plan_price' => number_format($price, 2, '.', ''),
            'amount_due_now' => number_format($isTrial ? 0 : $price, 2, '.', ''),
            'currency' => 'usd',
            'renews_at' => null,
            // The current paid plan ends now; its remaining days are not
            // carried over.
            'unused_days_lost' => $paidRunning ? $policy->unusedDays($current) : 0,
        ]);
    }

    private function planChangeBlocked(array $change): JsonResponse
    {
        return response()->json([
            'message' => $change['message'],
            'code' => $change['code'],
            'available_from' => $change['available_from'],
        ], 422);
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

        // A retry of the same checkout (same plan, cycle, price, coupon and
        // card), e.g. after a timeout, resumes the unpaid one Stripe already
        // has instead of creating a duplicate. Anything else starts fresh.
        $metadata['payment_method'] = $paymentMethodId;
        $stripeSubscription = $this->stripe->reusableIncompleteSubscription($stripeCustomer, $priceId, $metadata);

        if ($stripeSubscription) {
            Log::info('Subscribe: resuming the pending checkout instead of creating another', [
                'user_id' => $user->id, 'stripe_subscription_id' => $stripeSubscription->id,
            ]);
            $this->stripe->cancelIncompleteSubscriptions($stripeCustomer, $stripeSubscription->id);
        } else {
            $this->stripe->cancelIncompleteSubscriptions($stripeCustomer);

            $stripeSubscription = $this->stripe->createSubscription(
                $stripeCustomer,
                $priceId,
                $paymentMethodId,
                $metadata,
                (int) round($couponDiscount * 100),
                'checkout-'.$user->id.'-'.substr(sha1(json_encode([$priceId, $metadata])), 0, 24),
            );
        }

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

        // Stripe first: if it can't be reached, nothing changes locally and
        // the user is told to try again, so the plan is never shown as
        // cancelled while Stripe keeps billing it.
        if ($subscription->stripe_subscription_id) {
            try {
                $this->stripe->cancelSubscription($subscription->stripe_subscription_id);
            } catch (InvalidRequestException $e) {
                // Already gone on Stripe (cancelled in the Dashboard, or its
                // deleted webhook not processed yet): finish locally.
                if ($e->getStripeCode() !== 'resource_missing') {
                    report($e);

                    return response()->json([
                        'message' => 'We could not cancel your plan right now. Please try again.',
                        'code' => 'payment_error',
                    ], 502);
                }
            } catch (ApiConnectionException|RateLimitException $e) {
                report($e);

                return response()->json([
                    'message' => 'Payment service is temporarily unavailable, so your plan was not cancelled. Please try again in a moment.',
                    'code' => 'payment_service_unavailable',
                ], 503);
            } catch (ApiErrorException $e) {
                report($e);

                return response()->json([
                    'message' => 'We could not cancel your plan right now. Please try again.',
                    'code' => 'payment_error',
                ], 502);
            }
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
