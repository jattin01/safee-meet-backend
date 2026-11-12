<?php

namespace App\Console\Commands;

use App\Jobs\SyncPlanStripePrices as SyncPlanStripePricesJob;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Console\Command;

/**
 * Safety net, run daily and once after deploy: every plan's Stripe Price
 * matches the admin-set (discounted) price and every running subscriber
 * renews on it. Safe to re-run.
 */
class SyncPlanStripePrices extends Command
{
    protected $signature = 'stripe:sync-plan-prices {--dry-run : Only list plans and their running subscribers}';

    protected $description = 'Sync plan prices to Stripe and move running subscribers to the current price';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $failed = 0;

        foreach (SubscriptionPlan::orderBy('sort_order')->get() as $plan) {
            foreach (['monthly', 'yearly'] as $cycle) {
                $amount = (float) ($cycle === 'yearly' ? $plan->yearly_price : $plan->monthly_price);
                $subscribers = Subscription::where('plan_id', $plan->id)->where('billing_cycle', $cycle)
                    ->where('status', 'active')->whereNotNull('stripe_subscription_id')->count();

                $this->line(sprintf('%-28s %-8s $%-9s %d running subscriber(s)', $plan->name, $cycle, number_format($amount, 2), $subscribers));
            }

            if ($dryRun) {
                continue;
            }

            try {
                SyncPlanStripePricesJob::dispatchSync($plan->id);
            } catch (\Throwable $e) {
                $failed++;
                $this->error("  {$plan->name}: {$e->getMessage()}");
                report($e);
            }
        }

        $this->info($dryRun ? 'Dry run: nothing changed.' : "Done ({$failed} plan(s) failed). See the log for moved counts.");

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
