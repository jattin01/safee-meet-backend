<?php

namespace App\Services\BackgroundChecks;

use App\Models\BackgroundCheck;
use App\Models\User;
use App\Models\VerificationLevel;
use App\Services\Verification\UserVerificationLevelService;

class VerificationLevelPromotionService
{
    public function __construct(
        private readonly UserVerificationLevelService $levelService,
    ) {}

    public function levelTwo(): VerificationLevel
    {
        return $this->levelService->catalogLevel('level2');
    }

    public function promoteAfterSuccessfulCompletion(BackgroundCheck $check, VerificationLevel $levelTwo): void
    {
        if (! $check->completed_at
            || $check->status === 'failed'
            || $check->result_classification === 'failed') {
            return;
        }

        $user = User::find($check->user_id);
        if (! $user) {
            return;
        }

        $this->levelService->promote(
            $user,
            'level2',
            catalogLevel: $levelTwo,
        );
    }
}
