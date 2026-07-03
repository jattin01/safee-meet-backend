<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('registers a new user after verifying the phone otp', function () {
    $otpResponse = $this->postJson('/api/v1/auth/register', [
        'name' => 'Asha',
        'phone' => '+91 98765 43210',
    ])->assertOk()
        ->assertJsonPath('data.flow', 'register');

    $this->postJson('/api/v1/auth/verify-otp', [
        'phone' => '+919876543210',
        'otp' => $otpResponse->json('data.dev_otp'),
        'device_name' => 'test-phone',
    ])->assertCreated()
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.is_new_user', true)
        ->assertJsonPath('data.user.name', 'Asha');

    $this->assertDatabaseHas('users', [
        'phone' => '+919876543210',
        'name' => 'Asha',
    ]);
});

it('logs in an existing user after verifying the phone otp', function () {
    $user = User::factory()->create([
        'phone' => '+919999999999',
        'phone_verified_at' => now(),
    ]);

    $otpResponse = $this->postJson('/api/v1/auth/login', [
        'phone' => '+919999999999',
    ])->assertOk()
        ->assertJsonPath('data.flow', 'login');

    $response = $this->postJson('/api/v1/auth/verify-otp', [
        'phone' => '+919999999999',
        'otp' => $otpResponse->json('data.dev_otp'),
    ])->assertOk()
        ->assertJsonPath('data.is_new_user', false);

    $this->withToken($response->json('data.access_token'))
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.user.id', $user->id);
});

it('rejects an incorrect otp', function () {
    $this->postJson('/api/v1/auth/login-or-register', [
        'name' => 'Nikhil',
        'phone' => '+919812345678',
    ])->assertOk();

    $this->postJson('/api/v1/auth/verify-otp', [
        'phone' => '+919812345678',
        'otp' => '000000',
    ])->assertUnprocessable()
        ->assertJsonPath('message', 'The OTP is incorrect.');
});
