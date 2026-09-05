<?php

use App\Models\Admin;
use App\Models\Meeting;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createDashboardAdmin(string $roleSlug, string $email): Admin
{
    $role = Role::create([
        'name' => $roleSlug === 'super_admin' ? 'Super Admin' : 'Admin',
        'slug' => $roleSlug,
        'status' => true,
    ]);

    return Admin::create([
        'role_id' => $role->id,
        'name' => $role->name,
        'email' => $email,
        'password' => 'password',
        'status' => true,
    ]);
}

it('shows each dashboard only to its matching admin role', function () {
    $admin = createDashboardAdmin('admin', 'admin@example.com');
    $superAdmin = createDashboardAdmin('super_admin', 'super@example.com');

    $this->actingAs($admin, 'admin')
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Good Morning, Admin!');

    $this->actingAs($admin, 'admin')
        ->get('/super-admin/dashboard')
        ->assertForbidden();

    $this->actingAs($superAdmin, 'admin')
        ->get('/super-admin/dashboard')
        ->assertOk()
        ->assertSee('Good Morning, Super Admin!');

    $this->actingAs($superAdmin, 'admin')
        ->get('/dashboard')
        ->assertForbidden();
});

it('shows the Safee Meet internal-style login page', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('Welcome to Safee Meet')
        ->assertSee('Sign in to your administration dashboard');
});

it('redirects admins and super admins to their own dashboard after login', function () {
    createDashboardAdmin('admin', 'admin-login@example.com');

    $this->post('/login', [
        'email' => 'admin-login@example.com',
        'password' => 'password',
    ])->assertRedirect(route('admin.dashboard'));

    auth('admin')->logout();

    createDashboardAdmin('super_admin', 'super-login@example.com');

    $this->post('/login', [
        'email' => 'super-login@example.com',
        'password' => 'password',
    ])->assertRedirect(route('super-admin.dashboard'));
});

it('shows live meetings and recent users on the admin dashboard', function () {
    $admin = createDashboardAdmin('admin', 'dashboard-data@example.com');

    $host = User::factory()->create([
        'name' => 'Live Dashboard Host',
        'status' => 'active',
        'trust_score' => 91,
    ]);
    $guest = User::factory()->create([
        'name' => 'Live Dashboard Guest',
        'status' => 'active',
    ]);

    Meeting::create([
        'reference' => 'SM-LIVE-1',
        'host_user_id' => $host->id,
        'guest_user_id' => $guest->id,
        'meeting_date' => now()->addDay()->toDateString(),
        'meeting_time' => '10:30:00',
        'location' => 'Live Dashboard Cafe',
        'type' => 'coffee',
        'status' => 'scheduled',
        'trust_score_snapshot' => 93,
    ]);

    $this->actingAs($admin, 'admin')
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('SM-LIVE-1')
        ->assertSee('Live Dashboard Host')
        ->assertSee('Live Dashboard Guest')
        ->assertSee('Live Dashboard Cafe')
        ->assertDontSee('Alex Johnson');
});
