<?php

namespace App\Services;

use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
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
    private const SNAPSHOT_COLUMNS = [
        'pin_search' => 'safee_pin_search',
        'meeting_history' => 'meeting_history',
        'level1_verification' => 'level_1_verification',
        'level2_clearance' => 'level_2_clearance',
        'verified_badge' => 'verified_badge_display',
        'qr_code' => 'qr_generation',
        'trust_score' => 'trust_score_calculation',
        'safety_score_analytics' => 'safety_score_analytics',
        'trusted_contact_alerts' => 'trusted_contact_alerts',
        'premium_badge' => 'premium_badge',
    ];

    private const LIMIT_FEATURES = ['pin_search', 'meeting_history'];

    /** Per-request cache of a user's plan features, keyed by feature slug. */
    private array $cache = [];

    /** Per-request cache of the user's current immutable entitlement snapshot. */
    private array $subscriptionCache = [];

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
        return $this->activeUserSubscription($user) !== null
            || in_array($user->subscription_status, ['trial', 'active'], true);
    }

    public function activeUserSubscription(User $user): ?UserSubscription
    {
        return $this->subscriptionCache[$user->id] ??= $user->userSubscriptions()
            ->whereIn('status', ['trial', 'active'])
            ->latest('id')
            ->first();
    }

    /**
     * Freeze a plan's current feature matrix into the columns stored on one
     * user_subscriptions row. Limit features keep their pivot value; boolean
     * features are enabled by the existence of the plan_feature row.
     */
    public function snapshotFor(SubscriptionPlan $plan): array
    {
        $features = $plan->relationLoaded('comparisonFeatures')
            ? $plan->comparisonFeatures->keyBy('slug')
            : $plan->comparisonFeatures()->get()->keyBy('slug');

        $snapshot = [];

        foreach (self::SNAPSHOT_COLUMNS as $slug => $column) {
            $feature = $features->get($slug);
            $snapshot[$column] = in_array($slug, self::LIMIT_FEATURES, true)
                ? $feature?->pivot?->value
                : $feature !== null;
        }

        $pinLimit = $snapshot['safee_pin_search'];
        $snapshot['safee_pin_search_remaining'] = is_numeric($pinLimit)
            ? (int) $pinLimit
            : (strcasecmp(trim((string) $pinLimit), 'Unlimited') === 0 ? null : 0);

        return $snapshot;
    }

    /** Is a boolean feature included in the user's plan? */
    public function has(User $user, string $slug): bool
    {
        if (($column = self::SNAPSHOT_COLUMNS[$slug] ?? null)
            && ! in_array($slug, self::LIMIT_FEATURES, true)
            && ($subscription = $this->activeUserSubscription($user))) {
            return (bool) $subscription->{$column};
        }

        $feature = $this->features($user)->get($slug);

        return $feature !== null;
    }

    /** Raw matrix value for a feature ("3", "Unlimited", null). */
    public function value(User $user, string $slug): ?string
    {
        if (($column = self::SNAPSHOT_COLUMNS[$slug] ?? null)
            && ($subscription = $this->activeUserSubscription($user))) {
            $value = $subscription->{$column};

            return $value === null ? null : (string) $value;
        }

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
        if (($column = self::SNAPSHOT_COLUMNS[$slug] ?? null)
            && ($subscription = $this->activeUserSubscription($user))) {
            $value = $subscription->{$column};

            if ($value === null || $value === '') {
                return 0;
            }

            return is_numeric($value) ? (int) $value : null;
        }

        $feature = $this->features($user)->get($slug);

        if (! $feature) {
            return 0; // not entitled at all
        }

        $value = $feature->pivot->value;

        if ($value === null || ! is_numeric($value)) {
            return null; // included but "Unlimited"
        }

        return (int) $value;
    }
}
