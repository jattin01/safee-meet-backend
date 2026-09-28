<?php

use App\Models\Admin;
use App\Models\Incident;
use App\Models\Role;
use App\Models\User;

function createIncidentAdmin(): Admin
{
    $role = Role::create([
        'name' => 'Admin',
        'slug' => 'admin',
        'status' => true,
    ]);

    return Admin::create([
        'role_id' => $role->id,
        'name' => 'Incident Admin',
        'email' => 'incident-admin@example.com',
        'password' => 'password',
        'status' => true,
    ]);
}

function createIncident(User $reporter, string $description, string $createdAt): Incident
{
    $incident = Incident::create([
        'reporter_user_id' => $reporter->id,
        'type' => 'general_incident',
        'description' => $description,
        'status' => 'open',
    ]);

    $incident->forceFill([
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ])->saveQuietly();

    return $incident;
}

it('filters incidents by reporter name email or phone', function (string $search) {
    $admin = createIncidentAdmin();
    $matchingReporter = User::factory()->create([
        'name' => 'Alice Incident',
        'email' => 'alice-incident@example.com',
        'phone' => '+15551234567',
    ]);
    $otherReporter = User::factory()->create([
        'name' => 'Bob Elsewhere',
        'email' => 'bob@example.com',
        'phone' => '+15557654321',
    ]);

    createIncident($matchingReporter, 'matching incident', '2026-09-10 10:00:00');
    createIncident($otherReporter, 'other incident', '2026-09-10 11:00:00');

    $this->actingAs($admin, 'admin')
        ->get(route('incidents', ['search' => $search]))
        ->assertOk()
        ->assertSee('Alice Incident')
        ->assertDontSee('Bob Elsewhere');
})->with(['Alice', 'alice-incident@example.com', '+15551234567']);

it('filters incidents by an inclusive reported date range', function () {
    $admin = createIncidentAdmin();
    $beforeReporter = User::factory()->create(['name' => 'Before Reporter']);
    $insideReporter = User::factory()->create(['name' => 'Inside Reporter']);
    $afterReporter = User::factory()->create(['name' => 'After Reporter']);

    createIncident($beforeReporter, 'before range', '2026-09-09 23:59:59');
    createIncident($insideReporter, 'inside range', '2026-09-10 23:59:59');
    createIncident($afterReporter, 'after range', '2026-09-11 00:00:00');

    $this->actingAs($admin, 'admin')
        ->get(route('incidents', [
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-10',
        ]))
        ->assertOk()
        ->assertSee('Inside Reporter')
        ->assertDontSee('Before Reporter')
        ->assertDontSee('After Reporter');
});

it('returns only the results fragment for live search requests', function () {
    $admin = createIncidentAdmin();
    $reporter = User::factory()->create(['name' => 'Live Search Reporter']);
    createIncident($reporter, 'live search incident', '2026-09-10 10:00:00');

    $this->actingAs($admin, 'admin')
        ->get(route('incidents', ['search' => 'Live Search']), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->assertOk()
        ->assertSee('Live Search Reporter')
        ->assertDontSee('Incident Reports')
        ->assertDontSee('incidents-filter-form');
});

it('exports only incidents matching the active filters', function () {
    $admin = createIncidentAdmin();
    $alice = User::factory()->create(['name' => 'Alice Export']);
    $bob = User::factory()->create(['name' => 'Bob Export']);

    createIncident($alice, 'export this row', '2026-09-10 10:00:00');
    createIncident($bob, 'exclude this row', '2026-09-10 11:00:00');

    $response = $this->actingAs($admin, 'admin')
        ->get(route('incidents.export', ['search' => 'Alice']))
        ->assertOk()
        ->assertDownload();

    $content = $response->streamedContent();
    expect($content)->toContain('export this row')
        ->not->toContain('exclude this row');
});

it('rejects an incident date range that ends before it starts', function () {
    $admin = createIncidentAdmin();

    $this->actingAs($admin, 'admin')
        ->from(route('incidents'))
        ->get(route('incidents', [
            'start_date' => '2026-09-11',
            'end_date' => '2026-09-10',
        ]))
        ->assertRedirect(route('incidents'))
        ->assertSessionHasErrors('end_date');
});
