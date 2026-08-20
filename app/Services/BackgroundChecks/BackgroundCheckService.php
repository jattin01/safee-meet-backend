<?php

namespace App\Services\BackgroundChecks;

use App\DTOs\BackgroundChecks\EligibilityResult;
use App\Jobs\BackgroundChecks\RefreshDiditDecisionForBackgroundCheck;
use App\Jobs\BackgroundChecks\SubmitSearchbugBackgroundCheck;
use App\Models\Admin;
use App\Models\BackgroundCheck;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BackgroundCheckService
{
    public function __construct(
        private readonly BackgroundCheckEligibilityService $eligibility,
    ) {}

    public function queueIfEligible(User $user): EligibilityResult
    {
        $result = $this->eligibility->evaluate($user);
        if (! $result->eligible || ! $result->identity) {
            if (config('services.didit.refresh_incomplete_decisions')
                && $result->verification?->didit_session_id
                && in_array($result->reason, [
                    'DIDIT_ID_NOT_APPROVED',
                    'VERIFIED_DETAILS_INCOMPLETE',
                ], true)) {
                RefreshDiditDecisionForBackgroundCheck::dispatch($result->verification->id)->afterCommit();
            }

            return $result;
        }

        $idempotencyKey = $this->eligibility->idempotencyKey(
            (string) $user->id,
            (string) $result->verification->id,
            $result->identity->fingerprint(),
        );

        try {
            $check = $this->createCheck($user, $result, $idempotencyKey);
        } catch (QueryException $exception) {
            $check = BackgroundCheck::where('idempotency_key', $idempotencyKey)->first();
            if (! $check) {
                throw $exception;
            }
        }

        SubmitSearchbugBackgroundCheck::dispatch($check->id)->afterCommit();

        return new EligibilityResult(
            false,
            'CHECK_QUEUED',
            $result->subscription,
            $result->verification,
            $result->consent,
            $result->identity,
            $check,
        );
    }

    public function queueExplicitRecheck(User $user, Admin $admin, string $reason): EligibilityResult
    {
        $result = $this->eligibility->evaluate($user, ignoreExistingCheck: true);
        if (! $result->eligible || ! $result->identity) {
            return $result;
        }

        $pending = BackgroundCheck::where('user_id', $user->id)
            ->where('check_type', 'criminal')
            ->where('status', 'pending')
            ->first();

        if ($pending) {
            return new EligibilityResult(
                false,
                'CHECK_IN_PROGRESS',
                $result->subscription,
                $result->verification,
                $result->consent,
                $result->identity,
                $pending,
            );
        }

        $previous = BackgroundCheck::where('user_id', $user->id)
            ->where('check_type', 'criminal')
            ->latest('requested_at')
            ->first();
        $idempotencyKey = hash('sha256', implode('|', [
            $this->eligibility->idempotencyKey(
                (string) $user->id,
                (string) $result->verification->id,
                $result->identity->fingerprint(),
            ),
            'admin-recheck',
            (string) Str::ulid(),
        ]));

        $check = $this->createCheck($user, $result, $idempotencyKey, [
            'recheck_of_id' => $previous?->id,
            'recheck_reason' => $reason,
            'requested_by_admin_id' => $admin->id,
        ]);

        SubmitSearchbugBackgroundCheck::dispatch($check->id)->afterCommit();

        return new EligibilityResult(
            false,
            'RECHECK_QUEUED',
            $result->subscription,
            $result->verification,
            $result->consent,
            $result->identity,
            $check,
        );
    }

    /** @param array<string, mixed> $extra */
    private function createCheck(
        User $user,
        EligibilityResult $result,
        string $idempotencyKey,
        array $extra = [],
    ): BackgroundCheck {
        return DB::transaction(function () use ($user, $result, $idempotencyKey, $extra): BackgroundCheck {
            $id = (string) Str::ulid();

            return BackgroundCheck::create(array_merge([
                'id' => $id,
                'user_id' => $user->id,
                'user_verification_id' => $result->verification->id,
                'subscription_id' => $result->subscription->id,
                'plan_id' => $result->subscription->plan_id,
                'consent_id' => $result->consent->id,
                'provider' => 'searchbug',
                'check_type' => 'criminal',
                'provider_reference_id' => 'pending:'.$id,
                'idempotency_key' => $idempotencyKey,
                'status' => 'pending',
                'provider_status' => 'queued',
                'request_fingerprint' => $result->identity->fingerprint(),
                'requested_at' => now(),
            ], $extra));
        });
    }
}
