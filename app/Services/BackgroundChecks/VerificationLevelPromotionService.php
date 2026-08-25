<?php

namespace App\Services\BackgroundChecks;

use App\Models\BackgroundCheck;
use App\Models\User;
use App\Models\VerificationLevel;
use App\Support\Verification\TrustScoreCalculator;
use RuntimeException;

class VerificationLevelPromotionService
{
    public function levelTwo(): VerificationLevel
    {
        return VerificationLevel::active()
            ->where('slug', 'level_2_verified')
            ->first() ?? throw new RuntimeException('The active Level 2 verification catalog record is missing.');
    }

    public function promoteAfterSuccessfulCompletion(BackgroundCheck $check, VerificationLevel $levelTwo): void
    {
        if (! $check->completed_at
            || $check->status === 'failed'
            || $check->result_classification === 'failed') {
            return;
        }

        $user = User::query()->lockForUpdate()->find($check->user_id);
        if (! $user || $user->verification_level === 'professional') {
            return;
        }

        $user->forceFill([
            'verification_level' => 'level2',
            'verification_level_id' => $levelTwo->id,
            'trust_score' => TrustScoreCalculator::scoreFor('level2'),
        ])->save();
    }
}
