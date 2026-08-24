<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Subscription extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'plan_id', 'price', 'billing_cycle', 'status',
        'trial_days', 'started_at', 'renews_at', 'cancelled_at',
        'stripe_customer_id', 'stripe_subscription_id', 'subscription_id',
    ];

    /**
     * ULID handed to the app at subscribe time so it has a stable id to send
     * back on the later payment-confirm/webhook step, without depending on
     * the primary key (which is bigint on some deployments, char on others).
     */
    protected static function booted(): void
    {
        static::creating(function (Subscription $subscription) {
            if (empty($subscription->subscription_id)) {
                $subscription->subscription_id = (string) Str::ulid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'renews_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'price' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['trial', 'active']);
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'expired');
    }

    public function scopeTrial($query)
    {
        return $query->where('status', 'trial');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'trial' => 'Trial',
            'active' => 'Active',
            'expired' => 'Expired',
            'cancelled' => 'Cancelled',
            default => ucfirst((string) $this->status),
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'active' => '#4ade80',
            'trial' => '#facc15',
            'expired', 'cancelled' => '#f87171',
            default => '#9ca3af',
        };
    }

    public function getPlanLabelAttribute(): string
    {
        return $this->plan?->name ?? '—';
    }
}
