<?php

namespace App\Observers;

use App\Jobs\BackgroundChecks\EvaluateBackgroundCheckEligibility;
use App\Models\UserVerification;
use Illuminate\Support\Facades\DB;

class UserVerificationObserver
{
    public function updated(UserVerification $verification): void
    {
        if ($verification->provider !== 'didit' || $verification->status !== 'approved') {
            return;
        }

        if ($verification->wasChanged(['status', 'didit_decision_status', 'didit_payload'])) {
            $userId = $verification->user_id;
            DB::afterCommit(fn () => EvaluateBackgroundCheckEligibility::dispatch($userId));
        }
    }
}
