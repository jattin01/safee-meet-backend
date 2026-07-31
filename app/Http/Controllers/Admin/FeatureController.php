<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Feature;
use App\Models\SubscriptionPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FeatureController extends Controller
{
    public function index(): View
    {
        $features = Feature::orderBy('sort_order')->get();
        $plans = SubscriptionPlan::active()->orderBy('sort_order')->get(['id', 'slug', 'name']);

        // matrix[plan_id][feature_id] = ['included' => bool, 'value' => string|null]
        $matrix = [];
        foreach (DB::table('plan_feature')->get() as $row) {
            $matrix[$row->plan_id][$row->feature_id] = [
                'included' => (bool) $row->included,
                'value' => $row->value,
            ];
        }

        return view('features.index', [
            'features' => $features,
            'plans' => $plans,
            'matrix' => $matrix,
            'groups' => $features->groupBy('group'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::in(['boolean', 'limit'])],
            'group' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        Feature::create($validated + [
            'slug' => $this->uniqueSlug($validated['name']),
            'sort_order' => (int) Feature::max('sort_order') + 1,
            'is_active' => true,
        ]);

        return redirect()->route('features.index')->with('success', 'Feature created successfully.');
    }

    public function update(Request $request, Feature $feature): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::in(['boolean', 'limit'])],
            'group' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ]);

        // slug stays stable (the comparison API / clients key on it).
        $feature->update($validated);

        return redirect()->route('features.index')->with('success', 'Feature updated successfully.');
    }

    public function destroy(Feature $feature): RedirectResponse
    {
        // plan_feature rows cascade-delete via the FK.
        $feature->delete();

        return redirect()->route('features.index')->with('success', 'Feature deleted successfully.');
    }

    /**
     * Save the full plan × feature matrix. For each active plan we rebuild its
     * feature set from the posted grid: boolean cells are included when
     * checked, limit cells when a value is entered. sync() also detaches any
     * feature no longer assigned.
     *
     * A blank limit cell means "no change", not "remove" — an already-saved
     * value (e.g. "Unlimited") is carried forward instead of being detached.
     * Leaving a text field empty silently deleted a plan's entitlement
     * (e.g. Basic's pin_search grant) with no way to tell that apart from an
     * intentional removal; there is currently no UI action to explicitly
     * remove a limit feature from a plan.
     */
    public function saveMatrix(Request $request): RedirectResponse
    {
        $matrix = $request->input('matrix', []);
        $plans = SubscriptionPlan::active()->get();
        $features = Feature::all();

        $existing = [];
        foreach (DB::table('plan_feature')->get() as $row) {
            $existing[$row->plan_id][$row->feature_id] = [
                'included' => (bool) $row->included,
                'value' => $row->value,
            ];
        }

        DB::transaction(function () use ($matrix, $plans, $features, $existing) {
            foreach ($plans as $plan) {
                $sync = [];

                foreach ($features as $feature) {
                    $cell = $matrix[$plan->id][$feature->id] ?? [];

                    if ($feature->type === 'limit') {
                        $value = trim((string) ($cell['value'] ?? ''));

                        if ($value !== '') {
                            $sync[$feature->id] = ['included' => true, 'value' => $value];
                        } elseif ($prev = $existing[$plan->id][$feature->id] ?? null) {
                            $sync[$feature->id] = $prev;
                        }
                    } elseif (!empty($cell['included'])) {
                        $sync[$feature->id] = ['included' => true, 'value' => null];
                    }
                }

                $plan->comparisonFeatures()->sync($sync);
            }
        });

        return redirect()->route('features.index')->with('success', 'Feature matrix saved.');
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name, '_') ?: 'feature';
        $slug = $base;
        $i = 2;

        while (Feature::where('slug', $slug)->exists()) {
            $slug = $base.'_'.$i++;
        }

        return $slug;
    }
}
