<?php

namespace App\Jobs\BackgroundChecks;

use App\Models\User;
use App\Services\BackgroundChecks\BackgroundCheckService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class EvaluateBackgroundCheckEligibility implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 300;

    public function __construct(public readonly int|string $userId) {}

    public function uniqueId(): string
    {
        return (string) $this->userId;
    }

    public function handle(BackgroundCheckService $service): void
    {
        Log::channel('background_check')->info('Job: EvaluateBackgroundCheckEligibility started', [
            'user_id' => $this->userId,
        ]);

        $user = User::find($this->userId);
        if (! $user) {
            Log::channel('background_check')->warning('Job: EvaluateBackgroundCheckEligibility user not found', [
                'user_id' => $this->userId,
            ]);

            return;
        }

        $result = $service->queueIfEligible($user);

        Log::channel('background_check')->info('Job: EvaluateBackgroundCheckEligibility finished', [
            'user_id' => $this->userId,
            'eligible' => $result->eligible,
            'reason' => $result->reason,
        ]);
    }
}
