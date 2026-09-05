<?php

use App\Models\Feature;
use App\Models\Meeting;
use App\Models\SearchHistory;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use App\Services\PlanEntitlements;
use Laravel\Sanctum\Sanctum;

function snapshotPlan(string $slug, string $pinLimit, string $meetingLimit): SubscriptionPlan
{
    $plan = SubscriptionPlan::create([
        'name' => str($slug)->headline(),
        'slug' => $slug,
        'monthly_price' => 0,
        'yearly_price' => 0,
        'trial_days' => 7,
        'features' => [],
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $definitions = [
        ['pin_search', 'PIN Search', 'limit', $pinLimit],
        ['meeting_history', 'Meeting History', 'limit', $meetingLimit],
        ['level1_verification', 'Level 1 Verification', 'boolean', null],
    ];

    foreach ($definitions as [$featureSlug, $name, $type, $value]) {
        $feature = Feature::firstOrCreate(
            ['slug' => $featureSlug],
            ['name' => $name, 'type' => $type, 'group' => 'Test', 'is_active' => true],
        );

        // included is deliberately false: boolean snapshots are based on row
        // existence, while limits use the pivot value.
        $plan->comparisonFeatures()->attach($feature->id, [
            'included' => false,
            'value' => $value,
        ]);
    }

    return $plan;
}

it('stores the selected plan feature matrix as an immutable subscription snapshot', function () {
    $user = User::factory()->create();
    $plan = snapshotPlan('basic_snapshot', '3', '8');
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/subscriptions/subscribe', [
        'plan_slug' => $plan->slug,
        'billing_cycle' => 'monthly',
    ])->assertCreated();

    $snapshot = UserSubscription::where('user_id', $user->id)->sole();

    expect($snapshot->safee_pin_search)->toBe('3')
        ->and($snapshot->safee_pin_search_remaining)->toBe(3)
        ->and($snapshot->meeting_history)->toBe('8')
        ->and($snapshot->level_1_verification)->toBeTrue()
        ->and($snapshot->qr_generation)->toBeFalse();

    $pinFeature = Feature::where('slug', 'pin_search')->sole();
    $plan->comparisonFeatures()->updateExistingPivot($pinFeature->id, ['value' => '99']);

    expect($snapshot->fresh()->safee_pin_search)->toBe('3');
});

it('discontinues the old snapshot and gives an upgrade a fresh usage allowance', function () {
    $user = User::factory()->create();
    $basic = snapshotPlan('basic_history', '3', '3');
    $plus = snapshotPlan('plus_history', '8', '8');
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/subscriptions/subscribe', [
        'plan_slug' => $basic->slug,
        'billing_cycle' => 'monthly',
    ])->assertCreated();

    $oldSnapshot = UserSubscription::where('user_id', $user->id)->sole();
    $oldSnapshot->update(['safee_pin_search_remaining' => 0]);
    $searchedUsers = User::factory()->count(3)->create();

    foreach ($searchedUsers as $searchedUser) {
        SearchHistory::create([
            'searcher_id' => $user->id,
            'user_subscription_id' => $oldSnapshot->id,
            'found_user_id' => $searchedUser->id,
            'query' => 'test',
            'method' => 'pin',
        ]);
    }

    $this->postJson('/api/v1/subscriptions/subscribe', [
        'plan_slug' => $plus->slug,
        'billing_cycle' => 'monthly',
    ])->assertCreated();

    $snapshots = UserSubscription::where('user_id', $user->id)->orderBy('id')->get();
    $newSnapshot = $snapshots->last();

    expect($snapshots)->toHaveCount(2)
        ->and($snapshots->first()->status)->toBe('cancelled')
        ->and($snapshots->first()->cancelled_at)->not->toBeNull()
        ->and($snapshots->first()->safee_pin_search_remaining)->toBe(0)
        ->and($newSnapshot->status)->toBe('trial')
        ->and($newSnapshot->safee_pin_search)->toBe('8')
        ->and($newSnapshot->safee_pin_search_remaining)->toBe(8)
        ->and(SearchHistory::where('user_subscription_id', $oldSnapshot->id)->count())->toBe(3)
        ->and(SearchHistory::where('user_subscription_id', $newSnapshot->id)->count())->toBe(0)
        ->and(app(PlanEntitlements::class)->numericLimit($user->fresh(), 'pin_search'))->toBe(8);
});

