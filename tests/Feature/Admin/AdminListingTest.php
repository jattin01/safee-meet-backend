<?php

use App\Models\Admin;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows the admins page and returns paginated admin data over ajax', function () {
    $role = Role::create([
        'name' => 'Admin',
        'slug' => 'admin',
        'status' => true,
    ]);

    $loggedInAdmin = Admin::create([
        'role_id' => $role->id,
        'name' => 'Current Admin',
        'email' => 'current@example.com',
        'password' => 'password',
        'status' => true,
    ]);

    foreach (range(1, 11) as $number) {
        Admin::create([
            'role_id' => $role->id,
            'name' => 'Admin '.$number,
            'email' => "admin{$number}@example.com",
            'password' => 'password',
            'status' => true,
        ]);
    }

    $this->actingAs($loggedInAdmin, 'admin')
        ->get('/admins')
        ->assertOk()
        ->assertSee('Admin Management');

    $this->actingAs($loggedInAdmin, 'admin')
        ->getJson('/admins/data?page=1&per_page=10', [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertOk()
        ->assertJsonCount(10, 'data')
        ->assertJsonPath('total', 12)
        ->assertJsonPath('last_page', 2);
});
