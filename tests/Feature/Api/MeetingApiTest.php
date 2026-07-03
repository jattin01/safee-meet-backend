<?php

use App\Models\Meeting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('lets an authenticated host create and fetch a meeting', function () {
    $host = User::factory()->create();
    $guest = User::factory()->create();
    Sanctum::actingAs($host);

    $response = $this->postJson('/api/v1/meetings', [
        'guest_user_id' => $guest->id,
        'meeting_date' => now()->addDay()->toDateString(),
        'meeting_time' => '16:30',
        'location' => 'Central Cafe',
        'purpose' => 'Coffee and introductions',
        'type' => 'coffee',
    ])->assertCreated()
        ->assertJsonPath('host_user_id', $host->id)
        ->assertJsonPath('guest_user_id', $guest->id);

    $this->getJson('/api/v1/meetings/'.$response->json('id'))
        ->assertOk()
        ->assertJsonPath('location', 'Central Cafe');
});

it('only lets the host delete a meeting', function () {
    $host = User::factory()->create();
    $guest = User::factory()->create();
    $meeting = Meeting::create([
        'host_user_id' => $host->id,
        'guest_user_id' => $guest->id,
        'meeting_date' => now()->addDay()->toDateString(),
        'meeting_time' => '16:30',
        'location' => 'Central Cafe',
    ]);

    Sanctum::actingAs($guest);
    $this->deleteJson('/api/v1/meetings/'.$meeting->id)->assertForbidden();

    Sanctum::actingAs($host);
    $this->deleteJson('/api/v1/meetings/'.$meeting->id)->assertOk();

    $this->assertDatabaseMissing('meetings', ['id' => $meeting->id]);
});
