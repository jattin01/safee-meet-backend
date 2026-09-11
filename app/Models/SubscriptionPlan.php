<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    // Included on every toArray()/toJson() (e.g. the /api/subscriptions/plans
    // response) so the app gets the "% OFF" badge without extra math.
    protected $appends = ['monthly_discount_percent', 'yearly_discount_percent'];

    protected $fillable = [
        'name', 'slug', 'account_type', 'monthly_price', 'monthly_original_price',
        'yearly_price', 'yearly_original_price', 'trial_days',
        'pin_search_limit', 'features', 'icon', 'color', 'sort_order', 'is_active',
        'monthly_stripe_price_id', 'yearly_stripe_price_id',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'monthly_price' => 'decimal:2',
            'monthly_original_price' => 'decimal:2',
            'yearly_price' => 'decimal:2',
            'yearly_original_price' => 'decimal:2',
            'trial_days' => 'integer',
            'pin_search_limit' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * % off vs the monthly MRP, for the "XX% OFF" badge. Null when there's
     * no original price set, or it isn't actually higher than the sale price.
     */
    public function getMonthlyDiscountPercentAttribute(): ?int
    {
        return $this->discountPercent($this->monthly_price, $this->monthly_original_price);
    }

    public function getYearlyDiscountPercentAttribute(): ?int
    {
        return $this->discountPercent($this->yearly_price, $this->yearly_original_price);
    }

    private function discountPercent($price, $originalPrice): ?int
    {
        if ($originalPrice === null || (float) $originalPrice <= (float) $price) {
            return null;
        }

        return (int) round((1 - ((float) $price / (float) $originalPrice)) * 100);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'plan_id');
    }

    // Named comparisonFeatures (not features) to avoid clashing with the
    // legacy free-text `features` JSON column still used by the admin CRUD.
    public function comparisonFeatures(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class, 'plan_feature', 'plan_id', 'feature_id')
            ->withPivot('included', 'value')
            ->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Restrict to plans targeted at the given account type ('normal' /
     * 'employer'), plus any plan tagged 'both' (visible to everyone).
     */
    public function scopeForAccountType($query, string $accountType)
    {
        return $query->whereIn('account_type', [$accountType, 'both']);
    }

    /** null pin_search_limit means unlimited searches on this plan. */
    public function hasUnlimitedPinSearch(): bool
    {
        return $this->pin_search_limit === null;
    }
}
