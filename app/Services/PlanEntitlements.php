<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Single entry point for "what is this user's plan allowed to do", sourced from
 * the plan_feature matrix (admin-managed) — no hardcoded plan names anywhere.
 *
 * Feature slugs come from the features catalog, e.g. 'pin_search' (limit),
 * 'qr_code' / 'api_access' (boolean).
 */
class PlanEntitlements
{
    /** Per-request cache of a user's plan features, keyed by feature slug. */
    private array $cache = [];

    private function features(User $user): Collection
    {
        return $this->cache[$user->id] ??= $user->plan
            ? $user->plan->comparisonFeatures()->get()->keyBy('slug')
            : collect();
    }

    /**
     * Is the user's subscription currently usable? Trial and active count;
     * expired/cancelled/not_subscribed do not — those gate off search/chat
     * until the user pays.
     */
    public function subscriptionActive(User $user): bool
    {
        return in_array($user->subscription_status, ['trial', 'active'], true);
    }

    /** Is a boolean feature included in the user's plan? */
    public function has(User $user, string $slug): bool
    {
        $feature = $this->features($user)->get($slug);

        return $feature ? (bool) $feature->pivot->included : false;
    }

    /** Raw matrix value for a feature ("3", "Unlimited", null). */
    public function value(User $user, string $slug): ?string
    {
        return $this->features($user)->get($slug)?->pivot->value;
    }

    /**
     * Numeric monthly allowance for a limit feature:
     *   - integer   → that many (e.g. 3)
     *   - null      → unlimited (feature included with "Unlimited"/no value)
     *   - 0         → no access (feature not included, or user has no plan)
     */
    public function numericLimit(User $user, string $slug): ?int
    {
        $feature = $this->features($user)->get($slug);

        if (! $feature || ! $feature->pivot->included) {
            return 0; // not entitled at all
        }

        $value = $feature->pivot->value;

        if ($value === null || ! is_numeric($value)) {
            return null; // included but "Unlimited"
        }

        return (int) $value;
    }
}
