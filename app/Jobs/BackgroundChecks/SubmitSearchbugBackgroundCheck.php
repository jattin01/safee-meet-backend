<?php

namespace App\Jobs\BackgroundChecks;

use App\Contracts\CriminalBackgroundCheckProvider;
use App\Exceptions\BackgroundCheckProviderException;
use App\Models\BackgroundCheck;
use App\Services\BackgroundChecks\DiditVerifiedIdentityExtractor;
use App\Services\BackgroundChecks\VerificationLevelPromotionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
        VerificationLevelPromotionService $levelPromotion,
    ): void {
        Log::channel('background_check')->info('Job: SubmitSearchbugBackgroundCheck started', [
            'background_check_id' => $this->backgroundCheckId,
            'attempt' => $this->attempts(),
        ]);

        $check = BackgroundCheck::with(['verification', 'subscription.plan.comparisonFeatures', 'consent'])
            ->find($this->backgroundCheckId);

        if (! $check || $check->status !== 'pending' || $check->provider_status !== 'queued') {
            Log::channel('background_check')->warning('Job: check not pending/queued, skipping', [
                'background_check_id' => $this->backgroundCheckId,
                'status' => $check?->status,
                'provider_status' => $check?->provider_status,
            ]);

            return;
        }

        if (! $check->subscription
            || $check->subscription->status !== 'active'
            || ! $check->consent
            || ! $check->consent->accepted
            || $check->consent->revoked_at) {
            Log::channel('background_check')->info('Job: eligibility changed before submission', [
                'background_check_id' => $check->id,
                'user_id' => $check->user_id,
                'subscription_status' => $check->subscription?->status,
                'consent_accepted' => $check->consent?->accepted,
                'consent_revoked_at' => $check->consent?->revoked_at,
            ]);

            $this->markFailed($check, 'ELIGIBILITY_CHANGED', 'Background check eligibility changed before submission.');

            return;
        }

        $feature = $check->subscription->plan?->comparisonFeatures
            ->firstWhere('slug', 'background_verification');
        if (! $feature || ! (bool) $feature->pivot->included) {
            Log::channel('background_check')->info('Job: plan no longer eligible', [
                'background_check_id' => $check->id,
                'user_id' => $check->user_id,
            ]);

            $this->markFailed($check, 'PLAN_NOT_ELIGIBLE', 'The active plan no longer includes background verification.');

            return;
        }

        if (! $check->verification) {
            Log::channel('background_check')->info('Job: didit verification unavailable', [
                'background_check_id' => $check->id,
                'user_id' => $check->user_id,
            ]);

            $this->markFailed($check, 'LEVEL_ONE_NOT_APPROVED', 'The Didit verification is unavailable.');

            return;
        }

        $extraction = $extractor->extract($check->verification);
        if (! $extraction->identity) {
            Log::channel('background_check')->info('Job: identity extraction not ready', [
                'background_check_id' => $check->id,
                'user_id' => $check->user_id,
                'reason' => $extraction->reason,
            ]);

            $this->markFailed($check, $extraction->reason, 'Verified identity details are not ready.');

            return;
        }

        // Resolve the existing catalog row before making a paid provider call.
        // A missing Level 2 setup must not cause the provider to be called twice.
        $levelTwo = $levelPromotion->levelTwo();

        Log::channel('background_check')->info('Job: calling Signzy provider', [
            'background_check_id' => $check->id,
            'user_id' => $check->user_id,
            'idempotency_key' => $check->idempotency_key,
        ]);

        try {
            $result = $provider->submit($extraction->identity, (string) $check->idempotency_key);
        } catch (BackgroundCheckProviderException $exception) {
            Log::channel('background_check')->error('Job: Signzy provider call failed', [
                'background_check_id' => $check->id,
                'user_id' => $check->user_id,
                'provider_code' => $exception->providerCode,
                'retryable' => $exception->retryable,
                'message' => $exception->getMessage(),
            ]);

            if ($exception->retryable) {
                throw $exception;
            }

            $this->markFailed($check, $exception->providerCode, $exception->getMessage());

            return;
        }

        Log::channel('background_check')->info('Job: Signzy provider returned result', [
            'background_check_id' => $check->id,
            'user_id' => $check->user_id,
            'provider_status' => $result->providerStatus,
            'classification' => $result->classification,
        ]);

        DB::transaction(function () use ($check, $result, $levelPromotion, $levelTwo): void {
            $check->forceFill([
                'provider_reference_id' => $result->reference ?: $check->provider_reference_id,
                'provider_status' => $result->providerStatus,
                'provider_response' => $result->raw,
                'submitted_at' => now(),
            ]);

            $this->applyResult($check, $result->classification);
            $check->save();
            $levelPromotion->promoteAfterSuccessfulCompletion($check, $levelTwo);
        });

        Log::channel('background_check')->info('Job: SubmitSearchbugBackgroundCheck finished', [
            'background_check_id' => $check->id,
            'user_id' => $check->user_id,
            'final_status' => $check->status,
            'result_classification' => $check->result_classification,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        Log::channel('background_check')->error('Job: SubmitSearchbugBackgroundCheck failed permanently', [
            'background_check_id' => $this->backgroundCheckId,
            'exception' => $exception?->getMessage(),
        ]);

        $check = BackgroundCheck::find($this->backgroundCheckId);
        if ($check && $check->status === 'pending') {
            $this->markFailed($check, 'PROVIDER_UNAVAILABLE', 'Background-check provider could not be reached after retries.');
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
        } elseif ($classification === 'verified') {
            $check->status = 'clear';
            $check->result_summary = 'Background-check verification completed without an explicit failure.';
            $check->completed_at = now();
            $check->expires_at = now()->addDays((int) config('services.searchbug.valid_for_days', 365));
        } elseif ($classification === 'failed') {
            $this->markFailed($check, 'PROVIDER_REJECTED', 'Background-check provider rejected the background-check request.', false);
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
