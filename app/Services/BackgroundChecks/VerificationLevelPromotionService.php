<?php

namespace App\Services\BackgroundChecks;

use App\Models\BackgroundCheck;
use App\Models\User;
use App\Models\VerificationLevel;
use App\Services\Verification\UserVerificationLevelService;
use Illuminate\Support\Facades\Log;

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
            Log::channel('background_check')->info('Promotion: skipped, check not successfully completed', [
                'background_check_id' => $check->id,
                'user_id' => $check->user_id,
                'status' => $check->status,
                'result_classification' => $check->result_classification,
            ]);

            return;
        }

        $user = User::find($check->user_id);
        if (! $user) {
            Log::channel('background_check')->warning('Promotion: user not found', [
                'background_check_id' => $check->id,
                'user_id' => $check->user_id,
            ]);

            return;
        }

        Log::channel('background_check')->info('Promotion: promoting user to level2', [
            'background_check_id' => $check->id,
            'user_id' => $user->id,
        ]);

        $this->levelService->promote(
            $user,
            'level2',
            catalogLevel: $levelTwo,
        );

        Log::channel('background_check')->info('Promotion: user promoted to level2', [
            'background_check_id' => $check->id,
            'user_id' => $user->id,
        ]);
    }
}
