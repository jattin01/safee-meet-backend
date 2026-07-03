<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class SubscriptionsController extends Controller
{
    public function index(Request $request)
    {
        $defaultPlans = [
            [
                'name' => 'Basic',
                'monthly_price' => 9,
                'yearly_price' => 81,
                'features' => [
                    '6-Month support',
                    'Single end product use',
                    'Future upgrades included',
                    'Free for use in end products',
                ],
                'icon' => 'fa-dollar-sign',
                'color' => '#22c55e',
            ],
            [
                'name' => 'Premium',
                'monthly_price' => 19,
                'yearly_price' => 171,
                'features' => [
                    '6-Month support',
                    'Single end product use',
                    'Future upgrades included',
                    'Free for use in end products',
                ],
                'icon' => 'fa-rocket',
                'color' => '#ef4444',
            ],
            [
                'name' => 'Professional',
                'monthly_price' => 39,
                'yearly_price' => 351,
                'features' => [
                    '6-Month support',
                    'Single end product use',
                    'Future upgrades included',
                    'Free for use in end products',
                ],
                'icon' => 'fa-dollar-sign',
                'color' => '#ef4444',
            ],
        ];

        if ($request->isMethod('post')) {
            $action = $request->input('action', 'store');

            if ($action === 'delete') {
                $index = (int) $request->input('index');
                $plans = Session::get('subscription_plans', $defaultPlans);

                if (isset($plans[$index])) {
                    unset($plans[$index]);
                    $plans = array_values($plans);
                    Session::put('subscription_plans', $plans);
                }

                return redirect()->route('subscription')->with('success', 'Plan deleted successfully.');
            }

            if ($action === 'update') {
                $validated = $request->validate([
                    'index' => ['required', 'integer', 'min:0'],
                    'name' => ['required', 'string', 'max:50'],
                    'monthly_price' => ['required', 'numeric', 'min:0'],
                    'yearly_price' => ['required', 'numeric', 'min:0'],
                    'features' => ['nullable', 'string'],
                ]);

                $features = array_values(array_filter(array_map('trim', preg_split('/\r\n|[\r\n]/', $validated['features'] ?? ''))));
                $plans = Session::get('subscription_plans', $defaultPlans);

                if (isset($plans[$validated['index']])) {
                    $plans[$validated['index']]['name'] = $validated['name'];
                    $plans[$validated['index']]['monthly_price'] = (float) $validated['monthly_price'];
                    $plans[$validated['index']]['yearly_price'] = (float) $validated['yearly_price'];
                    $plans[$validated['index']]['features'] = $features ?: ['Custom plan features'];
                    Session::put('subscription_plans', $plans);
                }

                return redirect()->route('subscription')->with('success', 'Plan updated successfully.');
            }

            $validated = $request->validate([
                'name' => ['required', 'string', 'max:50'],
                'monthly_price' => ['required', 'numeric', 'min:0'],
                'yearly_price' => ['required', 'numeric', 'min:0'],
                'features' => ['nullable', 'string'],
            ]);

            $features = array_values(array_filter(array_map('trim', preg_split('/\r\n|[\r\n]/', $validated['features'] ?? ''))));

            $plans = Session::get('subscription_plans', $defaultPlans);
            $plans[] = [
                'name' => $validated['name'],
                'monthly_price' => (float) $validated['monthly_price'],
                'yearly_price' => (float) $validated['yearly_price'],
                'features' => $features ?: ['Custom plan features'],
                'icon' => 'fa-crown',
                'color' => '#ef4444',
            ];

            Session::put('subscription_plans', $plans);

            return redirect()->route('subscription')->with('success', 'New plan added successfully.');
        }

        $plans = Session::get('subscription_plans', $defaultPlans);

        return view('subscription.index', compact('plans'));
    }
}