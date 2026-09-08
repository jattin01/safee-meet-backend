<?php

namespace App\Console\Commands;

use App\Models\Meeting;
use Illuminate\Console\Command;

class ExpireStaleMeetings extends Command
{
    protected $signature = 'meetings:expire-stale';

    protected $description = 'Auto-expire meetings whose scheduled time + '.Meeting::EXPIRY_HOURS.'h has passed without a host/guest action';

    public function handle(): int
    {
        $cutoff = now()->subHours(Meeting::EXPIRY_HOURS);

        $due = Meeting::whereIn('status', Meeting::EXPIRABLE_STATUSES)
            ->whereNotNull('scheduled_start_at')
            ->where('scheduled_start_at', '<=', $cutoff)
            ->get();

        if ($due->isEmpty()) {
            $this->info('No meetings due for expiry.');

            return self::SUCCESS;
        }

        foreach ($due as $meeting) {
            // Bypass the model's status accessor with a direct update so we
            // persist the real 'expired' value rather than re-deriving it.
            $meeting->newQuery()->whereKey($meeting->getKey())->update(['status' => 'expired']);

            $this->line("  Meeting {$meeting->reference} ({$meeting->getKey()}) → expired.");
        }

        $this->info($due->count().' meeting(s) expired.');

        return self::SUCCESS;
    }
}
