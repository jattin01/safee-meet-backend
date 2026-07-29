<?php

namespace App\Services;

use App\Models\SubscriptionPlan;
use App\Models\User;
use Stripe\Customer;
use Stripe\StripeClient;
use Stripe\Subscription as StripeSubscription;

class StripeService
{
    private StripeClient $client;

    public function __construct()
    {
        $this->client = new StripeClient(config('services.stripe.secret'));
    }

    /**
     * Reuses the user's existing Stripe customer, or creates one and stores
     * its id — every subscription/payment call needs this as the anchor.
     */
    public function createOrGetCustomer(User $user): Customer
    {
        $existingId = $user->activeSubscription?->stripe_customer_id;

        if ($existingId) {
            return $this->client->customers->retrieve($existingId);
        }

        return $this->client->customers->create([
            'email' => $user->email,
            'name' => $user->displayName ?? $user->safeeId,
            'metadata' => ['user_id' => $user->id],
        ]);
    }

    /**
     * Creates the recurring Stripe subscription for a paid (or trialing)
     * plan. $priceId must be the Stripe Price id configured against the
     * plan's slug (monthly_stripe_price_id / yearly_stripe_price_id).
     */
    public function createSubscription(
        Customer $customer,
        string $priceId,
        ?string $paymentMethodId,
        ?int $trialDays
    ): StripeSubscription {
        if ($paymentMethodId) {
            $this->client->paymentMethods->attach($paymentMethodId, ['customer' => $customer->id]);
            $this->client->customers->update($customer->id, [
                'invoice_settings' => ['default_payment_method' => $paymentMethodId],
            ]);
        }

        $params = [
            'customer' => $customer->id,
            'items' => [['price' => $priceId]],
            'payment_behavior' => 'default_incomplete',
            // Stripe's newer API versions moved the client secret needed for
            // 3DS/off-session confirmation off `latest_invoice.payment_intent`
            // (removed) onto `latest_invoice.confirmation_secret`.
            'expand' => ['latest_invoice.confirmation_secret'],
        ];

        if ($trialDays) {
            $params['trial_period_days'] = $trialDays;
        }

        return $this->client->subscriptions->create($params);
    }

    public function cancelSubscription(string $stripeSubscriptionId): StripeSubscription
    {
        return $this->client->subscriptions->cancel($stripeSubscriptionId);
    }

    public function resolvePriceId(SubscriptionPlan $plan, string $billingCycle): ?string
    {
        return $billingCycle === 'yearly' ? $plan->yearly_stripe_price_id : $plan->monthly_stripe_price_id;
    }

    public function verifyWebhookSignature(string $payload, string $signatureHeader): \Stripe\Event
    {
        return \Stripe\Webhook::constructEvent(
            $payload,
            $signatureHeader,
            config('services.stripe.webhook_secret')
        );
    }
}
