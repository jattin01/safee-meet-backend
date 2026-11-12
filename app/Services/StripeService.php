<?php

namespace App\Services;

use App\Jobs\MovePlanSubscribersToCurrentPrice;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Customer;
use Stripe\Exception\InvalidRequestException;
use Stripe\Price;
use Stripe\StripeClient;
use Stripe\Subscription as StripeSubscription;

class StripeService
{
    /** Every plan is billed in this currency; the app shows prices in USD. */
    private const CURRENCY = 'usd';

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
        // The user row lock serialises concurrent purchases (double tap, two
        // devices), so only the first request can create the customer — the
        // second waits, then sees and reuses the stored id.
        $customer = DB::transaction(function () use ($user) {
            $locked = User::whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            // users.stripe_customer_id is the anchor now that a paid subscribe
            // writes nothing locally until payment succeeds. Older accounts
            // only have it on their subscription rows, so fall back to those.
            $existingId = $locked->stripe_customer_id
                ?? $locked->subscriptions()
                    ->whereNotNull('stripe_customer_id')
                    ->latest()
                    ->value('stripe_customer_id');

            $customer = ($existingId ? $this->reusableCustomer($existingId, $locked) : null)
                ?? $this->createCustomer($locked, $existingId);

            if ($locked->stripe_customer_id !== $customer->id) {
                $locked->forceFill(['stripe_customer_id' => $customer->id])->saveQuietly();
            }

            return $customer;
        });

        $user->forceFill(['stripe_customer_id' => $customer->id])->syncOriginalAttribute('stripe_customer_id');

