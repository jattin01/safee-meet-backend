<?php

use App\Models\Admin;
use App\Models\Payment;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserSubscription;

function createRevenueAdmin(): Admin
{
    $role = Role::create([
        'name' => 'Admin',
        'slug' => 'admin',
        'status' => true,
    ]);

    return Admin::create([
        'role_id' => $role->id,
        'name' => 'Revenue Admin',
        'email' => 'revenue-admin@example.com',
        'password' => 'password',
        'status' => true,
    ]);
}

function createRevenueTransaction(
    User $user,
    string $transactionId,
    string $status,
    string $createdAt,
    ?string $paidAt = null,
): Payment {
    $subscription = Subscription::create([
        'user_id' => $user->id,
        'price' => 29.99,
        'billing_cycle' => 'monthly',
        'status' => $status === 'succeeded' ? 'active' : 'incomplete',
        'started_at' => $createdAt,
        'renews_at' => now()->addMonth(),
    ]);

    UserSubscription::create([
        'subscription_id' => $subscription->subscription_id,
        'user_id' => $user->id,
        'price' => 29.99,
        'billing_cycle' => 'monthly',
        'status' => $subscription->status,
        'started_at' => $createdAt,
    ]);

    $payment = Payment::create([
        'user_id' => $user->id,
        'subscription_id' => $subscription->id,
        'stripe_payment_intent_id' => $transactionId,
        'amount' => 2999,
        'currency' => 'usd',
        'status' => $status,
        'paid_at' => $paidAt,
    ]);

    $payment->forceFill([
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ])->saveQuietly();

    return $payment;
}

it('shows payment transactions newest first with user and subscription details', function () {
    $admin = createRevenueAdmin();
    $user = User::factory()->create([
        'name' => 'Revenue User',
        'phone' => '+15550001111',
    ]);

    createRevenueTransaction($user, 'pi_older', 'succeeded', '2026-09-01 09:00:00', '2026-09-01 09:05:00');
    createRevenueTransaction($user, 'pi_newer', 'pending', '2026-09-03 10:00:00');

    $this->actingAs($admin, 'admin')
        ->get(route('revenue'))
        ->assertOk()
        ->assertSee('Transactions')
        ->assertSee('Revenue User')
        ->assertSee('+15550001111')
        ->assertSee('$29.99')
        ->assertSeeInOrder(['pi_newer', 'pi_older']);
});

it('filters by payment status and an inclusive transaction date range', function () {
    $admin = createRevenueAdmin();
    $user = User::factory()->create();

    createRevenueTransaction($user, 'pi_before', 'succeeded', '2026-09-02 23:59:59', '2026-09-02 23:59:59');
    createRevenueTransaction($user, 'pi_in_range', 'pending', '2026-09-03 23:59:59');
    createRevenueTransaction($user, 'pi_wrong_status', 'failed', '2026-09-03 12:00:00');
    createRevenueTransaction($user, 'pi_after', 'pending', '2026-09-04 00:00:00');

    $this->actingAs($admin, 'admin')
        ->get(route('revenue', [
            'payment_status' => 'pending',
            'start_date' => '2026-09-03',
            'end_date' => '2026-09-03',
        ]))
        ->assertOk()
        ->assertSee('pi_in_range')
        ->assertDontSee('pi_before')
        ->assertDontSee('pi_wrong_status')
        ->assertDontSee('pi_after')
        ->assertSee('value="2026-09-03"', false)
        ->assertSee('value="pending" selected', false);
});

it('filters by username', function () {
    $admin = createRevenueAdmin();
    $alice = User::factory()->create(['name' => 'Alice Example']);
    $bob = User::factory()->create(['name' => 'Bob Example']);

    createRevenueTransaction($alice, 'pi_alice', 'succeeded', '2026-09-01 09:00:00', '2026-09-01 09:05:00');
    createRevenueTransaction($bob, 'pi_bob', 'succeeded', '2026-09-01 09:00:00', '2026-09-01 09:05:00');

    $this->actingAs($admin, 'admin')
        ->get(route('revenue', ['username' => 'Alice']))
        ->assertOk()
        ->assertSee('pi_alice')
        ->assertDontSee('pi_bob')
        ->assertSee('value="Alice"', false);
});

it('suggests usernames for the live-search dropdown', function () {
    $admin = createRevenueAdmin();
    $alice = User::factory()->create(['name' => 'Alice Example']);
    $bob = User::factory()->create(['name' => 'Bob Example']);

    createRevenueTransaction($alice, 'pi_alice', 'succeeded', '2026-09-01 09:00:00', '2026-09-01 09:05:00');
    createRevenueTransaction($bob, 'pi_bob', 'succeeded', '2026-09-01 09:00:00', '2026-09-01 09:05:00');

    $this->actingAs($admin, 'admin')
        ->getJson(route('revenue.usernamefilter', ['q' => 'ali']))
        ->assertOk()
        ->assertJson(['usernames' => ['Alice Example']]);
});

it('returns just the table fragment for ajax requests', function () {
    $admin = createRevenueAdmin();
    $user = User::factory()->create(['name' => 'Ajax User']);

    createRevenueTransaction($user, 'pi_ajax', 'succeeded', '2026-09-01 09:00:00', '2026-09-01 09:05:00');

    $response = $this->actingAs($admin, 'admin')
        ->get(route('revenue'), ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk()
        ->assertSee('pi_ajax');

    $response->assertDontSee('Revenue Analytics');
});

it('rejects unsupported filters', function () {
    $admin = createRevenueAdmin();

    $this->actingAs($admin, 'admin')
        ->from(route('revenue'))
        ->get(route('revenue', [
            'payment_status' => 'not-a-real-status',
            'start_date' => '2026-09-04',
            'end_date' => '2026-09-03',
        ]))
        ->assertRedirect(route('revenue'))
        ->assertSessionHasErrors(['payment_status', 'end_date']);
});
