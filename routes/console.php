<?php

use App\Models\Payment;
use App\Models\Subscription;
use App\Services\StripeService;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Paid Stripe checkouts whose invoice.paid webhook never arrived (endpoint
// down, delivery failed): activates them, once, through the same code path.
Artisan::command('stripe:reconcile-subscriptions {--hours=48 : How far back to look}', function (StripeService $stripe, SubscriptionService $subscriptions) {
    $since = now()->subHours((int) $this->option('hours'));
    $activated = 0;
    $failed = 0;

    foreach ($stripe->recentActiveSubscriptions($since) as $stripeSubscription) {
        if (Subscription::where('stripe_subscription_id', $stripeSubscription->id)->exists()) {
            continue;
        }

        try {
            if ($subscriptions->reconcilePaidStripeSubscription($stripeSubscription)) {
                $activated++;
                $this->line("Activated {$stripeSubscription->id} for user {$stripeSubscription->metadata['user_id']}");
            }
        } catch (\Throwable $e) {
            $failed++;
            report($e);
            $this->error("{$stripeSubscription->id}: {$e->getMessage()}");
        }
    }

    // Prorated yearly upgrades: a paid upgrade whose webhook never arrived is
    // applied now; an unpaid one whose pending change Stripe has dropped
    // (not paid within ~23h) gets its invoice voided so it can never be
    // charged later for an upgrade the user doesn't have.
    $upgraded = 0;
    $voided = 0;

    foreach ($stripe->recentInvoices($since, 'paid') as $invoice) {
        $stripeSubscriptionId = $invoice->parent?->subscription_details?->subscription ?? null;

        if ($invoice->billing_reason !== 'subscription_update' || ! Subscription::forStripe($stripeSubscriptionId)
            || Payment::where('stripe_invoice_id', $invoice->id)->whereIn('status', [...Payment::COLLECTED_STATUSES, 'refunded'])->exists()) {
            continue;
        }

        try {
            $subscriptions->applyPaidInvoice($invoice);
            $upgraded++;
            $this->line("Recorded paid plan change {$invoice->id} ({$stripeSubscriptionId})");
        } catch (\Throwable $e) {
            $failed++;
            report($e);
            $this->error("{$invoice->id}: {$e->getMessage()}");
        }
    }

    foreach ($stripe->recentInvoices($since, 'open') as $invoice) {
        $stripeSubscriptionId = $invoice->parent?->subscription_details?->subscription ?? null;

        if ($invoice->billing_reason !== 'subscription_update' || ! Subscription::forStripe($stripeSubscriptionId)) {
            continue;
        }

        try {
            $stripeSubscription = $stripe->retrieveSubscription($stripeSubscriptionId);

            // Still payable (the user may be in 3DS right now), or a change
            // that was applied without waiting for payment: leave it.
            $charged = collect($invoice->lines->data ?? [])->first(fn ($line) => $line->amount > 0);
            $linePrice = $charged?->pricing?->price_details?->price ?? $charged?->price ?? null;
            $linePrice = is_string($linePrice) ? $linePrice : ($linePrice->id ?? null);

            if ($stripeSubscription->pending_update || ! $linePrice
                || $linePrice === ($stripeSubscription->items->data[0]->price->id ?? null)) {
                continue;
            }

            $stripe->voidInvoice($invoice->id);
            $voided++;
            $this->line("Voided expired upgrade invoice {$invoice->id} ({$stripeSubscriptionId})");
        } catch (\Throwable $e) {
            $failed++;
            report($e);
            $this->error("{$invoice->id}: {$e->getMessage()}");
        }
    }

    Log::info('Stripe subscriptions reconciled', [
        'since' => $since->toIso8601String(), 'activated' => $activated, 'upgraded' => $upgraded, 'voided' => $voided, 'failed' => $failed,
    ]);
    $this->info("Activated {$activated}, upgrades recorded {$upgraded}, expired upgrade invoices voided {$voided}, failed {$failed}.");

    return $failed ? 1 : 0;
})->purpose('Activate paid Stripe subscriptions that have no local record');

Schedule::command('stripe:reconcile-subscriptions')->everyFifteenMinutes()->withoutOverlapping();

// Flip expired free trials to active (and charge) once a day.
Schedule::command('subscriptions:process-trials')->daily();

// Backstop for admin price changes whose Stripe sync failed: every plan's
// Stripe Price matches the admin price and running subscribers renew on it.
Schedule::command('stripe:sync-plan-prices')->dailyAt('02:00')->withoutOverlapping();

// Auto-expire meetings that nobody accepted/rejected/completed within
// Meeting::EXPIRY_HOURS of their scheduled time.
Schedule::command('meetings:expire-stale')->everyFiveMinutes();
