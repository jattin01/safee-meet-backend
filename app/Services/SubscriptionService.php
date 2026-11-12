<?php

namespace App\Services;

use App\Mail\SubscriptionInvoiceMail;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Stripe\Subscription as StripeSubscription;

class SubscriptionService
{
    /**
     * Runs $callback while holding a named lock shared by every app server:
     * a MySQL GET_LOCK (released with the connection even if PHP dies), or a
     * cache lock on other databases (tests use SQLite). Throws
     * LockTimeoutException when another holder keeps it past $waitSeconds.
     */
    public function withLock(string $name, int $waitSeconds, Closure $callback): mixed
    {
        $name = 'safeemeet:'.$name;

        if (DB::getDriverName() !== 'mysql') {
            return Cache::lock($name, 120)->block($waitSeconds, $callback);
        }

        if ((int) (DB::selectOne('SELECT GET_LOCK(?, ?) AS acquired', [$name, $waitSeconds])->acquired ?? 0) !== 1) {
            throw new LockTimeoutException("Could not acquire lock {$name}");
        }

        try {
            return $callback();
        } finally {
            DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$name]);
        }
    }

    /**
     * Records a paid Stripe invoice exactly once: a renewal moves the plan
     * forward; a first payment creates the local subscription from the
     * metadata subscribe() attached. Shared by the invoice.paid webhook, the
     * subscribe() pre-check and the reconcile command, so whichever sees the
     * payment first does the work and the others find it done.
     *
     * Returns the subscription, or null when the invoice is not ours.
     */
    public function applyPaidInvoice($invoice): ?Subscription
    {
        $stripeSubscriptionId = $invoice->parent?->subscription_details?->subscription ?? $invoice->subscription ?? null;

        if (! $stripeSubscriptionId) {
            return null;
        }

        [$subscription, $payment, $isFirstPayment] = $this->withLock('invoice:'.$invoice->id, 20, function () use ($invoice, $stripeSubscriptionId) {
            // Stripe can deliver an event more than once. An invoice already
            // recorded as paid (or since refunded) is done: re-running it
            // would re-send the purchase mail and could reactivate a refunded
            // plan. A previously failed invoice that is now paid goes on.
            $recorded = Payment::where('stripe_invoice_id', $invoice->id)->first();

            if ($recorded && $recorded->status !== 'failed') {
                Log::info('Paid invoice already recorded; nothing to do', ['invoice_id' => $invoice->id]);

                return [Subscription::where('stripe_subscription_id', $stripeSubscriptionId)->first(), null, false];
            }

            $subscription = Subscription::where('stripe_subscription_id', $stripeSubscriptionId)->first();
            $isFirstPayment = false;

            if ($subscription && $this->isReplaced($subscription)) {
                // Money for a plan the user has since left (cancelled, or
                // expired and replaced by another plan). Record the payment,
                // never reactivate it, stop it billing, and flag for refund.
                Log::error('Paid invoice for a replaced subscription; not reactivated, review for refund', [
                    'subscription_id' => $subscription->id, 'invoice_id' => $invoice->id, 'amount' => $invoice->amount_paid,
                ]);
                $this->cancelOnStripe($stripeSubscriptionId);
            } elseif ($subscription) {
                // Renewal, or a Stripe retry succeeding after a failed renewal.
                $subscription->update([
                    'status' => 'active',
                    'renews_at' => $this->invoicePeriodEnd($invoice)
                        ?? ($subscription->billing_cycle === 'yearly' ? now()->addYear() : now()->addMonth()),
                ]);
                $subscription->user()->update(['subscription_status' => 'active']);
            } else {
                // First payment of a subscribe() checkout: nothing exists
                // locally until now.
                $stripeSubscription = app(StripeService::class)->retrieveSubscription($stripeSubscriptionId);
                $subscription = $this->activateFromStripe($stripeSubscription, $this->invoicePeriodEnd($invoice));

                if (! $subscription) {
                    return [null, null, false];
                }

                $isFirstPayment = $subscription->wasRecentlyCreated;
            }

            // Newer Stripe API versions no longer put payment_intent on the
            // invoice; it is looked up via the invoice's payments and needed
            // later to match a refund. Only set when found.
            $paymentIntentId = $invoice->payment_intent ?? null;

            if (! $paymentIntentId) {
                try {
                    $paymentIntentId = app(StripeService::class)->paymentIntentIdForInvoice($invoice->id);
                } catch (\Throwable $e) {
                    Log::warning('Could not resolve PaymentIntent for invoice', [
                        'invoice_id' => $invoice->id, 'message' => $e->getMessage(),
                    ]);
                }
            }

            $payment = Payment::updateOrCreate(
                ['stripe_invoice_id' => $invoice->id],
                array_filter([
                    'user_id' => $subscription->user_id,
                    'subscription_id' => $subscription->id,
                    'stripe_payment_intent_id' => is_string($paymentIntentId) ? $paymentIntentId : ($paymentIntentId->id ?? null),
                    'amount' => $invoice->amount_paid,
                    'currency' => $invoice->currency,
                    'status' => 'succeeded',
                    'paid_at' => now(),
                ], fn ($value) => $value !== null),
            );

            return [$subscription, $payment, $isFirstPayment];
        });

        // Notify only the caller that recorded the payment, after the lock.
        if ($payment) {
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

        return $subscription;
    }

    /**
     * Creates the local subscription for a paid plan from the metadata
     * subscribe() attached to the Stripe subscription. Returns the existing
     * row when it was already created.
     */
    public function activateFromStripe(StripeSubscription $stripeSubscription, ?CarbonInterface $periodEnd = null): ?Subscription
    {
        $metadata = $stripeSubscription->metadata;

        $user = User::find($metadata['user_id'] ?? null);
        // withTrashed: if the plan was deleted while this checkout was still
        // being paid, the user still gets the plan they paid for.
        $plan = SubscriptionPlan::withTrashed()->find($metadata['plan_id'] ?? null);

        if (! $user || ! $plan) {
            Log::error('Paid Stripe subscription has no matching user/plan', [
                'stripe_subscription_id' => $stripeSubscription->id,
                'user_id' => $metadata['user_id'] ?? null,
                'plan_id' => $metadata['plan_id'] ?? null,
            ]);

            return null;
        }

        $billingCycle = ($metadata['billing_cycle'] ?? null) === 'yearly' ? 'yearly' : 'monthly';
        $currentPeriodEnd = $stripeSubscription->items->data[0]->current_period_end
            ?? $stripeSubscription->current_period_end
            ?? null;

        return $this->activate($user, $plan, [
            'price' => $metadata['price'] ?? ($billingCycle === 'yearly' ? $plan->yearly_price : $plan->monthly_price),
            'billing_cycle' => $billingCycle,
            'status' => 'active',
            'renews_at' => $periodEnd
                ?? ($currentPeriodEnd ? Carbon::createFromTimestamp($currentPeriodEnd) : null)
                ?? ($billingCycle === 'yearly' ? now()->addYear() : now()->addMonth()),
            'stripe_customer_id' => is_string($stripeSubscription->customer) ? $stripeSubscription->customer : $stripeSubscription->customer->id,
            'stripe_subscription_id' => $stripeSubscription->id,
        ], Coupon::find($metadata['coupon_id'] ?? null), (float) ($metadata['coupon_discount'] ?? 0));
    }

    /**
     * A paid Stripe subscription (listed with latest_invoice expanded) that
     * has no local row yet, e.g. its invoice.paid webhook is late or failed:
     * records it now. Returns the local subscription, or null when it is not
     * a paid checkout of ours.
     */
    public function reconcilePaidStripeSubscription(StripeSubscription $stripeSubscription): ?Subscription
    {
        $invoice = $stripeSubscription->latest_invoice;

        if ($stripeSubscription->status !== 'active' || ! is_object($invoice) || $invoice->status !== 'paid'
            || empty($stripeSubscription->metadata['user_id'])) {
            return null;
        }

        return $this->applyPaidInvoice($invoice);
    }

    /**
     * Applies a refund to a payment row from Stripe's running refunded total
     * (set, never added, so repeats change nothing). Used by the
     * charge.refunded webhook and the admin refund action.
     *
     * Access policy:
     * 1. Partial refund: payment -> partially_refunded, plan keeps running.
     * 2. Full refund of the payment for the CURRENT period of a running
     *    plan: payment -> refunded, plan cancelled now, Stripe subscription
     *    cancelled so it does not renew.
     * 3. Full refund of an OLDER payment (past period, or a plan already
     *    ended or replaced): payment -> refunded only.
     */
    public function applyRefund(Payment $payment, int $refundedTotal, bool $full, ?string $paymentIntentId = null): void
    {
        $refunded = min($refundedTotal, (int) $payment->amount);
        $full = $full || $refunded >= (int) $payment->amount;

        $payment->update(array_filter([
            'amount_refunded' => $refunded,
            'status' => $full ? 'refunded' : 'partially_refunded',
            'refunded_at' => $payment->refunded_at ?? now(),
            // Backfill so the next lookup for this charge is a direct hit.
            'stripe_payment_intent_id' => $payment->stripe_payment_intent_id ?: $paymentIntentId,
        ], fn ($value) => $value !== null));

        Log::info('Payment refunded', ['payment_id' => $payment->id, 'amount_refunded' => $refunded, 'full' => $full]);

        if (! $full) {
            return; // Scenario 1
        }

        $subscription = $payment->subscription;

        if (! $subscription || ! in_array($subscription->status, ['trial', 'active'], true)) {
            return; // Scenario 3: that plan is no longer running
        }

        $latestPayment = Payment::where('subscription_id', $subscription->id)
            ->whereIn('status', [...Payment::COLLECTED_STATUSES, 'refunded'])
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->first();

        if ($latestPayment && $latestPayment->id !== $payment->id) {
            return; // Scenario 3: a later payment covers the current period
        }

        // Scenario 2: the current period was refunded in full.
        $subscription->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        if (! Subscription::where('user_id', $subscription->user_id)->whereKeyNot($subscription->getKey())
            ->whereIn('status', ['trial', 'active'])->exists()) {
            $subscription->user()->update(['subscription_status' => 'cancelled']);
        }

        if ($subscription->stripe_subscription_id) {
            // Its customer.subscription.deleted event then finds the row
            // already cancelled and does nothing.
            $this->cancelOnStripe($subscription->stripe_subscription_id);
        }
    }

    /**
     * Admin refund (STR-027): refunds $amountCents of a collected payment on
     * Stripe and applies it locally right away; the charge.refunded webhook
     * that follows finds it already applied. Returns the Stripe Refund.
     *
     * @throws \InvalidArgumentException when the payment cannot be refunded
     */
    public function refundPayment(Payment $payment, int $amountCents, string $stripeReason, array $metadata = []): \Stripe\Refund
    {
        return $this->withLock('refund:payment:'.$payment->id, 15, function () use ($payment, $amountCents, $stripeReason, $metadata) {
            $payment->refresh();

            if (! in_array($payment->status, Payment::COLLECTED_STATUSES, true)) {
                throw new \InvalidArgumentException('Only a paid payment can be refunded.');
            }

            $remaining = (int) $payment->amount - (int) $payment->amount_refunded;

            if ($amountCents <= 0 || $amountCents > $remaining) {
                throw new \InvalidArgumentException('The refund amount must be between 0.01 and '.number_format($remaining / 100, 2).'.');
            }

            $stripe = app(StripeService::class);
            $paymentIntentId = $payment->stripe_payment_intent_id
                ?: ($payment->stripe_invoice_id ? $stripe->paymentIntentIdForInvoice($payment->stripe_invoice_id) : null);

            if (! $paymentIntentId) {
                throw new \InvalidArgumentException('This payment has no Stripe charge to refund.');
            }

            $refund = $stripe->refundPaymentIntent($paymentIntentId, $amountCents, $stripeReason, [
                'payment_id' => (string) $payment->id, ...$metadata,
            ], 'refund-'.$payment->id.'-'.(int) $payment->amount_refunded.'-'.$amountCents);

            $this->applyRefund($payment, (int) $payment->amount_refunded + (int) $refund->amount, false, $paymentIntentId);

            return $refund;
        });
    }

    /**
     * A subscription the user has moved off: cancelled, or expired while
     * another plan of theirs is running. A late payment must not revive it.
     */
    private function isReplaced(Subscription $subscription): bool
    {
        if ($subscription->status === 'cancelled') {
            return true;
        }

        return $subscription->status === 'expired'
            && Subscription::where('user_id', $subscription->user_id)
                ->whereKeyNot($subscription->getKey())
                ->whereIn('status', ['trial', 'active'])
                ->exists();
    }

    private function cancelOnStripe(string $stripeSubscriptionId): void
    {
        try {
            app(StripeService::class)->cancelSubscription($stripeSubscriptionId);
        } catch (\Throwable $e) {
            // Already cancelled or expired on Stripe.
        }
    }

    /**
     * Builds the flat set of fields SubscriptionInvoiceMail needs and queues it.
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

    /**
     * When the period an invoice paid for ends, per Stripe: the latest
     * line-item period end. Null if the payload has no lines.
     */
    private function invoicePeriodEnd($invoice): ?CarbonInterface
    {
        $ends = collect($invoice->lines->data ?? [])
            ->map(fn ($line) => $line->period->end ?? null)
            ->filter();

        return $ends->isEmpty() ? null : Carbon::createFromTimestamp($ends->max());
    }

    /**
     * Puts the user on $plan: closes whatever they were on before, creates the
     * subscription + its frozen feature snapshot, records the coupon, and
     * points the user at the new plan. Called by subscribe() for trial/free
     * plans, and by the invoice.paid webhook for paid plans only once the
     * first payment has cleared — so backing out of the payment sheet leaves
     * the user's current plan untouched and writes nothing locally.
     *
     * Idempotent per stripe_subscription_id: a repeated webhook delivery gets
     * the existing row back (wasRecentlyCreated = false).
     */
    public function activate(
        User $user,
        SubscriptionPlan $plan,
        array $attributes,
        ?Coupon $coupon = null,
        float $couponDiscount = 0.0,
    ): Subscription {
        $replaced = collect();

        $subscription = DB::transaction(function () use ($user, $plan, $attributes, $coupon, $couponDiscount, &$replaced) {
            // Serializes concurrent webhook deliveries for the same user.
            User::whereKey($user->id)->lockForUpdate()->first();

            $stripeSubscriptionId = $attributes['stripe_subscription_id'] ?? null;

            if ($stripeSubscriptionId
                && $existing = Subscription::where('stripe_subscription_id', $stripeSubscriptionId)->first()) {
                return $existing;
            }

            // The old records remain as history; the model's updated hook
            // cancels their user_subscriptions snapshots too.
            $replaced = $user->subscriptions()->whereIn('status', ['trial', 'active', 'incomplete'])->get();

            foreach ($replaced as $old) {
                $old->update(['status' => 'cancelled', 'cancelled_at' => now()]);
            }

            $subscription = Subscription::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'trial_days' => $plan->trial_days,
                'started_at' => now(),
                ...$attributes,
            ]);

            if ($coupon) {
                CouponRedemption::create([
                    'coupon_id' => $coupon->id,
                    'user_id' => $user->id,
                    'subscription_id' => $subscription->id,
                    'discount_amount' => $couponDiscount,
                    'redeemed_at' => now(),
                ]);

                $coupon->increment('times_redeemed');
            }

            // Freeze the plan's current matrix; later admin edits affect new
            // purchases only.
            UserSubscription::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'subscription_id' => $subscription->subscription_id,
                'price' => $subscription->price,
                'billing_cycle' => $subscription->billing_cycle,
                'status' => $subscription->status,
                'trial_days' => $subscription->trial_days,
                'started_at' => $subscription->started_at,
                'renews_at' => $subscription->renews_at,
                'cancelled_at' => null,
                'stripe_customer_id' => $subscription->stripe_customer_id,
                'stripe_subscription_id' => $subscription->stripe_subscription_id,
                ...app(PlanEntitlements::class)->snapshotFor($plan),
            ]);

            $user->update([
                'plan_id' => $plan->id,
                'subscription_status' => $subscription->status,
            ]);

            return $subscription;
        });

        // Stop billing the replaced plans only after the new one is committed.
        foreach ($replaced as $old) {
            if ($old->stripe_subscription_id && $old->stripe_subscription_id !== $subscription->stripe_subscription_id) {
                try {
                    app(StripeService::class)->cancelSubscription($old->stripe_subscription_id);
                } catch (\Throwable $e) {
                    // Already cancelled or expired on Stripe.
                }
            }
        }

        // Older plans whose renewal failed are 'expired' locally but still
        // past_due on Stripe, which keeps retrying them: a retry that
        // succeeds later would charge for a plan the user has left. Close
        // every other live Stripe subscription of this customer.
        $customerId = $subscription->stripe_customer_id ?? $user->stripe_customer_id;

        if ($subscription->wasRecentlyCreated && $customerId) {
            try {
                app(StripeService::class)->cancelOtherLiveSubscriptions($customerId, $subscription->stripe_subscription_id);
            } catch (\Throwable $e) {
                Log::warning('Could not close older Stripe subscriptions after activation', [
                    'user_id' => $user->id, 'message' => $e->getMessage(),
                ]);
            }
        }

        return $subscription;
    }
}
