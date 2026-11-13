<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AdminAuditLogger;
use App\Services\PushNotificationService;
use App\Services\StripeService;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

        case 'charge.refund.updated':
            $this->handleRefundUpdated($event);

            return response()->json(['message' => 'Webhook processed successfully'], 200);
    }

    // Everything else (payment_intent.*, payment_link.*, ...) is logged
    // only. Subscriptions are driven by the invoice events above; the old
    // payment_intent.* handlers re-sent purchase notifications after
    // invoice.paid and could flip a paid plan to incomplete/expired.
    Log::info('Stripe webhook event ignored', [
        'event_id' => $event->id,
        'event_type' => $eventType,
        'object_id' => $event->data->object->id ?? null,
    ]);

    return response()->json(['message' => 'Event ignored'], 200);
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
        // Shared with the subscribe() pre-check and the reconcile command, so
        // the payment is recorded once whichever of them sees it first.
        app(SubscriptionService::class)->applyPaidInvoice($event->data->object);
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

    private function handlePaymentFailed($event): void
    {
        $invoice = $event->data->object;

        // Events can arrive out of order: a failed attempt delivered after
        // the same invoice was paid (Stripe retried and succeeded) must not
        // undo the payment or end the plan.
        $alreadyCollected = Payment::where('stripe_invoice_id', $invoice->id)
            ->whereIn('status', [...Payment::COLLECTED_STATUSES, 'refunded'])
            ->exists();

        if ($alreadyCollected) {
            Log::info('invoice.payment_failed for an invoice already paid; ignoring', ['invoice_id' => $invoice->id]);

            return;
        }

        $stripeSubscriptionId = $this->invoiceSubscriptionId($invoice);
        $subscription = $stripeSubscriptionId
            ? Subscription::forStripe($stripeSubscriptionId)
            : null;

        if (! $subscription) {
            // A declined FIRST payment: nothing is stored locally before the
            // first invoice is paid, so record the failed attempt against
            // the user (no subscription, no access) for the admin reports.
            $this->recordFailedFirstPayment($invoice, $stripeSubscriptionId);

            return;
        }

        if (($invoice->billing_reason ?? null) === 'subscription_update') {
            // A prorated upgrade charge was declined or is waiting for 3DS.
            // Stripe keeps the subscription on the plan already paid for
            // until it is paid, so access stays exactly as it is.
            Payment::updateOrCreate(
                ['stripe_invoice_id' => $invoice->id],
                array_filter([
                    'user_id' => $subscription->user_id,
                    'subscription_id' => $subscription->id,
                    'amount' => $invoice->amount_due,
                    'currency' => $invoice->currency,
                    'status' => 'failed',
                ], fn ($value) => $value !== null),
            );
            Log::info('Upgrade payment failed; current plan unchanged', [
                'invoice_id' => $invoice->id, 'subscription_id' => $subscription->id,
            ]);

            return;
        }

        // Grace policy (decided): access stops at the first failed renewal
        // and comes back if a Stripe retry succeeds. A plan that already
        // ended or was replaced keeps its status.
        $wasRunning = in_array($subscription->status, ['trial', 'active'], true);

        if ($wasRunning) {
            $subscription->update(['status' => 'expired']);
            $this->syncUserStatus($subscription, 'expired');
        }

        // Stripe retries the same invoice several times: one row per
        // invoice, and the user is told on the first failure only.
        $firstFailure = ! Payment::where('stripe_invoice_id', $invoice->id)->exists();

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

        if ($wasRunning && $firstFailure && $subscription->user) {
            try {
                app(PushNotificationService::class)->sendToUser(
                    $subscription->user,
                    'Payment failed',
                    "We couldn't renew your {$subscription->plan?->name} plan, so your access is paused. "
                        .'We will retry the payment automatically and restore your plan once it goes through.',
                    ['type' => 'subscription_payment_failed', 'subscription_id' => (string) $subscription->id],
                );
            } catch (\Throwable $e) {
                Log::warning('Could not send payment-failed notification', [
                    'subscription_id' => $subscription->id, 'message' => $e->getMessage(),
                ]);
            }
        }
    }

    private function recordFailedFirstPayment($invoice, ?string $stripeSubscriptionId): void
    {
        if (! $stripeSubscriptionId) {
            return;
        }

        // The invoice carries a copy of the subscription's metadata; fall
        // back to fetching the subscription for older payloads.
        $userId = $invoice->parent?->subscription_details?->metadata?->user_id ?? null;

        if (! $userId) {
            try {
                $userId = app(StripeService::class)->retrieveSubscription($stripeSubscriptionId)->metadata['user_id'] ?? null;
            } catch (\Throwable $e) {
                Log::warning('Could not load Stripe subscription for a failed first payment', [
                    'stripe_subscription_id' => $stripeSubscriptionId, 'message' => $e->getMessage(),
                ]);
            }
        }

        if (! $userId || ! User::whereKey($userId)->exists()) {
            Log::warning('Failed payment for an unknown user', [
                'invoice_id' => $invoice->id, 'stripe_subscription_id' => $stripeSubscriptionId,
            ]);

            return;
        }

        // Keyed on the invoice: retries of the same checkout update one row,
        // and a later invoice.paid for it turns the row into the success.
        Payment::updateOrCreate(
            ['stripe_invoice_id' => $invoice->id],
            [
                'user_id' => $userId,
                'amount' => $invoice->amount_due,
                'currency' => $invoice->currency,
                'status' => 'failed',
            ],
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

        // Shared with the admin panel's refund action. The payload is a
        // snapshot from when the event was sent, and events can arrive out
        // of order, so read the charge as Stripe holds it now and record that
        // exact total. If Stripe can't be reached, fall back to the snapshot,
        // which may only raise the recorded total, never lower it.
        $paymentIntentId = $charge->payment_intent ?? null;
        $paymentIntentId = is_string($paymentIntentId) ? $paymentIntentId : ($paymentIntentId->id ?? null);
        $exact = false;

        try {
            $fresh = app(StripeService::class)->retrieveCharge($charge->id);

            if (is_object($fresh) && isset($fresh->amount_refunded)) {
                $charge = $fresh;
                $exact = true;
            }
        } catch (\Throwable $e) {
            Log::warning('Could not re-read refunded charge; using the event snapshot', [
                'charge_id' => $charge->id ?? null, 'message' => $e->getMessage(),
            ]);
        }

        $refunded = (int) ($charge->amount_refunded ?? 0);

        app(SubscriptionService::class)->applyRefund(
            $payment,
            $refunded,
            (bool) ($charge->refunded ?? false) || $refunded >= (int) $payment->amount,
            $paymentIntentId,
            $exact,
        );
    }

    /**
     * charge.refund.updated: a refund that was pending has failed or been
     * cancelled, so the money did not go back. Stripe has already lowered
     * the charge's refunded total; record that exact total. A plan that a
     * full refund had cancelled stays cancelled (its Stripe subscription is
     * gone) and is flagged for the team to follow up.
     */
    private function handleRefundUpdated($event): void
    {
        $refund = $event->data->object;

        if (! in_array($refund->status ?? null, ['failed', 'canceled'], true)) {
            return;
        }

        $payment = $this->paymentForCharge($refund);
        $chargeId = is_string($refund->charge ?? null) ? $refund->charge : ($refund->charge->id ?? null);

        if (! $payment || ! $chargeId) {
            Log::warning('Failed refund for an unknown payment', ['refund_id' => $refund->id ?? null]);

            return;
        }

        $before = $payment->only(['status', 'amount_refunded']);
        $charge = app(StripeService::class)->retrieveCharge($chargeId);

        if (! is_object($charge) || ! isset($charge->amount_refunded)) {
            // Without Stripe's current total nothing is lowered; Stripe
            // re-delivers the event, so a later attempt can apply it.
            throw new \RuntimeException("Could not read charge {$chargeId} for failed refund ".($refund->id ?? ''));
        }

        $refunded = (int) $charge->amount_refunded;

        app(SubscriptionService::class)->applyRefund($payment, $refunded, (bool) ($charge->refunded ?? false), null, true);

        $payment->refresh();
        app(AdminAuditLogger::class)->log('payment.refund_reversed', $payment, $before, [
            'status' => $payment->status,
            'amount_refunded' => $payment->amount_refunded,
            'stripe_refund_id' => $refund->id ?? null,
            'stripe_refund_status' => $refund->status,
            'failure_reason' => $refund->failure_reason ?? null,
        ]);

        Log::error('Stripe refund failed; the money was not returned', [
            'payment_id' => $payment->id, 'refund_id' => $refund->id ?? null, 'failure_reason' => $refund->failure_reason ?? null,
            'subscription_status' => $payment->subscription?->status,
        ]);
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
        $subscription = Subscription::forStripe($stripeSubscription->id);

        // Already closed locally — e.g. replaced by an upgrade, which is what
        // cancelled it on Stripe in the first place.
        if (! $subscription || $subscription->status === 'cancelled') {
            return;
        }

        $subscription->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        $this->syncUserStatus($subscription, 'cancelled');
    }
}
