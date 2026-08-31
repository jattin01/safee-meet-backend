<?php

use App\Models\User;
use App\Models\UserVerification;
use App\Models\VerificationLevel;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

function postApprovedDiditWebhook(UserVerification $verification): TestResponse
{
    $payload = [
        'event_id' => 'event-'.$verification->id,
        'session_id' => $verification->didit_session_id,
        'status' => 'Approved',
        'webhook_type' => 'status.updated',
        'timestamp' => now()->timestamp,
        'decision' => ['status' => 'Approved'],
    ];
    $rawPayload = json_encode($payload, JSON_UNESCAPED_SLASHES);

    return test()->call('POST', '/api/webhooks/didit', server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_TIMESTAMP' => (string) $payload['timestamp'],
        'HTTP_X_SIGNATURE' => hash_hmac('sha256', $rawPayload, 'test-webhook-secret'),
    ], content: $rawPayload);
}

beforeEach(function () {
    Queue::fake();
    config(['services.didit.webhook_secret' => 'test-webhook-secret']);
});

it('stores the Level 1 catalog id when an approved webhook promotes the verification', function () {
    $levelOne = VerificationLevel::create([
        'slug' => 'level_1_verified',
        'name' => 'Level 1 Verified',
        'sort_order' => 1,
        'is_active' => true,
    ]);
    $user = User::factory()->create(['verification_level' => 'none']);
    $verification = UserVerification::create([
        'user_id' => $user->id,
        'provider' => 'didit',
        'didit_session_id' => 'approved-level-one-session',
        'didit_decision_status' => 'In Review',
        'verification_level' => 0,
        'status' => 'pending',
    ]);

    postApprovedDiditWebhook($verification)->assertOk();

    expect($verification->fresh()->verification_level)->toBe(1)
        ->and($user->fresh()->verification_level)->toBe('level1')
        ->and($user->fresh()->verification_level_id)->toBe($levelOne->id);
});

it('does not replace a higher user verification catalog id with Level 1', function () {
    VerificationLevel::create([
        'slug' => 'level_1_verified',
        'name' => 'Level 1 Verified',
        'sort_order' => 1,
        'is_active' => true,
    ]);
    $levelTwo = VerificationLevel::create([
        'slug' => 'level_2_verified',
        'name' => 'Level 2 Verified',
        'sort_order' => 2,
        'is_active' => true,
    ]);
    $user = User::factory()->create([
        'verification_level' => 'level2',
        'verification_level_id' => $levelTwo->id,
    ]);
    $verification = UserVerification::create([
        'user_id' => $user->id,
        'provider' => 'didit',
        'didit_session_id' => 'approved-existing-level-two-session',
        'didit_decision_status' => 'In Review',
        'verification_level' => 0,
        'status' => 'pending',
    ]);

    postApprovedDiditWebhook($verification)->assertOk();

    expect($verification->fresh()->verification_level)->toBe(1)
        ->and($user->fresh()->verification_level)->toBe('level2')
        ->and($user->fresh()->verification_level_id)->toBe($levelTwo->id);
});
