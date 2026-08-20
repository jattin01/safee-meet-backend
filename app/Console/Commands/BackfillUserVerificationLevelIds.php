<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\VerificationLevel;
use Illuminate\Console\Command;

class BackfillUserVerificationLevelIds extends Command
{
    protected $signature = 'users:backfill-verification-level-ids';

    protected $description = 'Set users.verification_level_id from the existing verification_level string, so already-approved users pick up their catalog badge icon.';

    /**
     * Maps the legacy string values on users.verification_level to their
     * matching row in the verification_levels catalog.
     */
    private const SLUG_MAP = [
        'level1' => 'level_1_verified',
        'level2' => 'level_2_verified',
        'professional' => 'professional',
    ];

    public function handle(): int
    {
        $levelIdsBySlug = VerificationLevel::withTrashed()
            ->whereIn('slug', self::SLUG_MAP)
            ->pluck('id', 'slug');

        $updated = 0;

        foreach (self::SLUG_MAP as $stringValue => $slug) {
            $levelId = $levelIdsBySlug[$slug] ?? null;

            if (!$levelId) {
                $this->warn("No verification_levels row found for slug \"{$slug}\" — skipping \"{$stringValue}\".");
                continue;
            }

            $count = User::where('verification_level', $stringValue)
                ->whereNull('verification_level_id')
                ->update(['verification_level_id' => $levelId]);

            $this->info("verification_level=\"{$stringValue}\" -> verification_level_id={$levelId}: {$count} user(s) updated.");
            $updated += $count;
        }

        $this->info("Done. {$updated} user(s) backfilled in total.");

        return self::SUCCESS;
    }
}
