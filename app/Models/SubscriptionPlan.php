<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    protected $fillable = [
        'name', 'slug', 'monthly_price', 'yearly_price', 'trial_days',
        'pin_search_limit', 'features', 'icon', 'color', 'sort_order', 'is_active',
        'monthly_stripe_price_id', 'yearly_stripe_price_id',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'monthly_price' => 'decimal:2',
            'yearly_price' => 'decimal:2',
            'trial_days' => 'integer',
            'pin_search_limit' => 'integer',
            'is_active' => 'boolean',
        ];
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

    /** null pin_search_limit means unlimited searches on this plan. */
    public function hasUnlimitedPinSearch(): bool
    {
        return $this->pin_search_limit === null;
    }
}
