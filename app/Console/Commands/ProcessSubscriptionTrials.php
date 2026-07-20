<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcessSubscriptionTrials extends Command
{
    protected $signature = 'subscriptions:process-trials';

    protected $description = 'Convert expired free-trial subscriptions to active and trigger their first charge';

    public function handle(): int
    {
        $due = Subscription::with('plan')
            ->where('status', 'trial')
            ->whereNotNull('renews_at')
            ->where('renews_at', '<=', now())
            ->get();

        if ($due->isEmpty()) {
            $this->info('No trials due for conversion.');
            return self::SUCCESS;
        }

        foreach ($due as $subscription) {
            DB::transaction(function () use ($subscription) {
                // TODO: charge the first real payment via Stripe here
                // (amount = $subscription->price). If the charge fails, mark
                // the subscription 'expired' instead of 'active'.

                $subscription->update([
                    'status' => 'active',
                    'renews_at' => now()->addMonth(),
                ]);

                $subscription->user?->update(['subscription_status' => 'active']);
            });

            $this->line("  Subscription #{$subscription->id} (user {$subscription->user_id}) → active.");
        }

        $this->info($due->count().' trial(s) converted to active.');
        return self::SUCCESS;
    }
}