        return $customer;
    }

    /**
     * The stored customer, unless it can no longer be used: deleted in the
     * Dashboard, missing (e.g. a test-mode id under live keys), or tagged as
     * another user's. Returning null makes the caller create a fresh one
     * instead of failing the purchase.
     */
    private function reusableCustomer(string $customerId, User $user): ?Customer
    {
        try {
            $customer = $this->client->customers->retrieve($customerId);
        } catch (InvalidRequestException $e) {
            if ($e->getStripeCode() !== 'resource_missing') {
                throw $e;
            }

            Log::warning('Stored Stripe customer not found; creating a new one.', [
                'user_id' => $user->id, 'stripe_customer_id' => $customerId,
            ]);

            return null;
        }

        if (isset($customer->deleted) && $customer->deleted) {
            Log::warning('Stored Stripe customer was deleted; creating a new one.', [
                'user_id' => $user->id, 'stripe_customer_id' => $customerId,
            ]);

            return null;
        }

        $owner = $customer->metadata['user_id'] ?? null;

        if ($owner !== null && (string) $owner !== (string) $user->id) {
            Log::error('Stored Stripe customer belongs to another user; creating a new one.', [
                'user_id' => $user->id, 'stripe_customer_id' => $customerId, 'customer_user_id' => $owner,
            ]);

            return null;
        }

        return $customer;
    }

    /**
     * The idempotency key covers a retry after a timeout (Stripe returns the
     * customer it already made). It includes the params and the rejected id,
     * so a changed email or a replacement for a deleted customer is not
     * answered with the old customer within Stripe's 24h key window.
     */
    private function createCustomer(User $user, ?string $replacingId): Customer
    {
        $params = array_filter([
            'email' => $user->email ?: null,
            'phone' => $user->phone ?: null,
            'name' => $user->name ?: $user->display_name ?: $user->safeeId,
            'metadata' => ['user_id' => (string) $user->id],
        ], fn ($value) => $value !== null && $value !== '');

        $key = 'customer-create-'.$user->id.'-'.substr(sha1(json_encode([$params, $replacingId])), 0, 16);

        return $this->client->customers->create($params, ['idempotency_key' => $key]);
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
            // Cards only (Apple Pay / Google Pay are cards too). Delayed
            // methods such as bank debits sit in "processing" for days, and
            // a retry would cancel a checkout whose money is still coming.
            'payment_settings' => ['payment_method_types' => ['card']],
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

    /**
     * The invoice a PaymentIntent paid. Current Stripe API versions no longer
     * put the invoice on the Charge/PaymentIntent; the link lives on the
     * InvoicePayment object instead.
     */
    public function invoiceIdForPaymentIntent(string $paymentIntentId): ?string
    {
        $payments = $this->client->invoicePayments->all([
            'payment' => ['type' => 'payment_intent', 'payment_intent' => $paymentIntentId],
            'limit' => 1,
        ]);

        $invoice = $payments->data[0]->invoice ?? null;

        return is_string($invoice) ? $invoice : $invoice?->id;
    }

    /**
     * The PaymentIntent that paid an invoice (the reverse of the above).
     * Stored on the payments row so a later refund can be matched to it.
     */
    public function paymentIntentIdForInvoice(string $invoiceId): ?string
    {
        $payments = $this->client->invoicePayments->all(['invoice' => $invoiceId, 'limit' => 10]);

        foreach ($payments->data as $invoicePayment) {
            if (($invoicePayment->status ?? null) !== 'paid' || ($invoicePayment->payment->type ?? null) !== 'payment_intent') {
                continue;
            }

            $paymentIntent = $invoicePayment->payment->payment_intent;

            return is_string($paymentIntent) ? $paymentIntent : $paymentIntent?->id;
        }

        return null;
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
    /**
     * A subscription of this user's that Stripe has already been paid for
     * but that has no local row yet: the user paid, and the invoice.paid
     * webhook has not arrived. Starting a new checkout then would charge
     * them twice. Comes with latest_invoice expanded.
     */
    public function paidUnrecordedSubscription(Customer $customer, User $user): ?StripeSubscription
    {
        $active = $this->client->subscriptions->all([
            'customer' => $customer->id,
            'status' => 'active',
            'limit' => 20,
            'expand' => ['data.latest_invoice'],
        ]);

        foreach ($active->data as $subscription) {
            if ((string) ($subscription->metadata['user_id'] ?? '') === (string) $user->id
                && ! \App\Models\Subscription::where('stripe_subscription_id', $subscription->id)->exists()) {
                return $subscription;
            }
        }

        return null;
    }

    /**
     * Active Stripe subscriptions created since $since, latest_invoice
     * expanded, for the reconcile command.
     *
     * @return iterable<StripeSubscription>
     */
    public function recentActiveSubscriptions(\DateTimeInterface $since): iterable
    {
        return $this->client->subscriptions->all([
            'status' => 'active',
            'created' => ['gte' => $since->getTimestamp()],
            'limit' => 100,
            'expand' => ['data.latest_invoice'],
        ])->autoPagingIterator();
    }

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
        $interval = $billingCycle === 'yearly' ? 'year' : 'month';

        if ($amount <= 0) {
            return; // free plan — never billed through Stripe
        }

        // The plan row lock serialises concurrent syncs (two purchases right
        // after a price change), so only one new Price is minted; the other
        // request waits, re-reads the id and finds it already matching.
        [$priceId, $replaced] = DB::transaction(function () use ($plan, $column, $amount, $interval, $billingCycle) {
            $locked = SubscriptionPlan::withTrashed()->whereKey($plan->getKey())->lockForUpdate()->firstOrFail();

            $old = $locked->{$column} ? $this->findPrice($locked->{$column}) : null;

            if ($old && $this->priceMatches($old, $amount, $interval)) {
                return [$old->id, false];
            }

            // Reused key: a retry after a timeout gets the Price Stripe
            // already created instead of a second one.
            $new = $this->client->prices->create([
                'product' => $old?->product ?? $this->resolveProductId($locked),
                'currency' => self::CURRENCY,
                'unit_amount' => $amount,
                'recurring' => ['interval' => $interval],
                'metadata' => ['plan_id' => $locked->id, 'billing_cycle' => $billingCycle],
            ], ['idempotency_key' => "price-{$locked->id}-{$billingCycle}-{$amount}-".($old?->id ?? 'none')]);

            $locked->forceFill([$column => $new->id])->saveQuietly();

            if ($old?->active) {
                $this->client->prices->update($old->id, ['active' => false]);
            }

            if ($old && ! $this->priceMatches($old, $old->unit_amount, $interval)) {
                Log::warning('Plan Stripe Price had the wrong interval or currency; replaced.', [
                    'plan_id' => $locked->id, 'billing_cycle' => $billingCycle, 'old_price_id' => $old->id,
                    'old_interval' => $old->recurring?->interval, 'old_currency' => $old->currency,
                ]);
            }

            // A brand-new plan has no subscribers to move.
            return [$new->id, $old !== null];
        });

        $plan->forceFill([$column => $priceId])->syncOriginalAttribute($column);

        // Every renewal bills the plan's current (discounted) price, so
        // existing subscribers follow the new Price from their next renewal.
        if ($replaced) {
            MovePlanSubscribersToCurrentPrice::dispatch($plan->id, $billingCycle);
        }
    }

    /**
     * The stored Price, or null when Stripe no longer has it (e.g. a
     * test-mode id under live keys), so the sync mints a fresh one instead
     * of failing every save and purchase on this plan.
     */
    private function findPrice(string $priceId): ?Price
    {
        try {
            return $this->client->prices->retrieve($priceId);
        } catch (InvalidRequestException $e) {
            if ($e->getStripeCode() !== 'resource_missing') {
                throw $e;
            }

            Log::warning('Stored Stripe Price not found; creating a new one.', ['price_id' => $priceId]);

            return null;
        }
    }

    /**
     * Keeps the Stripe Product name equal to the plan name, so invoices and
     * receipts show the plan's current name after a rename.
     */
    public function syncPlanProductName(SubscriptionPlan $plan): void
    {
        $priceId = $plan->monthly_stripe_price_id ?? $plan->yearly_stripe_price_id;
        $price = $priceId ? $this->findPrice($priceId) : null;

        if (! $price) {
            return;
        }

        $product = $this->client->products->retrieve(is_string($price->product) ? $price->product : $price->product->id);

        if ($product->name !== $plan->name) {
            $this->client->products->update($product->id, ['name' => $plan->name]);
        }
    }

    /**
     * Puts a running Stripe subscription on $priceId so its next renewal
     * bills the plan's current price. No proration: the period already paid
     * is untouched, nothing is charged or credited now. Returns whether the
     * subscription was changed.
     */
    public function moveSubscriptionToPrice(string $stripeSubscriptionId, string $priceId): bool
    {
        $subscription = $this->client->subscriptions->retrieve($stripeSubscriptionId);

        if (! in_array($subscription->status, ['active', 'trialing', 'past_due'], true)) {
            return false;
        }

        $item = $subscription->items->data[0] ?? null;

        if (! $item || $item->price->id === $priceId) {
            return false;
        }

        $this->client->subscriptions->update($stripeSubscriptionId, [
            'items' => [['id' => $item->id, 'price' => $priceId]],
            'proration_behavior' => 'none',
        ], ['idempotency_key' => "move-{$stripeSubscriptionId}-{$priceId}"]);

        return true;
    }

    /**
     * Whether a Price charges exactly what the app shows for this cycle:
     * active, same amount and currency, recurring every 1 month / 1 year.
     */
    private function priceMatches(Price $price, int $amount, string $interval): bool
    {
        return $price->active
            && $price->unit_amount === $amount
            && $price->currency === self::CURRENCY
            && $price->recurring?->interval === $interval
            && (int) ($price->recurring?->interval_count ?? 1) === 1;
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
            if ($price = $this->findPrice($priceId)) {
                return is_string($price->product) ? $price->product : $price->product->id;
            }
        }

        return $this->client->products->create([
            'name' => $plan->name,
            'metadata' => ['plan_id' => $plan->id],
        ], ['idempotency_key' => "product-{$plan->id}-".substr(sha1($plan->name), 0, 12)])->id;
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
