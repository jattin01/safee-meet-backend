<?php

use App\Models\Admin;
use App\Models\Role;
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
