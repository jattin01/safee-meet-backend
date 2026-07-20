<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Canonical SafeeMeet catalog. Idempotent — keyed on slug, so running it
     * repeatedly just re-asserts these five plans. Admin CRUD can tweak copy
     * afterwards; re-running this restores the baseline.
     *
     * pin_search_limit: null = unlimited. trial_days: null = no free period.
     */
    public function run(): void
    {
        $plans = [
            [
                'slug' => 'free_trial',
                'name' => 'Free Trial',
                'monthly_price' => 0.99,   // charged after the free trial ends
                'yearly_price' => 0.00,
                'trial_days' => 100,
                'pin_search_limit' => 3,
                'icon' => 'fa-shield-halved',
                'color' => '#6b7280',
                'sort_order' => 1,
                'features' => [
                    'Identity Registration',
                    'Level 1 Verification',
                    '3 SAFEE PIN Search / Chat',
                    'Basic Safety Tips',
                    'Community Guidelines Access',
                    'Limited Meeting History',
                ],
            ],
            [
                'slug' => 'basic_limited',
                'name' => 'Basic Limited',
                'monthly_price' => 4.99,
                'yearly_price' => 44.91,
                'trial_days' => null,
                'pin_search_limit' => 8,
                'icon' => 'fa-star',
                'color' => '#22c55e',
                'sort_order' => 2,
                'features' => [
                    'Everything in Free',
                    'Level 1 Verification',
                    '8 SAFEE PIN Search / Chat',
                    'Basic Safety Tips',
                    'Community Guidelines Access',
                    'Limited Meeting History',
                ],
            ],
            [
                'slug' => 'basic',
                'name' => 'Basic',
                'monthly_price' => 9.99,
                'yearly_price' => 83.92,
                'trial_days' => null,
                'pin_search_limit' => null, // unlimited
                'icon' => 'fa-star',
                'color' => '#299cdb',
                'sort_order' => 3,
                'features' => [
                    'Everything in Basic Limited',
                    'Verified Badge Display',
                    'Unlimited PIN Search / Chat',
                    'Priority Support',
                    'QR Code Generation',
                ],
            ],
            [
                'slug' => 'premium',
                'name' => 'Premium',
                'monthly_price' => 19.99,
                'yearly_price' => 167.92,
                'trial_days' => null,
                'pin_search_limit' => null,
                'icon' => 'fa-crown',
                'color' => '#DC131C',
                'sort_order' => 4,
                'features' => [
                    'Everything in Basic',
                    'Level 1 & 2 Clearance',
                    'Background Verification',
                    'Trust Score Calculation',
                    'Safety Score Analytics',
                    'Premium Badge',
                    'Priority Visibility',
                    'Trusted Contact Alerts',
                ],
            ],
            [
                'slug' => 'professional',
                'name' => 'Professional',
                'monthly_price' => 29.99,
                'yearly_price' => 251.92,
                'trial_days' => null,
                'pin_search_limit' => null,
                'icon' => 'fa-briefcase',
                'color' => '#f7b84b',
                'sort_order' => 5,
                'features' => [
                    'Everything in Premium',
                    'Professional Verification',
                    'Business Listing',
                    'API Access',
                    'Dedicated Account Manager',
                    'Custom Integrations',
                ],
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::updateOrCreate(
                ['slug' => $plan['slug']],
                $plan + ['is_active' => true],
            );
        }
    }
}
