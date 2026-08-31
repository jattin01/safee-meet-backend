<?php

namespace App\Services\Verification;

use App\Models\User;
use App\Models\UserVerification;
use App\Models\VerificationLevel;
use App\Support\Verification\TrustScoreCalculator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class UserVerificationLevelService
{
    /** @var array<string, array{rank: int, slug: string}> */
    private const LEVELS = [
        'level1' => ['rank' => 1, 'slug' => 'level_1_verified'],
        'level2' => ['rank' => 2, 'slug' => 'level_2_verified'],
        'professional' => ['rank' => 3, 'slug' => 'professional'],
    ];

    public function catalogLevel(string $level): VerificationLevel
    {
        $definition = $this->definition($level);

        return VerificationLevel::active()
            ->where('slug', $definition['slug'])
            ->first()
            ?? throw new RuntimeException("The active {$level} verification catalog record is missing.");
    }

    public function promote(
        User $user,
        string $targetLevel,
        ?UserVerification $verification = null,
        ?VerificationLevel $catalogLevel = null,
    ): User {
        $definition = $this->definition($targetLevel);

        return DB::transaction(function () use ($user, $targetLevel, $verification, $catalogLevel, $definition): User {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $currentRank = $this->rankOf($lockedUser->verification_level);
            $shouldPromoteUser = $definition['rank'] >= $currentRank;

            if ($shouldPromoteUser) {
                $catalogLevel ??= $this->catalogLevel($targetLevel);

                if ($catalogLevel->slug !== $definition['slug'] || ! $catalogLevel->is_active || $catalogLevel->trashed()) {
                    throw new RuntimeException("The supplied verification catalog record does not match {$targetLevel}.");
                }
            }

            if ($verification) {
                $lockedVerification = UserVerification::query()
                    ->whereKey($verification->getKey())
                    ->where('user_id', $lockedUser->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedVerification->verification_level < $definition['rank']) {
                    $lockedVerification->forceFill([
                        'verification_level' => $definition['rank'],
                    ])->save();
                }
            }

            // A delayed lower-level webhook must never replace a higher badge.
            if (! $shouldPromoteUser) {
                return $lockedUser;
            }

            $lockedUser->forceFill([
                'verification_level' => $targetLevel,
                'verification_level_id' => $catalogLevel->id,
                'trust_score' => TrustScoreCalculator::scoreFor($targetLevel),
            ])->save();

            return $lockedUser;
        });
    }

    /** @return array{rank: int, slug: string} */
    private function definition(string $level): array
    {
        return self::LEVELS[$level]
            ?? throw new InvalidArgumentException("Unsupported verification level: {$level}.");
    }

    private function rankOf(?string $level): int
    {
        return self::LEVELS[$level]['rank'] ?? 0;
    }
}
