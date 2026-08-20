<?php

namespace App\Jobs\BackgroundChecks;

use App\Contracts\CriminalBackgroundCheckProvider;
use App\Exceptions\BackgroundCheckProviderException;
use App\Models\BackgroundCheck;
use App\Services\BackgroundChecks\DiditVerifiedIdentityExtractor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SubmitSearchbugBackgroundCheck implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public readonly string $backgroundCheckId) {}

    public function handle(
        CriminalBackgroundCheckProvider $provider,
        DiditVerifiedIdentityExtractor $extractor,
    ): void {
        $check = BackgroundCheck::with(['verification', 'subscription.plan.comparisonFeatures', 'consent'])
            ->find($this->backgroundCheckId);

        if (! $check || $check->status !== 'pending' || $check->provider_status !== 'queued') {
            return;
        }

        if (! $check->subscription
            || $check->subscription->status !== 'active'
            || ! $check->consent
            || ! $check->consent->accepted
            || $check->consent->revoked_at) {
            $this->markFailed($check, 'ELIGIBILITY_CHANGED', 'Background check eligibility changed before submission.');

            return;
        }

        $feature = $check->subscription->plan?->comparisonFeatures
            ->firstWhere('slug', 'background_verification');
        if (! $feature || ! (bool) $feature->pivot->included) {
            $this->markFailed($check, 'PLAN_NOT_ELIGIBLE', 'The active plan no longer includes background verification.');

            return;
        }

        if (! $check->verification) {
            $this->markFailed($check, 'LEVEL_ONE_NOT_APPROVED', 'The Didit verification is unavailable.');

            return;
        }

        $extraction = $extractor->extract($check->verification);
        if (! $extraction->identity) {
            $this->markFailed($check, $extraction->reason, 'Verified identity details are not ready.');

            return;
        }

        try {
            $result = $provider->submit($extraction->identity, (string) $check->idempotency_key);
        } catch (BackgroundCheckProviderException $exception) {
            if ($exception->retryable) {
                throw $exception;
            }

            $this->markFailed($check, $exception->providerCode, $exception->getMessage());

            return;
        }

        $check->forceFill([
            'provider_reference_id' => $result->reference ?: $check->provider_reference_id,
            'provider_status' => $result->providerStatus,
            'provider_response' => $result->raw,
            'submitted_at' => now(),
        ]);

        $this->applyResult($check, $result->classification);
        $check->save();

    }

    public function failed(?Throwable $exception): void
    {
        $check = BackgroundCheck::find($this->backgroundCheckId);
        if ($check && $check->status === 'pending') {
            $this->markFailed($check, 'PROVIDER_UNAVAILABLE', 'Searchbug could not be reached after retries.');
        }
    }

    private function applyResult(BackgroundCheck $check, string $classification): void
    {
        $check->result_classification = $classification;

        if ($classification === 'clear') {
            $check->status = 'clear';
            $check->result_summary = 'No matching records were returned.';
            $check->completed_at = now();
            $check->expires_at = now()->addDays((int) config('services.searchbug.valid_for_days', 365));
        } elseif ($classification === 'flagged') {
            $check->status = 'flagged';
            $check->result_summary = 'Potential records require manual review.';
            $check->completed_at = now();
        } elseif ($classification === 'failed') {
            $this->markFailed($check, 'PROVIDER_REJECTED', 'Searchbug rejected the background-check request.', false);
        }
    }

    private function markFailed(
        BackgroundCheck $check,
        string $code,
        string $message,
        bool $save = true,
    ): void {
        $check->forceFill([
            'status' => 'failed',
            'provider_status' => 'failed',
            'result_classification' => 'failed',
            'failure_code' => $code,
            'failure_message' => $message,
            'failed_at' => now(),
        ]);

        if ($save) {
            $check->save();
        }
    }
}
