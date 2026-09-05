<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserSubscription extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'subscription_id',
        'user_id',
        'plan_id',
        'price',
        'billing_cycle',
        'status',
        'trial_days',
        'started_at',
        'renews_at',
        'cancelled_at',
        'stripe_customer_id',
        'stripe_subscription_id',

        'safee_pin_search',
        'safee_pin_search_remaining',
        'meeting_history',
        'level_1_verification',
        'level_2_clearance',
        'verified_badge_display',
        'qr_generation',
        'trust_score_calculation',
        'safety_score_analytics',
        'trusted_contact_alerts',
        'premium_badge',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'trial_days' => 'integer',
            'safee_pin_search_remaining' => 'integer',
            'level_1_verification' => 'boolean',
            'level_2_clearance' => 'boolean',
            'verified_badge_display' => 'boolean',
            'qr_generation' => 'boolean',
            'trust_score_calculation' => 'boolean',
            'safety_score_analytics' => 'boolean',
            'trusted_contact_alerts' => 'boolean',
            'premium_badge' => 'boolean',
            'started_at' => 'datetime',
            'renews_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'subscription_id', 'subscription_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function searches(): HasMany
    {
        return $this->hasMany(SearchHistory::class);
    }

    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class);
    }
}
