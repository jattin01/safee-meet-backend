<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Coupon extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code', 'description', 'discount_type', 'discount_value',
        'plan_id', 'billing_cycle', 'max_redemptions', 'max_redemptions_per_user',
        'times_redeemed', 'starts_at', 'expires_at', 'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'max_redemptions' => 'integer',
            'max_redemptions_per_user' => 'integer',
            'times_redeemed' => 'integer',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function hasStarted(): bool
    {
        return $this->starts_at === null || $this->starts_at->isPast();
    }

    public function hasExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function hasReachedGlobalLimit(): bool
    {
        return $this->max_redemptions !== null && $this->times_redeemed >= $this->max_redemptions;
    }

    /**
     * How many times the given user has already redeemed this coupon.
     */
    public function redemptionsCountFor(string|int $userId): int
    {
        return $this->redemptions()->where('user_id', $userId)->count();
    }

    public function hasReachedPerUserLimit(string|int $userId): bool
    {
        return $this->redemptionsCountFor($userId) >= $this->max_redemptions_per_user;
    }

    /**
     * Full eligibility check for a given user/plan/billing_cycle combo.
     * Returns null when the coupon is usable, or an error message otherwise.
     */
    public function eligibilityError(string|int $userId, ?SubscriptionPlan $plan = null, ?string $billingCycle = null): ?string
    {
        if (! $this->is_active) {
            return 'This coupon is no longer active.';
        }

        if (! $this->hasStarted()) {
            return 'This coupon is not active yet.';
        }

        if ($this->hasExpired()) {
            return 'This coupon has expired.';
        }

        if ($this->hasReachedGlobalLimit()) {
            return 'This coupon has reached its maximum number of redemptions.';
        }

        if ($this->hasReachedPerUserLimit($userId)) {
            return 'You have already used this coupon the maximum number of times.';
        }

        if ($this->plan_id !== null && $plan !== null && $this->plan_id !== $plan->id) {
            return 'This coupon is not valid for the selected plan.';
        }

        if ($this->billing_cycle !== null && $billingCycle !== null && $this->billing_cycle !== $billingCycle) {
            return "This coupon is only valid for {$this->billing_cycle} billing.";
        }

        return null;
    }

    /**
     * Discount amount for a given price, clamped so it never exceeds the
     * price itself (a percentage/fixed coupon can never make the price negative).
     */
    public function discountFor(float $price): float
    {
        $discount = $this->discount_type === 'percentage'
            ? $price * ((float) $this->discount_value / 100)
            : (float) $this->discount_value;

        return round(min($discount, $price), 2);
    }
}
