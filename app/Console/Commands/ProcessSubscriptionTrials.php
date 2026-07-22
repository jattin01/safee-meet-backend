<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcessSubscriptionTrials extends Command
{
    protected $signature = 'subscriptions:process-trials';

    protected $description = 'Expire free-trial subscriptions whose trial period has ended';

    public function handle(): int
    {
        $due = Subscription::with('plan')
            ->where('status', 'trial')
            ->whereNotNull('renews_at')
            ->where('renews_at', '<=', now())
            ->get();

        if ($due->isEmpty()) {
            $this->info('No trials due for expiry.');
            return self::SUCCESS;
        }

        foreach ($due as $subscription) {
            // Trials are not auto-charged — they expire, which disables
            // search/chat until the user pays for a plan (see the subscribe
            // endpoint + PlanEntitlements::subscriptionActive).
            DB::transaction(function () use ($subscription) {
                $subscription->update(['status' => 'expired']);
                $subscription->user?->update(['subscription_status' => 'expired']);
            });

            $this->line("  Subscription #{$subscription->id} (user {$subscription->user_id}) → expired.");
        }

        $this->info($due->count().' trial(s) expired.');
        return self::SUCCESS;
    }
}
