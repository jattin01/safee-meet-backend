<?php

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

    Log::info('Stripe subscriptions reconciled', ['since' => $since->toIso8601String(), 'activated' => $activated, 'failed' => $failed]);
    $this->info("Activated {$activated}, failed {$failed}.");

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
