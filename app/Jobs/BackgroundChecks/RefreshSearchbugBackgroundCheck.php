<?php

namespace App\Jobs\BackgroundChecks;

use App\Contracts\CriminalBackgroundCheckProvider;
use App\Exceptions\BackgroundCheckProviderException;
use App\Models\BackgroundCheck;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RefreshSearchbugBackgroundCheck implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public readonly string $backgroundCheckId) {}

    public function handle(CriminalBackgroundCheckProvider $provider): void
    {
        $check = BackgroundCheck::find($this->backgroundCheckId);
        if (! $check || $check->status !== 'pending') {
            return;
        }

        if ($check->poll_attempts >= (int) config('services.searchbug.max_poll_attempts', 12)) {
            $check->update([
                'status' => 'failed',
                'provider_status' => 'timed_out',
                'result_classification' => 'failed',
                'failure_code' => 'PROVIDER_TIMEOUT',
                'failure_message' => 'The provider did not complete the check in time.',
                'failed_at' => now(),
            ]);

            return;
        }

        try {
            $result = $provider->retrieve($check->provider_reference_id);
        } catch (BackgroundCheckProviderException $exception) {
            if ($exception->retryable) {
                throw $exception;
            }

            $check->update([
                'status' => 'failed',
                'provider_status' => 'failed',
                'result_classification' => 'failed',
                'failure_code' => $exception->providerCode,
                'failure_message' => $exception->getMessage(),
                'failed_at' => now(),
            ]);

            return;
        }

        $updates = [
            'provider_status' => $result->providerStatus,
            'provider_response' => $result->raw,
            'poll_attempts' => $check->poll_attempts + 1,
            'result_classification' => $result->classification,
        ];

        if ($result->classification === 'clear') {
            $updates += [
                'status' => 'clear',
                'result_summary' => 'No matching records were returned.',
                'completed_at' => now(),
                'expires_at' => now()->addDays((int) config('services.searchbug.valid_for_days', 365)),
            ];
        } elseif ($result->classification === 'flagged') {
            $updates += [
                'status' => 'flagged',
                'result_summary' => 'Potential records require manual review.',
                'completed_at' => now(),
            ];
        } elseif ($result->classification === 'failed') {
            $updates += [
                'status' => 'failed',
                'failure_code' => 'PROVIDER_REJECTED',
                'failure_message' => 'Searchbug rejected the background-check request.',
                'failed_at' => now(),
            ];
        }

        $check->update($updates);

        if ($result->isPending()) {
            self::dispatch($check->id)
                ->delay(now()->addSeconds((int) config('services.searchbug.poll_delay', 300)));
        }
    }
}
