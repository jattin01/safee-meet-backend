<?php

namespace App\Http\Controllers;

use App\Models\AdminAuditLog;
use App\Models\Feature;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\AdminAuditLogger;
use App\Services\StripeService;
use App\Services\SubscriptionMetrics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SubscriptionsController extends Controller
{
    public function index(Request $request)
    {
        if ($request->isMethod('post')) {

            $action = $request->input('action', 'store');

            if ($action === 'toggle') {
                $plan = SubscriptionPlan::findOrFail($request->input('id'));
                $wasActive = (bool) $plan->is_active;

                // Deactivating only stops NEW purchases: the plan disappears
                // from the app's plans/comparison lists and subscribe()
                // rejects it. Existing subscribers keep their plan and keep
                // renewing on their current Stripe price.
                $plan->update(['is_active' => ! $wasActive]);

                $this->audit()->log(
                    $wasActive ? 'plan.deactivated' : 'plan.activated',
                    $plan,
                    ['is_active' => $wasActive],
                    ['is_active' => ! $wasActive],
                );

                return redirect()->route('subscription')->with('success', $wasActive
                    ? "Plan \"{$plan->name}\" deactivated. It is hidden from new users; existing subscribers are not affected."
                    : "Plan \"{$plan->name}\" activated. It is available to new users again.");
            }

            if ($action === 'move') {
                return $this->movePlan(
                    SubscriptionPlan::findOrFail($request->input('id')),
                    $request->input('direction') === 'up' ? 'up' : 'down',
                );
            }

            if ($action === 'delete') {
                $plan = SubscriptionPlan::findOrFail($request->input('id'));

                // Deleting would orphan users still on (and being billed for)
                // this plan, so it's only allowed once nobody is on it.
                $liveCount = Subscription::where('plan_id', $plan->id)->whereIn('status', ['trial', 'active'])->count();

                if ($liveCount > 0) {
                    return redirect()->route('subscription')
                        ->with('error', "This plan can't be deleted: {$liveCount} user(s) currently have an active or trial subscription on it.");
                }

                try {
                    app(StripeService::class)->archivePlanPrices($plan);
                } catch (\Throwable $e) {
                    return redirect()->route('subscription')
                        ->with('error', 'Plan not deleted — archiving its Stripe prices failed: '.$e->getMessage());
                }

                $snapshot = $this->auditSnapshot($plan);

                // Soft delete: gone from the admin list and the app, but its
                // name stays on subscription history, payments and reports.
                $plan->update(['is_active' => false]);
                $plan->delete();

                $this->audit()->log('plan.deleted', $plan, $snapshot, null);

                return redirect()->route('subscription')->with('success', 'Plan deleted. Past subscriptions and reports still show its name.');
            }

            $featuresRule = [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    if (empty($this->splitFeatures($value))) {
                        $fail('Add at least one feature.');
                    }
                },
            ];

            if ($action === 'update') {
                $validated = $request->validate([
                    'id' => ['required', 'integer', 'exists:subscription_plans,id'],
                    ...$this->planRules($featuresRule),
                ], $this->planMessages());

                $plan = SubscriptionPlan::findOrFail($validated['id']);
                $before = $this->auditSnapshot($plan);

                $plan->update([
                    'name' => $validated['name'],
                    // The slug is NOT changed on rename: the app sends it to
                    // subscribe, so changing it would break apps that cached
                    // the old one. It is set once, when the plan is created.
                    // Who this plan is shown to; 'both' (the default) keeps it
                    // visible to normal and employer accounts alike.
                    'account_type' => $validated['account_type'] ?? 'both',
                    'monthly_price' => $validated['monthly_price'],
                    // Blank = no strikethrough MRP shown for this cycle.
                    'monthly_original_price' => ($validated['monthly_original_price'] ?? null) ?: null,
                    'yearly_price' => $validated['yearly_price'],
                    'yearly_original_price' => ($validated['yearly_original_price'] ?? null) ?: null,
                    // Blank / 0 = no free trial on this plan.
                    'trial_days' => ($validated['trial_days'] ?? null) ?: null,
                    'features' => $this->splitFeatures($validated['features']),
                ]);

                // Keep the comparison-table entitlements (plan_feature pivot)
                // in sync with whatever was checked/typed in the feature grid.
                $this->syncComparisonFeatures($plan, $request->input('plan_features', []));

                [$old, $new] = $this->audit()->diff($before, $this->auditSnapshot($plan->refresh()));

                if ($new) {
                    $this->audit()->log('plan.updated', $plan, $old, $new);
                }

                if ($error = $this->syncStripePrices($plan)) {
                    return redirect()->route('subscription')->with('error', $error);
                }

                return redirect()->route('subscription')->with('success', 'Plan updated successfully.');
            }

            $validated = $request->validate($this->planRules($featuresRule), $this->planMessages());

            $plan = SubscriptionPlan::create([
                'name' => $validated['name'],
                'slug' => $this->uniqueSlug($validated['name']),
                // Who this plan is shown to; 'both' (the default) keeps it
                // visible to normal and employer accounts alike.
                'account_type' => $validated['account_type'] ?? 'both',
                'monthly_price' => $validated['monthly_price'],
                // Blank = no strikethrough MRP shown for this cycle.
                'monthly_original_price' => ($validated['monthly_original_price'] ?? null) ?: null,
                'yearly_price' => $validated['yearly_price'],
                'yearly_original_price' => ($validated['yearly_original_price'] ?? null) ?: null,
                'trial_days' => ($validated['trial_days'] ?? null) ?: null,
                'features' => $this->splitFeatures($validated['features']),
                'icon' => 'fa-crown',
                'color' => '#ef4444',
                'sort_order' => (int) SubscriptionPlan::max('sort_order') + 1,
                'is_active' => true,
            ]);

            // Populate the comparison-table entitlements (plan_feature pivot)
            // right away, so a brand-new plan shows up correctly on the
            // side-by-side comparison instead of every cell defaulting to "No".
            $this->syncComparisonFeatures($plan, $request->input('plan_features', []));

            $this->audit()->log('plan.created', $plan, null, $this->auditSnapshot($plan->refresh()));

            if ($error = $this->syncStripePrices($plan)) {
                return redirect()->route('subscription')->with('error', $error);
            }

            return redirect()->route('subscription')->with('success', 'New plan added successfully.');
        }

        $plans = SubscriptionPlan::orderBy('sort_order')->get();

        // Users currently ON each plan (running trial/active subscription),
        // keyed by plan_id. users.plan_id is not used: it keeps pointing at
        // a plan after it expires or is cancelled.
        $userCountsByPlan = Subscription::whereIn('status', ['trial', 'active'])
            ->selectRaw('plan_id, COUNT(DISTINCT user_id) as total')
            ->groupBy('plan_id')
            ->pluck('total', 'plan_id');

        // Recent plan changes (price, features, status, order, delete) for
        // the change-history panel — ADM-012 / ADM-019.
        $planHistory = AdminAuditLog::with('admin:id,name')
            ->where('target_type', 'SubscriptionPlan')
            ->latest('occurred_at')
            ->limit(50)
            ->get();

        $planNames = SubscriptionPlan::withTrashed()
            ->whereIn('id', $planHistory->pluck('target_id')->unique())
            ->pluck('name', 'id');

        // Same MRR as the Revenue page (see SubscriptionMetrics).
        $mrr = app(SubscriptionMetrics::class)->recurringRevenue()['mrr'];

        // Comparison-feature catalog, grouped for the checklist on the
        // create/edit forms (same source of truth as Admin\FeatureController).
        $featureGroups = Feature::active()->orderBy('sort_order')->get()->groupBy('group');

        // planFeatureMatrix[plan_id][feature_id] = ['included' => bool, 'value' => string|null]
        // Used to pre-fill each plan's checklist when its edit modal opens.
        $planFeatureMatrix = [];
        foreach (DB::table('plan_feature')->get() as $row) {
            $planFeatureMatrix[$row->plan_id][$row->feature_id] = [
                'included' => (bool) $row->included,
                'value' => $row->value,
            ];
        }

        return view('subscription.index', compact('plans', 'userCountsByPlan', 'mrr', 'featureGroups', 'planFeatureMatrix', 'planHistory', 'planNames'));
    }

    /** Highest price an admin can set, in dollars (Stripe-safe, catches typos). */
    private const MAX_PRICE = 99999.99;

    /** Longest free trial an admin can set, in days. */
    private const MAX_TRIAL_DAYS = 365;

    /**
     * Create/edit validation. Prices: at most 2 decimals (Stripe bills in
     * cents, so 9.999 would be silently rounded) and capped at MAX_PRICE.
     */
    private function planRules(array $featuresRule): array
    {
        $price = ['numeric', 'decimal:0,2', 'min:0', 'max:'.self::MAX_PRICE];

        return [
            'name' => ['required', 'string', 'max:50'],
            'account_type' => ['nullable', 'in:normal,employer,both'],
            'monthly_price' => ['required', ...$price],
            'monthly_original_price' => ['nullable', ...$price, 'gte:monthly_price'],
            'yearly_price' => ['required', ...$price],
            'yearly_original_price' => ['nullable', ...$price, 'gte:yearly_price'],
            'trial_days' => ['nullable', 'integer', 'min:0', 'max:'.self::MAX_TRIAL_DAYS],
            'features' => $featuresRule,
        ];
    }

    private function planMessages(): array
    {
        return [
            'features.required' => 'Add at least one feature.',
            'monthly_original_price.gte' => 'The flat (MRP) monthly price must be greater than or equal to the discounted price.',
            'yearly_original_price.gte' => 'The flat (MRP) yearly price must be greater than or equal to the discounted price.',
            '*.decimal' => 'Prices can have at most 2 decimal places (e.g. 9.99).',
        ];
    }

    /**
     * Swaps a plan with its neighbour in the display order (the order the
     * app's plans and comparison screens use). Orders are first renumbered
     * 1..n so gaps or duplicates from older data can't break the swap.
     */
    private function movePlan(SubscriptionPlan $plan, string $direction)
    {
        DB::transaction(function () use ($plan, $direction) {
            $ordered = SubscriptionPlan::orderBy('sort_order')->orderBy('id')->lockForUpdate()->get();

            foreach ($ordered->values() as $position => $row) {
                if ((int) $row->sort_order !== $position + 1) {
                    $row->update(['sort_order' => $position + 1]);
                }
            }

            $index = $ordered->search(fn ($row) => $row->id === $plan->id);
            $neighbour = $ordered->get($direction === 'up' ? $index - 1 : $index + 1);

            if (! $neighbour) {
                return;
            }

            $mine = $index + 1;
            $theirs = $direction === 'up' ? $index : $index + 2;

            $plan->update(['sort_order' => $theirs]);
            $neighbour->update(['sort_order' => $mine]);

            $this->audit()->log('plan.reordered', $plan, ['sort_order' => $mine], ['sort_order' => $theirs]);
        });

        return redirect()->route('subscription')->with('success', "Plan \"{$plan->name}\" moved {$direction}.");
    }

    private function audit(): AdminAuditLogger
    {
        return app(AdminAuditLogger::class);
    }

    /**
     * The plan fields an admin can change, plus its feature grid, in the
     * shape stored on audit log rows (old_values / new_values).
     */
    private function auditSnapshot(SubscriptionPlan $plan): array
    {
        return [
            'name' => $plan->name,
            'slug' => $plan->slug,
            'account_type' => $plan->account_type,
            'monthly_price' => (string) $plan->monthly_price,
            'monthly_original_price' => $plan->monthly_original_price !== null ? (string) $plan->monthly_original_price : null,
            'yearly_price' => (string) $plan->yearly_price,
            'yearly_original_price' => $plan->yearly_original_price !== null ? (string) $plan->yearly_original_price : null,
            'trial_days' => $plan->trial_days,
            'is_active' => (bool) $plan->is_active,
            'features' => array_values((array) $plan->features),
            'plan_features' => DB::table('plan_feature')
                ->where('plan_id', $plan->id)
                ->orderBy('feature_id')
                ->get(['feature_id', 'included', 'value'])
                ->map(fn ($row) => ['feature_id' => $row->feature_id, 'included' => (bool) $row->included, 'value' => $row->value])
                ->all(),
        ];
    }

    /**
     * Sync a plan's comparison-table entitlements (plan_feature pivot) from
     * the posted feature checklist. Mirrors Admin\FeatureController::saveMatrix
     * so the "Create/Edit Plan" form and the "/features" matrix page keep the
     * pivot table in a consistent shape.
     *
     * $input is expected as [feature_id => ['included' => '1'?, 'value' => string?]].
     */
    private function syncComparisonFeatures(SubscriptionPlan $plan, array $input): void
    {
        $sync = [];

        foreach (Feature::all() as $feature) {
            $cell = $input[$feature->id] ?? [];

            if ($feature->type === 'limit') {
                $value = trim((string) ($cell['value'] ?? ''));

                if ($value !== '') {
                    $sync[$feature->id] = ['included' => true, 'value' => $value];
                }
            } elseif (!empty($cell['included'])) {
                $sync[$feature->id] = ['included' => true, 'value' => null];
            }
        }

        $plan->comparisonFeatures()->sync($sync);
    }

    /**
     * Point the plan's Stripe Prices at the admin-set amounts, so users are
     * charged exactly the (discounted) price shown in the app. Returns an
     * error message for the admin when Stripe couldn't be reached — the next
     * save, or the next subscribe on this plan, retries the sync.
     */
    private function syncStripePrices(SubscriptionPlan $plan): ?string
    {
        try {
            foreach (['monthly', 'yearly'] as $billingCycle) {
                app(StripeService::class)->syncPlanPrice($plan, $billingCycle);
            }
        } catch (\Throwable $e) {
            return 'Plan saved, but syncing its price to Stripe failed: '.$e->getMessage();
        }

        return null;
    }

    /**
     * Split the "one per line" features textarea into a clean, non-empty list.
     */
    private function splitFeatures(string $raw): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\r\n|[\r\n]/', $raw))));
    }

    /**
     * Build a URL-safe, table-unique slug from a plan name. $ignoreId lets an
     * update exclude the row being edited from the uniqueness check, so an
     * unchanged name keeps its existing slug instead of getting a "_2" suffix.
     */
    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name, '_') ?: 'plan';
        $slug = $base;
        $i = 2;

        // withTrashed: a deleted plan still holds its slug in the unique index.
        while (
            SubscriptionPlan::withTrashed()->where('slug', $slug)
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base.'_'.$i++;
        }

        return $slug;
    }
}
