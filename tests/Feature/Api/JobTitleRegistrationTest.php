<?php

use App\Models\JobTitle;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    // The legacy test schema predates these production columns.
    $missingStrings = collect([
        'display_name', 'firebase_uid', 'status', 'onboarding_status',
        'kyc_status', 'trust_tier',
    ])->reject(fn (string $column): bool => Schema::hasColumn('users', $column));

    $missingBooleans = collect([
        'is_chat_enabled', 'is_meeting_enabled', 'is_sos_enabled',
    ])->reject(fn (string $column): bool => Schema::hasColumn('users', $column));

    if ($missingStrings->isNotEmpty() || $missingBooleans->isNotEmpty()) {
        Schema::table('users', function (Blueprint $table) use ($missingStrings, $missingBooleans): void {
            foreach ($missingStrings as $column) {
                $table->string($column)->nullable();
            }
            foreach ($missingBooleans as $column) {
                $table->boolean($column)->default(true);
            }
        });
    }
});

it('stores jobTitleId in users.job_title during phone registration', function () {
    $jobTitle = JobTitle::query()->where('normalized_name', 'electrician')->firstOrFail();

    $phone = '+919876543210';
    Cache::put('phone_otp_'.hash('sha256', $phone), [
        'verified' => true,
        'verified_at' => now()->timestamp,
        'firebase_uid' => 'firebase-test-user',
    ], 600);

    $this->postJson('/api/v1/auth/register', [
        'provider' => 'phone',
        'providerToken' => 'unused-by-verified-otp-registration',
        'name' => 'Udit',
        'email' => 'udit@example.com',
        'phone' => $phone,
        'accountType' => 'employer',
        'companyName' => 'Solution Bowl',
        'jobTitleId' => $jobTitle->id,
        'consentAccepted' => true,
    ])->assertCreated()
        ->assertJsonPath('data.user.jobTitleId', $jobTitle->id)
        ->assertJsonPath('data.user.jobTitle', 'Electrician');

    $this->assertDatabaseHas('users', [
        'phone' => $phone,
        'company_name' => 'Solution Bowl',
        'job_title' => $jobTitle->id,
    ]);
});

it('rejects an inactive jobTitleId during phone registration', function () {
    $jobTitle = JobTitle::create([
        'name' => 'Inactive title',
        'normalized_name' => 'inactive title',
        'is_active' => false,
    ]);

    $this->postJson('/api/v1/auth/register', [
        'provider' => 'phone',
        'name' => 'Udit',
        'phone' => '+919876543211',
        'accountType' => 'employer',
        'jobTitleId' => $jobTitle->id,
        'consentAccepted' => true,
    ])->assertUnprocessable()
        ->assertJsonPath('code', 'INVALID_JOB_TITLE');
});
