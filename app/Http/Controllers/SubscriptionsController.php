<?php

namespace App\Http\Controllers;

use App\Models\Feature;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SubscriptionsController extends Controller
{
    public function index(Request $request)
    {
        if ($request->isMethod('post')) {

            $action = $request->input('action', 'store');

            if ($action === 'delete') {
                SubscriptionPlan::where('id', $request->input('id'))->delete();

                return redirect()->route('subscription')->with('success', 'Plan deleted successfully.');
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
                    'name' => ['required', 'string', 'max:50'],
                    'monthly_price' => ['required', 'numeric', 'min:0'],
                    'yearly_price' => ['required', 'numeric', 'min:0'],
                    'trial_days' => ['nullable', 'integer', 'min:0'],
                    'features' => $featuresRule,
                ], [
                    'features.required' => 'Add at least one feature.',
                ]);

                $plan = SubscriptionPlan::findOrFail($validated['id']);

                $plan->update([
                    'name' => $validated['name'],
                    // Keep the slug in sync with the name. Excluding the row's
                    // own id means an unchanged name keeps the same slug, and a
                    // rename updates it (staying table-unique).
                    'slug' => $this->uniqueSlug($validated['name'], (int) $validated['id']),
                    'monthly_price' => $validated['monthly_price'],
                    'yearly_price' => $validated['yearly_price'],
                    // Blank / 0 = no free trial on this plan.
                    'trial_days' => $validated['trial_days'] ?: null,
                    'features' => $this->splitFeatures($validated['features']),
                ]);

                // Keep the comparison-table entitlements (plan_feature pivot)
                // in sync with whatever was checked/typed in the feature grid.
                $this->syncComparisonFeatures($plan, $request->input('plan_features', []));

                return redirect()->route('subscription')->with('success', 'Plan updated successfully.');
            }

            $validated = $request->validate([
                'name' => ['required', 'string', 'max:50'],
                'monthly_price' => ['required', 'numeric', 'min:0'],
                'yearly_price' => ['required', 'numeric', 'min:0'],
                'trial_days' => ['nullable', 'integer', 'min:0'],
                'features' => $featuresRule,
            ], [
                'features.required' => 'Add at least one feature.',
            ]);

            $plan = SubscriptionPlan::create([
                'name' => $validated['name'],
                'slug' => $this->uniqueSlug($validated['name']),
                'monthly_price' => $validated['monthly_price'],
                'yearly_price' => $validated['yearly_price'],
                'trial_days' => $validated['trial_days'] ?: null,
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

            return redirect()->route('subscription')->with('success', 'New plan added successfully.');
        }

        $plans = SubscriptionPlan::orderBy('sort_order')->get();

        // Real-time headcount per plan, keyed by plan_id, for the stat cards.
        $userCountsByPlan = User::whereNotNull('plan_id')
            ->selectRaw('plan_id, count(*) as total')
            ->groupBy('plan_id')
            ->pluck('total', 'plan_id');

        // MRR from currently active (billed) subscriptions only; trials aren't
        // paying yet. Yearly plans are normalized to a monthly figure.
        $mrr = Subscription::where('status', 'active')
            ->get()
            ->sum(fn (Subscription $sub) => $sub->billing_cycle === 'yearly' ? $sub->price / 12 : $sub->price);

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

        return view('subscription.index', compact('plans', 'userCountsByPlan', 'mrr', 'featureGroups', 'planFeatureMatrix'));
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

        while (
            SubscriptionPlan::where('slug', $slug)
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base.'_'.$i++;
        }

        return $slug;
    }
}
