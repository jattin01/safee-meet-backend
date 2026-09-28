<?php

namespace App\Console\Commands;

use App\Models\Payment;
use Illuminate\Console\Command;
use Stripe\StripeClient;
use Throwable;

class SyncPaymentAmountsFromStripe extends Command
{
    protected $signature = 'payments:sync-stripe-amounts {--dry-run : Report what would change without saving}';

    protected $description = 'Corrects each payment\'s amount/currency to what Stripe actually charged on its payment intent';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $stripe = new StripeClient(config('services.stripe.secret'));

        $payments = Payment::whereNotNull('stripe_payment_intent_id')->get();

        $this->info("Checking {$payments->count()} payment(s) against Stripe.");

        $updated = 0;
        $failed = 0;

        foreach ($payments as $payment) {
            try {
                $intent = $stripe->paymentIntents->retrieve($payment->stripe_payment_intent_id);
            } catch (Throwable $e) {
                $failed++;
                $this->warn("  [SKIP] payment {$payment->id}: {$e->getMessage()}");
                continue;
            }

            // A succeeded payment is worth what was received; anything else
            // is worth what Stripe asked for.
            $amount = $intent->status === 'succeeded' ? $intent->amount_received : $intent->amount;

            if ((int) $payment->amount === (int) $amount && $payment->currency === $intent->currency) {
                continue;
            }

            $this->line("  payment {$payment->id}: {$payment->amount} {$payment->currency} -> {$amount} {$intent->currency}"
                . ($dryRun ? ' (dry run — not saved)' : ''));

            if (! $dryRun) {
                $payment->update(['amount' => $amount, 'currency' => $intent->currency]);
            }
            $updated++;
        }

        $this->info(($dryRun ? 'Would update' : 'Updated') . " {$updated} payment(s); {$failed} skipped.");

        return self::SUCCESS;
    }
}
