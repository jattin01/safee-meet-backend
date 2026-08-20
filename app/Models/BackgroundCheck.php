<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackgroundCheck extends Model
{
    use HasUlids;

    protected $fillable = [
        'id', 'user_id', 'user_verification_id', 'subscription_id', 'plan_id',
        'consent_id', 'provider', 'check_type', 'provider_reference_id',
        'idempotency_key', 'status', 'provider_status', 'result_classification',
        'result_score', 'result_summary', 'request_fingerprint', 'provider_response',
        'failure_code', 'failure_message', 'poll_attempts', 'requested_at',
        'submitted_at', 'completed_at', 'failed_at', 'expires_at', 'recheck_of_id',
        'recheck_reason', 'requested_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'provider_response' => 'encrypted:array',
            'result_score' => 'integer',
            'poll_attempts' => 'integer',
            'requested_at' => 'datetime',
            'submitted_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verification(): BelongsTo
    {
        return $this->belongsTo(UserVerification::class, 'user_verification_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }

    public function consent(): BelongsTo
    {
        return $this->belongsTo(UserConsent::class, 'consent_id');
    }

    public function previousCheck(): BelongsTo
    {
        return $this->belongsTo(self::class, 'recheck_of_id');
    }

    public function requestedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'requested_by_admin_id');
    }
}
