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
        // users.stripe_customer_id is the anchor now that a paid subscribe
        // writes nothing locally until payment succeeds. Older accounts only
        // have it on their subscription rows, so fall back to those once.
        $existingId = $user->stripe_customer_id
            ?? $user->subscriptions()
                ->whereNotNull('stripe_customer_id')
                ->latest()
                ->value('stripe_customer_id');

        $customer = $existingId
            ? $this->client->customers->retrieve($existingId)
            : $this->client->customers->create([
                'email' => $user->email,
                'name' => $user->displayName ?? $user->safeeId,
                'metadata' => ['user_id' => $user->id],
            ]);

        if ($user->stripe_customer_id !== $customer->id) {
            $user->forceFill(['stripe_customer_id' => $customer->id])->saveQuietly();
        }

        return $customer;
    }

    /**
     * Creates the recurring Stripe subscription for a paid plan. $metadata is
     * everything the invoice.paid webhook needs to create the local records
     * once the first payment succeeds — nothing is stored locally before that.
     * $discountCents is a one-off (first invoice only) coupon discount.
     */
    public function createSubscription(
        Customer $customer,
        string $priceId,
        ?string $paymentMethodId,
        array $metadata = [],
        int $discountCents = 0,
    ): StripeSubscription {
        if ($paymentMethodId) {
            // Stripe test tokens (e.g. pm_card_visa) alias to a different,
            // real PaymentMethod id once attached — always use the id Stripe
            // hands back, not the id we were given.
            $attached = $this->client->paymentMethods->attach($paymentMethodId, ['customer' => $customer->id]);
            $this->client->customers->update($customer->id, [
                'invoice_settings' => ['default_payment_method' => $attached->id],
            ]);
        }

        $params = [
            'customer' => $customer->id,
            'items' => [['price' => $priceId]],
            'payment_behavior' => 'default_incomplete',
            'metadata' => array_filter($metadata, fn ($value) => $value !== null),
            // Stripe's newer API versions moved the client secret needed for
            // 3DS/off-session confirmation off `latest_invoice.payment_intent`
            // (removed) onto `latest_invoice.confirmation_secret`.
            'expand' => ['latest_invoice.confirmation_secret'],
        ];

        if ($discountCents > 0) {
            $price = $this->client->prices->retrieve($priceId);
            $coupon = $this->client->coupons->create([
                'amount_off' => $discountCents,
                'currency' => $price->currency,
                'duration' => 'once',
                'max_redemptions' => 1,
            ]);
            $params['discounts'] = [['coupon' => $coupon->id]];
        }

        return $this->client->subscriptions->create($params);
    }

    public function retrieveSubscription(string $stripeSubscriptionId): StripeSubscription
    {
        return $this->client->subscriptions->retrieve($stripeSubscriptionId);
    }

    public function cancelSubscription(string $stripeSubscriptionId): StripeSubscription
    {
        return $this->client->subscriptions->cancel($stripeSubscriptionId);
    }

    /**
     * Cancels the customer's still-unpaid subscriptions from earlier subscribe
     * attempts the user backed out of, so a retry never leaves two payable
     * subscriptions open at once.
     */
    public function cancelIncompleteSubscriptions(Customer $customer): void
    {
        $pending = $this->client->subscriptions->all([
            'customer' => $customer->id,
            'status' => 'incomplete',
        ]);

        foreach ($pending->autoPagingIterator() as $subscription) {
            $this->cancelSubscription($subscription->id);
        }
    }

    public function resolvePriceId(SubscriptionPlan $plan, string $billingCycle): ?string
    {
        return $billingCycle === 'yearly' ? $plan->yearly_stripe_price_id : $plan->monthly_stripe_price_id;
    }

    /**
     * Makes the plan's Stripe Price for $billingCycle charge exactly the
     * admin-set (discounted) monthly_price / yearly_price. Stripe Price
     * amounts are immutable, so a changed amount mints a new Price on the
     * same Product, points the plan at it and archives the old one. A plan
     * with no Price yet (newly added in admin) gets its Product created here.
     */
    public function syncPlanPrice(SubscriptionPlan $plan, string $billingCycle): void
    {
        $column = $billingCycle === 'yearly' ? 'yearly_stripe_price_id' : 'monthly_stripe_price_id';
        $amount = (int) round((float) ($billingCycle === 'yearly' ? $plan->yearly_price : $plan->monthly_price) * 100);

        if ($amount <= 0) {
            return; // free plan — never billed through Stripe
        }

        $old = $plan->{$column} ? $this->client->prices->retrieve($plan->{$column}) : null;

        if ($old && $old->active && $old->unit_amount === $amount) {
            return;
        }

        $new = $this->client->prices->create([
            'product' => $old?->product ?? $this->resolveProductId($plan),
            'currency' => $old?->currency ?? 'usd',
            'unit_amount' => $amount,
            'recurring' => ['interval' => $billingCycle === 'yearly' ? 'year' : 'month'],
            'metadata' => ['plan_id' => $plan->id, 'billing_cycle' => $billingCycle],
        ]);

        $plan->forceFill([$column => $new->id])->saveQuietly();

        if ($old?->active) {
            $this->client->prices->update($old->id, ['active' => false]);
        }
    }

    /**
     * Archives both of a plan's Prices so no new subscription can be started
     * on a deleted plan. Existing subscriptions on them keep running.
     */
    public function archivePlanPrices(SubscriptionPlan $plan): void
    {
        foreach (array_filter([$plan->monthly_stripe_price_id, $plan->yearly_stripe_price_id]) as $priceId) {
            $this->client->prices->update($priceId, ['active' => false]);
        }
    }

    /**
     * The plan's Stripe Product: reuse the one behind its other cycle's Price
     * so monthly and yearly stay under one Product, else create it.
     */
    private function resolveProductId(SubscriptionPlan $plan): string
    {
        foreach (array_filter([$plan->monthly_stripe_price_id, $plan->yearly_stripe_price_id]) as $priceId) {
            return $this->client->prices->retrieve($priceId)->product;
        }

        return $this->client->products->create([
            'name' => $plan->name,
            'metadata' => ['plan_id' => $plan->id],
        ])->id;
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
