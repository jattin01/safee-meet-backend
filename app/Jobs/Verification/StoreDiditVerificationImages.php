<?php

namespace App\Jobs\Verification;

use App\Models\UserVerification;
use App\Services\Verification\DiditVerificationImageStorage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class StoreDiditVerificationImages implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function __construct(public readonly int|string $verificationId) {}

    public function handle(DiditVerificationImageStorage $storage): void
    {
        $verification = UserVerification::find($this->verificationId);
        if (! $verification || $verification->provider !== 'didit') {
            return;
        }

        $failures = $storage->storeAvailableImages($verification);
        if ($failures !== []) {
            throw new RuntimeException('Didit image downloads failed for: '.implode(', ', $failures));
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Didit verification images exhausted all download attempts', [
            'verification_id' => $this->verificationId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
