<?php

namespace App\Observers;

use App\Jobs\BackgroundChecks\EvaluateBackgroundCheckEligibility;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

class SubscriptionObserver
{
    public function created(Subscription $subscription): void
    {
        if ($subscription->status === 'active') {
            $this->evaluateAfterCommit($subscription);
        }
    }

    public function updated(Subscription $subscription): void
    {
        if ($subscription->wasChanged('status') && $subscription->status === 'active') {
            $this->evaluateAfterCommit($subscription);
        }
    }

    private function evaluateAfterCommit(Subscription $subscription): void
    {
        $userId = $subscription->user_id;
        DB::afterCommit(fn () => EvaluateBackgroundCheckEligibility::dispatch($userId));
    }
}
