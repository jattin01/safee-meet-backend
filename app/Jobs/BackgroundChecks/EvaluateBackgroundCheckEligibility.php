<?php

namespace App\Jobs\BackgroundChecks;

use App\Models\User;
use App\Services\BackgroundChecks\BackgroundCheckService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

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
        $user = User::find($this->userId);
        if ($user) {
            $service->queueIfEligible($user);
        }
    }
}
