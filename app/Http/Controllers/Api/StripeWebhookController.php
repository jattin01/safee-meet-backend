<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use UnexpectedValueException;

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
        try {
            $event = app(\App\Services\StripeService::class)->verifyWebhookSignature(
                $request->getContent(),
                $request->header('Stripe-Signature', ''),
            );
        } catch (UnexpectedValueException|SignatureVerificationException $e) {
            Log::warning('Stripe webhook signature verification failed', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Invalid signature'], 400);
        }

        // Idempotency: Stripe retries undelivered webhooks, so a repeat event
        // id must be a no-op rather than double-crediting/cancelling.
        $alreadyProcessed = PaymentWebhookEvent::where('stripe_event_id', $event->id)
            ->whereNotNull('processed_at')
            ->exists();

        if ($alreadyProcessed) {
            return response()->json(['message' => 'Already processed']);
        }

        $record = PaymentWebhookEvent::firstOrCreate(
            ['stripe_event_id' => $event->id],
            ['type' => $event->type, 'payload' => $event->toArray()],
        );

        DB::transaction(function () use ($event) {
            match ($event->type) {
                'invoice.paid' => $this->handleInvoicePaid($event),
                'invoice.payment_failed' => $this->handlePaymentFailed($event),
                'customer.subscription.deleted' => $this->handleSubscriptionDeleted($event),
                default => null,
            };
        });

        $record->update(['processed_at' => now()]);

        return response()->json(['message' => 'ok']);
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
        $subscription = Subscription::where('stripe_subscription_id', $this->invoiceSubscriptionId($invoice))->first();

        if (! $subscription) {
            return;
        }

        $subscription->update([
            'status' => 'active',
            'renews_at' => now()->addMonth(),
        ]);
        $subscription->user()->update(['subscription_status' => 'active']);

        Payment::updateOrCreate(
            ['stripe_invoice_id' => $invoice->id],
            [
                'user_id' => $subscription->user_id,
                'subscription_id' => $subscription->id,
                'stripe_payment_intent_id' => $invoice->payment_intent,
                'amount' => $invoice->amount_paid,
                'currency' => $invoice->currency,
                'status' => 'succeeded',
                'paid_at' => now(),
            ],
        );
    }

    private function handlePaymentFailed($event): void
    {
        $invoice = $event->data->object;
        $subscription = Subscription::where('stripe_subscription_id', $this->invoiceSubscriptionId($invoice))->first();

        if (! $subscription) {
            return;
        }

        $subscription->update(['status' => 'expired']);
        $subscription->user()->update(['subscription_status' => 'expired']);

        Payment::updateOrCreate(
            ['stripe_invoice_id' => $invoice->id],
            [
                'user_id' => $subscription->user_id,
                'subscription_id' => $subscription->id,
                'stripe_payment_intent_id' => $invoice->payment_intent,
                'amount' => $invoice->amount_due,
                'currency' => $invoice->currency,
                'status' => 'failed',
            ],
        );
    }

    private function handleSubscriptionDeleted($event): void
    {
        $stripeSubscription = $event->data->object;
        $subscription = Subscription::where('stripe_subscription_id', $stripeSubscription->id)->first();

        if (! $subscription) {
            return;
        }

        $subscription->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        $subscription->user()->update(['subscription_status' => 'cancelled']);
    }
}
