<?php

namespace App\Services\BackgroundChecks;

use App\DTOs\BackgroundChecks\EligibilityResult;
use App\Models\BackgroundCheck;
use App\Models\User;
use App\Models\UserConsent;
use App\Models\UserVerification;
use Illuminate\Support\Facades\Log;

class BackgroundCheckEligibilityService
{
    public function __construct(
        private readonly DiditVerifiedIdentityExtractor $identityExtractor,
    ) {}

    public function evaluate(User $user, bool $ignoreExistingCheck = false): EligibilityResult
    {
        Log::channel('background_check')->info('Eligibility: evaluate() started', [
            'user_id' => $user->id,
            'kyc_status' => $user->kyc_status,
            'verification_level' => $user->verification_level,
        ]);

        if (! config('services.signzy.enabled')) {
            Log::channel('background_check')->info('Eligibility: provider disabled', ['user_id' => $user->id]);

            return new EligibilityResult(false, 'PROVIDER_DISABLED');
        }

        if ($user->kyc_status !== 'verified'
            || ! in_array($user->verification_level, ['level1', 'level2', 'professional'], true)) {
            Log::channel('background_check')->info('Eligibility: level1 not approved', ['user_id' => $user->id]);

            return new EligibilityResult(false, 'LEVEL_ONE_NOT_APPROVED');
        }

        $subscription = $user->subscriptions()
            ->where('status', 'active')
            ->with('plan.comparisonFeatures')
            ->latest('id')
            ->first();

        if (! $subscription) {
            Log::channel('background_check')->info('Eligibility: no active subscription', ['user_id' => $user->id]);

            return new EligibilityResult(false, 'NO_ACTIVE_SUBSCRIPTION');
        }

        $feature = $subscription->plan?->comparisonFeatures
            ->firstWhere('slug', 'background_verification');

        if (! $feature || ! (bool) $feature->pivot->included) {
            Log::channel('background_check')->info('Eligibility: plan not eligible', [
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'plan_id' => $subscription->plan_id,
            ]);

            return new EligibilityResult(false, 'PLAN_NOT_ELIGIBLE', subscription: $subscription);
        }

        $verification = UserVerification::where('user_id', $user->id)
            ->where('provider', 'didit')
            ->latest('id')
            ->first();

        if (! $verification) {
            Log::channel('background_check')->info('Eligibility: no didit verification found', ['user_id' => $user->id]);

            return new EligibilityResult(false, 'LEVEL_ONE_NOT_APPROVED', subscription: $subscription);
        }

        $extraction = $this->identityExtractor->extract($verification);
        if (! $extraction->isComplete()) {
            Log::channel('background_check')->info('Eligibility: identity extraction incomplete', [
                'user_id' => $user->id,
                'verification_id' => $verification->id,
                'reason' => $extraction->reason,
                'missing_fields' => $extraction->missingFields,
            ]);

            return new EligibilityResult(
                false,
                $extraction->reason,
                subscription: $subscription,
                verification: $verification,
                missingFields: $extraction->missingFields,
            );
        }

        $consent = UserConsent::where('user_id', $user->id)
            ->activeBackgroundCheck()
            ->where('version', config('services.searchbug.consent_version'))
            ->latest('created_at')
            ->first();

        if (! $consent) {
            Log::channel('background_check')->info('Eligibility: consent required', [
                'user_id' => $user->id,
                'verification_id' => $verification->id,
            ]);

            return new EligibilityResult(
                false,
                'CONSENT_REQUIRED',
                subscription: $subscription,
                verification: $verification,
                identity: $extraction->identity,
            );
        }

        $idempotencyKey = $this->idempotencyKey(
            (string) $user->id,
            (string) $verification->id,
            $extraction->identity->fingerprint(),
        );

        $existing = BackgroundCheck::where('idempotency_key', $idempotencyKey)->first();
        if ($existing && ! $ignoreExistingCheck) {
            Log::channel('background_check')->info('Eligibility: check already exists', [
                'user_id' => $user->id,
                'background_check_id' => $existing->id,
                'status' => $existing->status,
            ]);

            return new EligibilityResult(
                false,
                'CHECK_ALREADY_EXISTS',
                $subscription,
                $verification,
                $consent,
                $extraction->identity,
                $existing,
            );
        }

        Log::channel('background_check')->info('Eligibility: user is eligible', [
            'user_id' => $user->id,
            'verification_id' => $verification->id,
            'idempotency_key' => $idempotencyKey,
        ]);

        return new EligibilityResult(
            true,
            'ELIGIBLE',
            $subscription,
            $verification,
            $consent,
            $extraction->identity,
        );
    }

    public function idempotencyKey(string $userId, string $verificationId, string $fingerprint): string
    {
        return hash('sha256', implode('|', [
            $userId,
            $verificationId,
            $fingerprint,
            (string) config('services.searchbug.consent_version'),
        ]));
    }
}
