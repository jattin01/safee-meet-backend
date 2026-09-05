<?php

use App\Models\Admin;
use App\Models\Meeting;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;

it('shows exhausted previous plans and the current upgraded plan as a plan journey', function () {
    $role = Role::create([
        'name' => 'Admin',
        'slug' => 'admin',
        'status' => true,
    ]);
    $admin = Admin::create([
        'role_id' => $role->id,
        'name' => 'Usage Admin',
        'email' => 'usage-admin@example.com',
        'password' => 'password',
        'status' => true,
    ]);
    $user = User::factory()->create();
    $guest = User::factory()->create();
    $oldPlan = SubscriptionPlan::create([
        'name' => 'Basic History',
        'slug' => 'basic_history_admin',
        'monthly_price' => 5,
        'yearly_price' => 50,
        'trial_days' => null,
        'features' => [],
        'sort_order' => 1,
        'is_active' => true,
    ]);
    $currentPlan = SubscriptionPlan::create([
        'name' => 'Plus Upgrade',
        'slug' => 'plus_upgrade_admin',
        'monthly_price' => 10,
        'yearly_price' => 100,
        'trial_days' => null,
        'features' => [],
        'sort_order' => 1,
        'is_active' => true,
    ]);
    $oldSubscription = Subscription::create([
        'user_id' => $user->id,
        'plan_id' => $oldPlan->id,
        'price' => 5,
        'billing_cycle' => 'monthly',
        'status' => 'cancelled',
        'started_at' => now()->subMonths(2),
        'renews_at' => now()->subMonth(),
        'cancelled_at' => now()->subMonth(),
    ]);
    UserSubscription::create([
        'subscription_id' => $oldSubscription->subscription_id,
        'user_id' => $user->id,
        'plan_id' => $oldPlan->id,
        'price' => 5,
        'billing_cycle' => 'monthly',
        'status' => 'cancelled',
        'started_at' => $oldSubscription->started_at,
        'renews_at' => $oldSubscription->renews_at,
        'cancelled_at' => $oldSubscription->cancelled_at,
        'safee_pin_search' => '3',
        'safee_pin_search_remaining' => 0,
        'meeting_history' => '3',
        'level_1_verification' => true,
        'qr_generation' => true,
    ]);

    $subscription = Subscription::create([
        'user_id' => $user->id,
        'plan_id' => $currentPlan->id,
        'price' => 10,
        'billing_cycle' => 'monthly',
        'status' => 'active',
        'started_at' => now(),
        'renews_at' => now()->addMonth(),
    ]);
    $snapshot = UserSubscription::create([
        'subscription_id' => $subscription->subscription_id,
        'user_id' => $user->id,
        'plan_id' => $currentPlan->id,
        'price' => 10,
        'billing_cycle' => 'monthly',
        'status' => 'active',
        'started_at' => $subscription->started_at,
        'renews_at' => $subscription->renews_at,
        'safee_pin_search' => '8',
        'safee_pin_search_remaining' => 5,
        'meeting_history' => '8',
        'level_1_verification' => true,
        'level_2_clearance' => true,
        'qr_generation' => true,
        'premium_badge' => true,
    ]);
    Meeting::create([
        'host_user_id' => $user->id,
        'guest_user_id' => $guest->id,
        'user_subscription_id' => $snapshot->id,
        'meeting_date' => now()->addDay()->toDateString(),
        'meeting_time' => '12:00',
        'location' => 'Test location',
        'status' => 'scheduled',
    ]);

    $response = $this->actingAs($admin, 'admin')->get(route('users.show', $user->id));

    $response->assertOk()
        ->assertSee('Plan Journey', false)
        ->assertSee('plans recorded')
        ->assertSeeInOrder(['Plus Upgrade', 'Current plan', 'Active'])
        ->assertSeeInOrder(['Basic History', 'Previous plan', 'Cancelled'])
        ->assertSeeInOrder(['PIN Searches', 'Meetings'])
        ->assertSeeInOrder(['Plus Upgrade', '8', '3', '5', '8', '1', '7'])
        ->assertSeeInOrder(['Basic History', '3', '3', '0', '3', '0', '3'])
        ->assertSee('Feature Snapshot', false)
        ->assertSee('4/8 included')
        ->assertSee('2/8 included')
        ->assertSee('Level 2 Clearance')
        ->assertSee('Premium Badge');
});
