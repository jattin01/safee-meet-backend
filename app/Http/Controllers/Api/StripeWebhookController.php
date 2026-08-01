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

    $subscriptionExists = Subscription::where(
        'stripe_payment_intent_id',
        $paymentIntentId
    )->exists();

    if (!$subscriptionExists) {
        Log::warning('Subscription not found', [
            'payment_intent_id' => $paymentIntentId,
        ]);

        return response()->json([
            'message' => 'Subscription not found',
        ], 200);
    }

    switch ($eventType) {
        case 'payment_intent.created':
            log::warning ('inside case 1 pending');
            // yha payment ko pending hi rakhna h 
            $subscription->update([
            'status' => 'pending',
            ]);
            break;

        case 'payment_intent.processing':
            // yha payment ko processing hi rakhna h
            log::warning ('inside case 2 processing');
            $subscription->update([
            'status' => 'processing',
            ]);
            break;

        case 'payment_intent.succeeded':
            // yha payment ko paid krna h 
            log::warning ('inside case 3 active');
            $subscription->update([
            'status' => 'active',
            'paid_at' => now(),
        ]);
        break;
        case 'payment_intent.payment_failed':
            // yha payment ko failed krna h
            log::warning ('inside case 4 faild');
            $subscription->update([
                'status' => 'failed',
            ]);
            break;

        case 'payment_intent.canceled':
            // yha payment ko failed krna h
            log::warning ('inside case 5 cancelled');
            $subscription->update([
                'status' => 'cancelled',
            ]);
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
