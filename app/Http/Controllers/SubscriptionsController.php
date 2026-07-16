<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;

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

            if ($action === 'update') {
                $validated = $request->validate([
                    'id' => ['required', 'integer', 'exists:subscription_plans,id'],
                    'name' => ['required', 'string', 'max:50'],
                    'monthly_price' => ['required', 'numeric', 'min:0'],
                    'yearly_price' => ['required', 'numeric', 'min:0'],
                    'features' => ['nullable', 'string'],
                ]);

                $features = array_values(array_filter(array_map('trim', preg_split('/\r\n|[\r\n]/', $validated['features'] ?? ''))));

                SubscriptionPlan::where('id', $validated['id'])->update([
                    'name' => $validated['name'],
                    'monthly_price' => $validated['monthly_price'],
                    'yearly_price' => $validated['yearly_price'],
                    'features' => $features ?: ['Custom plan features'],
                ]);

                return redirect()->route('subscription')->with('success', 'Plan updated successfully.');
            }

            $validated = $request->validate([
                'name' => ['required', 'string', 'max:50'],
                'monthly_price' => ['required', 'numeric', 'min:0'],
                'yearly_price' => ['required', 'numeric', 'min:0'],
                'features' => ['nullable', 'string'],
            ]);

            $features = array_values(array_filter(array_map('trim', preg_split('/\r\n|[\r\n]/', $validated['features'] ?? ''))));

            SubscriptionPlan::create([
                'name' => $validated['name'],
                'monthly_price' => $validated['monthly_price'],
                'yearly_price' => $validated['yearly_price'],
                'features' => $features ?: ['Custom plan features'],
                'icon' => 'fa-crown',
                'color' => '#ef4444',
                'sort_order' => (int) SubscriptionPlan::max('sort_order') + 1,
            ]);

            return redirect()->route('subscription')->with('success', 'New plan added successfully.');
        }

        $plans = SubscriptionPlan::orderBy('sort_order')->get();

        return view('subscription.index', compact('plans'));
    }
}
