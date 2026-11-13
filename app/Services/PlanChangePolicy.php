<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;

/**
 * Which plan changes a user on a running paid plan may make (decided rules):
 *
 * - Monthly plan: a higher plan (either cycle) or the same plan yearly is a
 *   new checkout at full price; the days left on the current month are not
 *   carried over. A lower plan waits until the current plan ends.
 * - Yearly plan: a higher yearly plan is a prorated upgrade of the same
 *   Stripe subscription (only the difference for the rest of the year is
 *   charged, renewal date unchanged). Monthly plans and lower plans wait
 *   until the year ends.
 * - The same plan and cycle again is refused.
 *
 * The free trial is for first-time users only: never while a paid plan
 * is running, and never again once the user has had any plan (a trial or
 * a paid one).
 *
 * Otherwise users on a trial, a free plan, or no running plan may pick any
 * plan.
 * Plans are ranked by sort_order, then monthly price.
 */
class PlanChangePolicy
{
    public const NEW_CHECKOUT = 'new_checkout';

    public const PRORATED_UPGRADE = 'prorated_upgrade';

    public const BLOCKED = 'blocked';

    /**
     * @return array{type: string, code: ?string, message: ?string, available_from: ?string}
     */
    public function decide(?Subscription $current, SubscriptionPlan $target, string $billingCycle, ?User $user = null): array
    {
        $currentPlan = $current?->plan;

        if ((int) $target->trial_days > 0 && ! $this->isRunningPaid($current) && $user && $this->hasHadPlan($user)) {
            return $this->result(self::BLOCKED, 'trial_not_available', 'The free trial can only be used once.');
        }

        if (! $current || ! $currentPlan || $current->status !== 'active' || (float) $current->price <= 0
            || ($current->renews_at && $current->renews_at->isPast())) {
            return $this->result(self::NEW_CHECKOUT);
        }

        $until = $current->renews_at?->format('d M Y');
        $availableFrom = $current->renews_at?->toIso8601String();

        // A free trial is not a plan to switch to later: say so plainly
        // instead of the downgrade message with a date.
        if ((int) $target->trial_days > 0) {
            return $this->result(self::BLOCKED, 'trial_not_available',
                'The free trial is not available while you have an active paid plan.');
        }

        if ($currentPlan->id === $target->id && $current->billing_cycle === $billingCycle) {
            return $this->result(self::BLOCKED, 'already_on_plan', 'You are already on this plan.', $availableFrom);
        }

        $higher = $this->rank($target) > $this->rank($currentPlan);

        if ($current->billing_cycle === 'yearly') {
            if ($higher && $billingCycle === 'yearly') {
                return $this->result($current->stripe_subscription_id ? self::PRORATED_UPGRADE : self::NEW_CHECKOUT);
            }

            if ($higher) {
                return $this->result(self::BLOCKED, 'yearly_upgrade_yearly_only',
                    'Your yearly plan can be upgraded to a higher yearly plan only. '
                        .($until ? "Monthly plans are available after it ends on {$until}." : 'Monthly plans are available after it ends.'),
                    $availableFrom);
            }

            return $this->downgrade($until, $availableFrom);
        }

        // Monthly: any higher plan, or the same plan billed yearly.
        if ($higher || ($currentPlan->id === $target->id && $billingCycle === 'yearly')) {
            return $this->result(self::NEW_CHECKOUT);
        }

        return $this->downgrade($until, $availableFrom);
    }

    /** Days left on the current paid period, for the "not carried over" warning. */
    public function unusedDays(?Subscription $current): int
    {
        if (! $current || $current->status !== 'active' || ! $current->renews_at || $current->renews_at->isPast()) {
            return 0;
        }

        return (int) ceil(now()->diffInSeconds($current->renews_at) / 86400);
    }

    /**
     * Whether the user has ever had a plan: a trial or a paid plan, running
     * or ended (soft-deleted rows too). Unpaid checkout attempts don't count.
     */
    public function hasHadPlan(User $user): bool
    {
        return Subscription::withTrashed()
            ->where('user_id', $user->id)
            ->where('status', '!=', 'incomplete')
            ->exists();
    }

    private function isRunningPaid(?Subscription $current): bool
    {
        return $current && $current->plan && $current->status === 'active' && (float) $current->price > 0
            && ! ($current->renews_at && $current->renews_at->isPast());
    }

    private function downgrade(?string $until, ?string $availableFrom): array
    {
        return $this->result(self::BLOCKED, 'downgrade_not_allowed',
            $until ? "You can switch to this plan after your current plan ends on {$until}." : 'You can switch to this plan after your current plan ends.',
            $availableFrom);
    }

    private function rank(SubscriptionPlan $plan): array
    {
        return [(int) $plan->sort_order, (float) $plan->monthly_price];
    }

    private function result(string $type, ?string $code = null, ?string $message = null, ?string $availableFrom = null): array
    {
        return ['type' => $type, 'code' => $code, 'message' => $message, 'available_from' => $type === self::BLOCKED ? $availableFrom : null];
    }
}
