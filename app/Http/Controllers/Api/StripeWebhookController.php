<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\SubscriptionInvoiceMail;
use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use App\Models\Coupon;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\PushNotificationService;
use App\Services\StripeService;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Stripe\Exception\SignatureVerificationException;
use UnexpectedValueException;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    /**
     * POST /api/v1/webhooks/stripe — no auth middleware, Stripe calls this
     * directly. Trust boundary is the signature check below, not sanctum.
     *
     * Renewals, failed charges, and out-of-band cancellations (e.g. from the
     * Stripe dashboard) all arrive here rather than through an app screen, so
     * this is the only reliable place to keep `subscriptions`/`users` in sync.
     */
    public function __invoke(Request $request): JsonResponse
{
    log::info('Stripe webhook received', [
        'headers' => $request->headers->all(),
        'body' => $request->getContent(),
    ]);


    $payload = $request->getContent();
    $signature = $request->header('Stripe-Signature');
    $webhookSecret = config('services.stripe.webhook_secret');

    try {
        // Verify that webhook actually came from Stripe
        $event = Webhook::constructEvent(
            $payload,
            $signature,
            $webhookSecret
        );
    } catch (UnexpectedValueException $exception) {
        Log::error('Invalid Stripe webhook payload', [
            'message' => $exception->getMessage(),
        ]);

        return response()->json([
            'message' => 'Invalid webhook payload',
        ], 400);
    } catch (SignatureVerificationException $exception) {
        Log::error('Invalid Stripe webhook signature', [
            'message' => $exception->getMessage(),
        ]);

        return response()->json([
            'message' => 'Invalid webhook signature',
        ], 400);
    }

    $eventType = $event->type;

    // Renewals (and out-of-band changes on Stripe's side) arrive as
    // invoice.*/customer.subscription.* events, not payment_intent.* — their
    // data.object is an Invoice/Subscription, not a PaymentIntent, and are
    // looked up by stripe_subscription_id instead of a payment intent id.
    // Route these to their own handlers before the payment_intent-specific
    // lookup below, which would otherwise misread the invoice's own id as a
    // payment intent id and silently no-op with "Subscription not found".
    switch ($eventType) {
        case 'invoice.paid':
            $this->handleInvoicePaid($event);

            return response()->json(['message' => 'Webhook processed successfully'], 200);

        case 'invoice.payment_failed':
            $this->handlePaymentFailed($event);

            return response()->json(['message' => 'Webhook processed successfully'], 200);

        case 'customer.subscription.deleted':
            $this->handleSubscriptionDeleted($event);

            return response()->json(['message' => 'Webhook processed successfully'], 200);

        case 'charge.refunded':
            $this->handleChargeRefunded($event);

            return response()->json(['message' => 'Webhook processed successfully'], 200);
    }

    $paymentIntent = $event->data->object;

    $paymentIntentId = $paymentIntent->id ?? null;
    $stripeStatus = $paymentIntent->status ?? null;

    Log::info('Verified Stripe webhook received', [
        'event_id' => $event->id,
        'event_type' => $eventType,
        'payment_intent_id' => $paymentIntentId,
        'stripe_status' => $stripeStatus,
    ]);

    if (! $paymentIntentId) {
        return response()->json([
            'message' => 'Payment Intent ID not found',
        ], 422);
    }

    $payment = Payment::where('stripe_payment_intent_id', $paymentIntentId)->first();

    if (! $payment || ! $payment->subscription) {
        Log::warning('Subscription not found', [
            'payment_intent_id' => $paymentIntentId,
        ]);

        return response()->json([
            'message' => 'Subscription not found',
        ], 200);
    }

    $subscription = $payment->subscription;

    switch ($eventType) {
        case 'payment_intent.created':
            Log::warning('step1 Subscription set to incomplete', ['payment_intent_id' => $paymentIntentId]);
            $subscription->update([
                'status' => 'incomplete',
            ]);
            break;

        case 'payment_intent.processing':
            Log::warning(' step 2 Subscription set to incomplete', ['payment_intent_id' => $paymentIntentId]);
            $subscription->update([
                'status' => 'incomplete',
            ]);
            break;

        case 'payment_intent.succeeded':
            Log::warning('Step 3 Subscription set to active', ['payment_intent_id' => $paymentIntentId]);
            $subscription->update([
                'status' => 'active',
            ]);
            $subscription->user()->update(['subscription_status' => 'active']);
            // Stripe's received amount is the source of truth for revenue.
            $payment->update([
                'status' => 'succeeded',
                'amount' => $paymentIntent->amount_received ?? $payment->amount,
                'currency' => $paymentIntent->currency ?? $payment->currency,
                'paid_at' => now(),
            ]);

            app(PushNotificationService::class)->sendToUser(
                $subscription->user,
                'Subscription purchase successful',
                'Your plan was purchased successfully.',
                ['type' => 'subscription_purchased', 'subscription_id' => (string) $subscription->id],
            );

            $this->sendInvoiceMail($subscription, $payment);
            break;

        case 'payment_intent.payment_failed':
            Log::warning('Step 4 Subscription set to expired', ['payment_intent_id' => $paymentIntentId]);
            $subscription->update([
                'status' => 'expired',
            ]);
            $this->syncUserStatus($subscription, 'expired');
            $payment->update([
                'status' => 'failed',
            ]);
            break;

        case 'payment_intent.canceled':
            Log::warning('Step 5 Subscription set to cancelled', ['payment_intent_id' => $paymentIntentId]);
            $subscription->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);
            $this->syncUserStatus($subscription, 'cancelled');
            break;

        default:
            Log::info('Stripe webhook event ignored', [
                'event_type' => $eventType,
                'payment_intent_id' => $paymentIntentId,
            ]);
            break;
    }

    return response()->json([
        'message' => 'Webhook processed successfully',
    ], 200);
}

    /**
     * Stripe's newer API versions moved the subscription id off the invoice's
     * top-level `subscription` field (removed) onto
     * `parent.subscription_details.subscription`.
     */
    private function invoiceSubscriptionId($invoice): ?string
    {
        return $invoice->parent?->subscription_details?->subscription ?? $invoice->subscription ?? null;
    }

    private function handleInvoicePaid($event): void
    {
        $invoice = $event->data->object;
        $stripeSubscriptionId = $this->invoiceSubscriptionId($invoice);

        if (! $stripeSubscriptionId) {
            return;
        }

        $subscription = Subscription::where('stripe_subscription_id', $stripeSubscriptionId)->first();
        $isFirstPayment = false;

        if ($subscription) {
            // Renewal (or a pre-existing incomplete row being paid).
            $subscription->update([
                'status' => 'active',
                'renews_at' => $subscription->billing_cycle === 'yearly' ? now()->addYear() : now()->addMonth(),
            ]);
            $subscription->user()->update(['subscription_status' => 'active']);
        } else {
            // First payment of a subscribe() checkout — nothing exists
            // locally until now.
            $subscription = $this->activateFromStripe($stripeSubscriptionId);

            if (! $subscription) {
                return;
            }

            $isFirstPayment = $subscription->wasRecentlyCreated;
        }

        // Newer Stripe API versions no longer put payment_intent on the
        // invoice, so it is looked up via the invoice's payments. It is
        // needed later to match a refund (charge.refunded) to this row. Only
        // set it when found so an id stored earlier isn't wiped out.
        $paymentIntentId = $invoice->payment_intent ?? null;

        if (! $paymentIntentId) {
            try {
                $paymentIntentId = app(StripeService::class)->paymentIntentIdForInvoice($invoice->id);
            } catch (\Throwable $e) {
                Log::warning('Could not resolve PaymentIntent for invoice', [
                    'invoice_id' => $invoice->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $payment = Payment::updateOrCreate(
            ['stripe_invoice_id' => $invoice->id],
            array_filter([
                'user_id' => $subscription->user_id,
                'subscription_id' => $subscription->id,
                'stripe_payment_intent_id' => $paymentIntentId,
                'amount' => $invoice->amount_paid,
                'currency' => $invoice->currency,
                'status' => 'succeeded',
                'paid_at' => now(),
            ], fn ($value) => $value !== null),
        );

        if ($isFirstPayment) {
            app(PushNotificationService::class)->sendToUser(
                $subscription->user,
                'Subscription purchase successful',
                'Your plan was purchased successfully.',
                ['type' => 'subscription_purchased', 'subscription_id' => (string) $subscription->id],
            );
        }

        $this->sendInvoiceMail($subscription, $payment);
    }

    /**
     * Creates the local subscription for a paid plan from the metadata
     * subscribe() attached to the Stripe subscription, now that its first
     * invoice is paid. Returns the existing row on a repeated delivery.
     */
    private function activateFromStripe(string $stripeSubscriptionId): ?Subscription
    {
        $stripeSubscription = app(StripeService::class)->retrieveSubscription($stripeSubscriptionId);
        $metadata = $stripeSubscription->metadata;

        $user = User::find($metadata['user_id'] ?? null);
        // withTrashed: if the plan was deleted while this checkout was still
        // being paid, the user still gets the plan they paid for.
        $plan = SubscriptionPlan::withTrashed()->find($metadata['plan_id'] ?? null);

        if (! $user || ! $plan) {
            Log::error('Paid Stripe subscription has no matching user/plan', [
                'stripe_subscription_id' => $stripeSubscriptionId,
                'user_id' => $metadata['user_id'] ?? null,
                'plan_id' => $metadata['plan_id'] ?? null,
            ]);

            return null;
        }

        $billingCycle = ($metadata['billing_cycle'] ?? null) === 'yearly' ? 'yearly' : 'monthly';

        return app(SubscriptionService::class)->activate($user, $plan, [
            'price' => $metadata['price'] ?? ($billingCycle === 'yearly' ? $plan->yearly_price : $plan->monthly_price),
            'billing_cycle' => $billingCycle,
            'status' => 'active',
            'renews_at' => $billingCycle === 'yearly' ? now()->addYear() : now()->addMonth(),
            'stripe_customer_id' => $stripeSubscription->customer,
            'stripe_subscription_id' => $stripeSubscription->id,
        ], Coupon::find($metadata['coupon_id'] ?? null), (float) ($metadata['coupon_discount'] ?? 0));
    }

    /**
     * Mirrors a non-active status onto the user only when this subscription
     * is the one they're on — events for a replaced or abandoned subscription
     * must not knock the user off the plan that replaced it.
     */
    private function syncUserStatus(Subscription $subscription, string $status): void
    {
        $hasOtherLivePlan = Subscription::where('user_id', $subscription->user_id)
            ->whereKeyNot($subscription->getKey())
            ->whereIn('status', ['trial', 'active'])
            ->exists();

        if (! $hasOtherLivePlan) {
            $subscription->user()->update(['subscription_status' => $status]);
        }
    }

    /**
     * Builds the flat set of fields SubscriptionInvoiceMail needs from the
     * subscription/payment models and queues it — shared by the first-purchase
     * (payment_intent.succeeded) and renewal (invoice.paid) paths.
     */
    private function sendInvoiceMail(Subscription $subscription, Payment $payment): void
    {
        $user = $subscription->user;

        if (empty($user?->email)) {
            return;
        }

        Mail::to($user->email)->queue(new SubscriptionInvoiceMail(
            userName: $user->name,
            planName: $subscription->plan?->name ?? '-',
            billingCycle: $subscription->billing_cycle,
            amount: ($payment->amount ?? 0) / 100,
            currency: $payment->currency ?? 'usd',
            status: $subscription->status,
            paymentDate: ($payment->paid_at ?? now())->format('d M Y'),
            nextBillingDate: optional($subscription->renews_at)->format('d M Y'),
            transactionId: $payment->stripe_invoice_id ?? $payment->stripe_payment_intent_id,
        ));
    }

    private function handlePaymentFailed($event): void
    {
        $invoice = $event->data->object;
        $subscription = Subscription::where('stripe_subscription_id', $this->invoiceSubscriptionId($invoice))->first();

        if (! $subscription) {
            return;
        }

        $subscription->update(['status' => 'expired']);
        $this->syncUserStatus($subscription, 'expired');

        Payment::updateOrCreate(
            ['stripe_invoice_id' => $invoice->id],
            array_filter([
                'user_id' => $subscription->user_id,
                'subscription_id' => $subscription->id,
                'stripe_payment_intent_id' => $invoice->payment_intent ?? null,
                'amount' => $invoice->amount_due,
                'currency' => $invoice->currency,
                'status' => 'failed',
            ], fn ($value) => $value !== null),
        );
    }

    /**
     * charge.refunded fires for every refund on a charge, full or partial,
     * and carries the running total (amount_refunded). Values are set from
     * that total, never added to, so a repeated delivery changes nothing.
     *
     * Scenarios:
     * 1. Partial refund: payment -> partially_refunded. Access unchanged;
     *    only the refunded part leaves revenue.
     * 2. Full refund of the payment for the CURRENT period of a running
     *    plan: payment -> refunded, plan ends now (cancelled), Stripe
     *    subscription cancelled so it does not renew. Access stops.
     * 3. Full refund of an OLDER payment (a past period, or a plan that was
     *    already replaced / cancelled / expired): payment -> refunded only.
     *    The current plan is untouched.
     * 4. Charge not matching any payment row: logged and ignored.
     */
    private function handleChargeRefunded($event): void
    {
        $charge = $event->data->object;
        $payment = $this->paymentForCharge($charge);

        if (! $payment) {
            Log::warning('Refund received for an unknown charge', [
                'charge_id' => $charge->id ?? null,
                'payment_intent_id' => $charge->payment_intent ?? null,
            ]);

            return;
        }

        $refunded = min((int) ($charge->amount_refunded ?? 0), (int) $payment->amount);
        $isFullRefund = (bool) ($charge->refunded ?? false) || $refunded >= (int) $payment->amount;

        $payment->update(array_filter([
            'amount_refunded' => $refunded,
            'status' => $isFullRefund ? 'refunded' : 'partially_refunded',
            'refunded_at' => now(),
            // Backfill so the next lookup for this charge is a direct hit.
            'stripe_payment_intent_id' => $payment->stripe_payment_intent_id ?: ($charge->payment_intent ?? null),
        ], fn ($value) => $value !== null));

        Log::info('Payment refunded', [
            'payment_id' => $payment->id,
            'amount_refunded' => $refunded,
            'full' => $isFullRefund,
        ]);

        // Scenario 1: partial refund keeps the plan running.
        if (! $isFullRefund) {
            return;
        }

        $subscription = $payment->subscription;

        // Scenario 3: the plan this paid for is no longer running.
        if (! $subscription || ! in_array($subscription->status, ['trial', 'active'], true)) {
            return;
        }

        // Scenario 3: an older period was refunded; a later payment covers
        // the current one.
        $latestPayment = Payment::where('subscription_id', $subscription->id)
            ->whereIn('status', [...Payment::COLLECTED_STATUSES, 'refunded'])
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->first();

        if ($latestPayment && $latestPayment->id !== $payment->id) {
            return;
        }

        // Scenario 2: the current period was refunded in full.
        $subscription->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        $this->syncUserStatus($subscription, 'cancelled');

        if ($subscription->stripe_subscription_id) {
            try {
                // Its customer.subscription.deleted event then finds the row
                // already cancelled and does nothing.
                app(StripeService::class)->cancelSubscription($subscription->stripe_subscription_id);
            } catch (\Throwable $e) {
                Log::warning('Could not cancel Stripe subscription after full refund', [
                    'stripe_subscription_id' => $subscription->stripe_subscription_id,
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Finds the payments row a Stripe Charge belongs to: by PaymentIntent
     * first, then via the invoice that PaymentIntent paid (older rows were
     * stored without a PaymentIntent id).
     */
    private function paymentForCharge($charge): ?Payment
    {
        $paymentIntentId = $charge->payment_intent ?? null;

        if (! $paymentIntentId) {
            return null;
        }

        $paymentIntentId = is_string($paymentIntentId) ? $paymentIntentId : $paymentIntentId->id;

        $payment = Payment::where('stripe_payment_intent_id', $paymentIntentId)->first();

        if ($payment) {
            return $payment;
        }

        try {
            $invoiceId = app(StripeService::class)->invoiceIdForPaymentIntent($paymentIntentId);
        } catch (\Throwable $e) {
            Log::warning('Could not resolve invoice for refunded charge', [
                'payment_intent_id' => $paymentIntentId,
                'message' => $e->getMessage(),
            ]);

            return null;
        }

        return $invoiceId ? Payment::where('stripe_invoice_id', $invoiceId)->first() : null;
    }

    private function handleSubscriptionDeleted($event): void
    {
        $stripeSubscription = $event->data->object;
        $subscription = Subscription::where('stripe_subscription_id', $stripeSubscription->id)->first();

        // Already closed locally — e.g. replaced by an upgrade, which is what
        // cancelled it on Stripe in the first place.
        if (! $subscription || $subscription->status === 'cancelled') {
            return;
        }

        $subscription->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        $this->syncUserStatus($subscription, 'cancelled');
    }
}