it('decrements remaining searches and does not charge a repeated member', function () {
    $searcher = User::factory()->create();
    $targets = User::factory()->count(3)->create();
    $targets->each(fn (User $target, int $index) => $target->update([
        'safee_pin' => 'SM-SEARCH-'.($index + 1),
        'status' => 'active',
    ]));
    $plan = snapshotPlan('two_searches', '2', '8');
    Sanctum::actingAs($searcher);

    $this->postJson('/api/v1/subscriptions/subscribe', [
        'plan_slug' => $plan->slug,
        'billing_cycle' => 'monthly',
    ])->assertCreated();

    $snapshot = UserSubscription::where('user_id', $searcher->id)->sole();

    $this->getJson('/api/v1/members/search?pin=SM-SEARCH-1')
        ->assertOk()
        ->assertJsonPath('success', true);
    expect($snapshot->fresh()->safee_pin_search_remaining)->toBe(1);

    $this->getJson('/api/v1/members/search?pin=SM-SEARCH-1')
        ->assertOk()
        ->assertJsonPath('success', true);
    expect($snapshot->fresh()->safee_pin_search_remaining)->toBe(1);

    $this->getJson('/api/v1/members/search?pin=SM-SEARCH-2')
        ->assertOk()
        ->assertJsonPath('success', true);
    expect($snapshot->fresh()->safee_pin_search_remaining)->toBe(0);

    $this->getJson('/api/v1/members/search?pin=SM-SEARCH-3')
        ->assertOk()
        ->assertJsonPath('success', false)
        ->assertJsonPath('required_feature', 'pin_search');

    expect(SearchHistory::where('user_subscription_id', $snapshot->id)->count())->toBe(2);
});

it('links meetings to the active snapshot and enforces its numeric allowance', function () {
    $host = User::factory()->create();
    $guests = User::factory()->count(2)->create();
    $plan = snapshotPlan('one_meeting', '3', '1');
    Sanctum::actingAs($host);

    $this->postJson('/api/v1/subscriptions/subscribe', [
        'plan_slug' => $plan->slug,
        'billing_cycle' => 'monthly',
    ])->assertCreated();

    $snapshot = UserSubscription::where('user_id', $host->id)->sole();
    $date = now()->addDay()->toDateString();

    $this->postJson('/api/v1/meetings', [
        'guest_user_id' => $guests[0]->id,
        'meeting_date' => $date,
        'meeting_time' => '16:30',
        'location' => 'Central Cafe',
    ])->assertCreated();

    expect(Meeting::where('user_subscription_id', $snapshot->id)->count())->toBe(1);

    $this->postJson('/api/v1/meetings', [
        'guest_user_id' => $guests[1]->id,
        'meeting_date' => $date,
        'meeting_time' => '17:00',
        'location' => 'Central Cafe',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('meeting_history');
});

it('keeps the old subscription active when an upgrade fails validation', function () {
    $user = User::factory()->create();
    $basic = snapshotPlan('working_basic', '3', '3');
    $paid = snapshotPlan('paid_upgrade', '8', '8');
    $paid->update(['monthly_price' => 25, 'yearly_price' => 250, 'trial_days' => null]);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/subscriptions/subscribe', [
        'plan_slug' => $basic->slug,
        'billing_cycle' => 'monthly',
    ])->assertCreated();

    $oldSnapshot = UserSubscription::where('user_id', $user->id)->sole();

    $this->postJson('/api/v1/subscriptions/subscribe', [
        'plan_slug' => $paid->slug,
        'billing_cycle' => 'monthly',
    ])->assertUnprocessable();

    expect($oldSnapshot->fresh()->status)->toBe('trial')
        ->and(UserSubscription::where('user_id', $user->id)->count())->toBe(1);
});
