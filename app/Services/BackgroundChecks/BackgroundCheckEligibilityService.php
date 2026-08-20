<?php

namespace App\Services\BackgroundChecks;

use App\DTOs\BackgroundChecks\EligibilityResult;
use App\Models\BackgroundCheck;
use App\Models\User;
use App\Models\UserConsent;
use App\Models\UserVerification;

class BackgroundCheckEligibilityService
{
    public function __construct(
        private readonly DiditVerifiedIdentityExtractor $identityExtractor,
    ) {}

    public function evaluate(User $user, bool $ignoreExistingCheck = false): EligibilityResult
    {
        if (! config('services.searchbug.enabled')) {
            return new EligibilityResult(false, 'PROVIDER_DISABLED');
        }

        if ($user->kyc_status !== 'verified'
            || ! in_array($user->verification_level, ['level1', 'level2', 'professional'], true)) {
            return new EligibilityResult(false, 'LEVEL_ONE_NOT_APPROVED');
        }

        $subscription = $user->subscriptions()
            ->where('status', 'active')
            ->with('plan.comparisonFeatures')
            ->latest('id')
            ->first();

        if (! $subscription) {
            return new EligibilityResult(false, 'NO_ACTIVE_SUBSCRIPTION');
        }

        $feature = $subscription->plan?->comparisonFeatures
            ->firstWhere('slug', 'background_verification');

        if (! $feature || ! (bool) $feature->pivot->included) {
            return new EligibilityResult(false, 'PLAN_NOT_ELIGIBLE', subscription: $subscription);
        }

        $verification = UserVerification::where('user_id', $user->id)
            ->where('provider', 'didit')
            ->latest('id')
            ->first();

        if (! $verification) {
            return new EligibilityResult(false, 'LEVEL_ONE_NOT_APPROVED', subscription: $subscription);
        }

        $extraction = $this->identityExtractor->extract($verification);
        if (! $extraction->isComplete()) {
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
