<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use App\Models\Subscription;
use App\Services\PushNotificationService;
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
            $payment->update([
                'status' => 'succeeded',
                'paid_at' => now(),
            ]);

            app(PushNotificationService::class)->sendToUser(
                $subscription->user,
                'Subscription purchase successful',
                'Your plan was purchased successfully.',
                ['type' => 'subscription_purchased', 'subscription_id' => (string) $subscription->id],
            );
            break;

        case 'payment_intent.payment_failed':
            Log::warning('Step 4 Subscription set to expired', ['payment_intent_id' => $paymentIntentId]);
            $subscription->update([
                'status' => 'expired',
            ]);
            $subscription->user()->update(['subscription_status' => 'expired']);
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
            $subscription->user()->update(['subscription_status' => 'cancelled']);
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
        $subscription = Subscription::where('stripe_subscription_id', $this->invoiceSubscriptionId($invoice))->first();

        if (! $subscription) {
            return;
        }

        $subscription->update([
            'status' => 'active',
            'renews_at' => $subscription->billing_cycle === 'yearly' ? now()->addYear() : now()->addMonth(),
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
