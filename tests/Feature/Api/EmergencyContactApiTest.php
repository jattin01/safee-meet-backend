<?php

use App\Models\EmergencyContact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('lists and creates emergency contacts for the authenticated user', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/emergency-contacts', [
        'full_name' => 'Priya Sharma',
        'relationship' => 'Sister',
        'phone_number' => '+919876543210',
    ])->assertCreated()
        ->assertJsonPath('data.user_id', $user->id);

    $this->getJson('/api/v1/emergency-contacts')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.full_name', 'Priya Sharma');
});

it('does not allow a user to delete another users emergency contact', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $contact = EmergencyContact::create([
        'user_id' => $owner->id,
        'full_name' => 'Ravi Kumar',
        'relationship' => 'Friend',
        'phone_number' => '+919999999999',
    ]);

    Sanctum::actingAs($otherUser);

    $this->deleteJson('/api/v1/emergency-contacts/'.$contact->id)
        ->assertForbidden();
});
