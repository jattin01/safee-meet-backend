<?php

use App\Jobs\Verification\StoreDiditVerificationImages;
use App\Models\User;
use App\Models\UserVerification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

function sendDiditProgressWebhook(UserVerification $verification, string $status): TestResponse
{
    $payload = [
        'event_id' => 'event-'.$verification->id.'-'.$status,
        'session_id' => $verification->didit_session_id,
        'status' => $status,
        'webhook_type' => 'status.updated',
        'timestamp' => now()->timestamp,
        'decision' => ['status' => $status],
    ];
    $rawPayload = json_encode($payload, JSON_UNESCAPED_SLASHES);

    return test()->call('POST', '/api/webhooks/didit', server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_TIMESTAMP' => (string) $payload['timestamp'],
        'HTTP_X_SIGNATURE' => hash_hmac('sha256', $rawPayload, 'test-webhook-secret'),
    ], content: $rawPayload);
}

function fakeDiditSessionCreation(string $sessionId): void
{
    Http::fake([
        'https://didit.test/v3/session/' => Http::response([
            'session_id' => $sessionId,
            'session_token' => 'token-'.$sessionId,
            'verification_url' => 'https://verify.didit.test/'.$sessionId,
            'status' => 'Not Started',
        ]),
    ]);
}

beforeEach(function () {
    Mail::fake();
    Queue::fake();
    Storage::fake('public');
    config([
        'services.didit.webhook_secret' => 'test-webhook-secret',
        'services.didit.api_key' => 'test-api-key',
        'services.didit.base_url' => 'https://didit.test',
        'services.didit.workflow_id' => 'workflow-1',
    ]);
});

it('keeps the verification not submitted when a session is started', function () {
    fakeDiditSessionCreation('fresh-session');
    $user = User::factory()->create(['kyc_status' => 'not_started']);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/verification/didit/start')->assertOk();

    $verification = UserVerification::where('user_id', $user->id)->first();
    expect($verification->status)->toBe('not_submitted')
        ->and($verification->submitted_at)->toBeNull()
        ->and($user->fresh()->kyc_status)->toBe('not_started');

    $this->getJson('/api/v1/verification/didit/status')
        ->assertOk()
        ->assertJsonPath('status', 'not_submitted');
});

it('keeps the verification not submitted when the user drops out', function (string $diditStatus) {
    $user = User::factory()->create(['kyc_status' => 'not_started']);
    $verification = UserVerification::create([
        'user_id' => $user->id,
        'provider' => 'didit',
        'didit_session_id' => 'dropped-session-'.$diditStatus,
        'didit_decision_status' => 'In Progress',
        'status' => 'not_submitted',
    ]);

    sendDiditProgressWebhook($verification, $diditStatus)->assertOk();

    expect($verification->fresh()->status)->toBe('not_submitted')
        ->and($verification->fresh()->submitted_at)->toBeNull()
        ->and($user->fresh()->kyc_status)->toBe('not_started');
    Queue::assertNotPushed(StoreDiditVerificationImages::class);
})->with(['In Progress', 'Abandoned', 'Expired']);

it('moves a legacy pending row back to not submitted when Didit reports it abandoned', function () {
    $user = User::factory()->create(['kyc_status' => 'pending']);
    $verification = UserVerification::create([
        'user_id' => $user->id,
        'provider' => 'didit',
        'didit_session_id' => 'legacy-pending-session',
        'didit_decision_status' => 'In Progress',
        'status' => 'pending',
        'submitted_at' => now(),
    ]);

    sendDiditProgressWebhook($verification, 'Abandoned')->assertOk();

    expect($verification->fresh()->status)->toBe('not_submitted')
        ->and($verification->fresh()->submitted_at)->toBeNull()
        ->and($user->fresh()->kyc_status)->toBe('not_started');
});

it('marks the verification submitted only when Didit puts it in review', function () {
    $user = User::factory()->create(['kyc_status' => 'not_started']);
    $verification = UserVerification::create([
        'user_id' => $user->id,
        'provider' => 'didit',
        'didit_session_id' => 'review-session',
        'didit_decision_status' => 'In Progress',
        'status' => 'not_submitted',
    ]);

    sendDiditProgressWebhook($verification, 'In Review')->assertOk();

    expect($verification->fresh()->status)->toBe('pending')
        ->and($verification->fresh()->submitted_at)->not->toBeNull()
        ->and($user->fresh()->kyc_status)->toBe('pending');
    Queue::assertPushed(StoreDiditVerificationImages::class);
});

it('ignores a late progress webhook after Didit has decided', function (string $decidedStatus, string $lateStatus) {
    $user = User::factory()->create(['kyc_status' => $decidedStatus === 'approved' ? 'verified' : 'rejected']);
    $verification = UserVerification::create([
        'user_id' => $user->id,
        'provider' => 'didit',
        'didit_session_id' => "decided-{$decidedStatus}-{$lateStatus}",
        'didit_decision_status' => $decidedStatus === 'approved' ? 'Approved' : 'Declined',
        'status' => $decidedStatus,
        'submitted_at' => now(),
    ]);

    sendDiditProgressWebhook($verification, $lateStatus)->assertOk();

    expect($verification->fresh()->status)->toBe($decidedStatus)
        ->and($verification->fresh()->submitted_at)->not->toBeNull();
})->with([
    ['approved', 'In Progress'],
    ['approved', 'In Review'],
    ['rejected', 'Abandoned'],
]);

it('clears the previous attempt when the user retries with another document', function () {
    $oldSelfie = 'verification/face-id/didit/old-session/selfie.jpg';
    $oldBack = 'verification/national-id/didit/old-session/back.jpg';
    Storage::disk('public')->put($oldSelfie, 'old-selfie');
    Storage::disk('public')->put($oldBack, 'old-back');

    $user = User::factory()->create(['kyc_status' => 'rejected']);
    UserVerification::create([
        'user_id' => $user->id,
        'provider' => 'didit',
        'didit_session_id' => 'old-session',
        'didit_decision_status' => 'Declined',
        'status' => 'rejected',
        'face_id_image' => $oldSelfie,
        'national_id_back_image' => $oldBack,
        'rejection_reason' => 'Document image was unreadable.',
        'submitted_at' => now()->subDay(),
        'reviewed_at' => now()->subDay(),
        'rejected_at' => now()->subDay(),
    ]);

    fakeDiditSessionCreation('new-session');
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/verification/didit/start')
        ->assertOk()
        ->assertJsonPath('sessionId', 'new-session');

    // Review data is cleared at once; the old images (the selfie is also the
    // profile photo) stay until the new attempt is submitted.
    $verification = UserVerification::where('user_id', $user->id)->first();
    expect($verification->didit_session_id)->toBe('new-session')
        ->and($verification->status)->toBe('not_submitted')
        ->and($verification->rejection_reason)->toBeNull()
        ->and($verification->rejected_at)->toBeNull()
        ->and($verification->reviewed_at)->toBeNull()
        ->and($verification->submitted_at)->toBeNull()
        ->and($verification->face_id_image)->toBe($oldSelfie)
        ->and($verification->national_id_back_image)->toBe($oldBack)
        ->and($user->fresh()->kyc_status)->toBe('not_started');
    Storage::disk('public')->assertExists($oldSelfie);

    // Dropping the new attempt still keeps them.
    sendDiditProgressWebhook($verification, 'Abandoned')->assertOk();
    expect($verification->fresh()->face_id_image)->toBe($oldSelfie);

    // Submitting the new attempt removes the old evidence before the new
    // images are downloaded, so the two attempts never mix.
    sendDiditProgressWebhook($verification->fresh(), 'In Review')->assertOk();

    $verification->refresh();
    expect($verification->face_id_image)->toBeNull()
        ->and($verification->national_id_back_image)->toBeNull();
    Storage::disk('public')->assertMissing($oldSelfie);
    Storage::disk('public')->assertMissing($oldBack);
    Queue::assertPushed(StoreDiditVerificationImages::class);
});

it('keeps images that belong to the current session when it is submitted', function () {
    $currentSelfie = 'verification/face-id/didit/current-session/selfie.jpg';
    Storage::disk('public')->put($currentSelfie, 'selfie');

    $user = User::factory()->create();
    $verification = UserVerification::create([
        'user_id' => $user->id,
        'provider' => 'didit',
        'didit_session_id' => 'current-session',
        'didit_decision_status' => 'In Progress',
        'status' => 'not_submitted',
        'face_id_image' => $currentSelfie,
    ]);

    sendDiditProgressWebhook($verification, 'In Review')->assertOk();

    expect($verification->fresh()->face_id_image)->toBe($currentSelfie);
    Storage::disk('public')->assertExists($currentSelfie);
});

it('lets the user retry an unfinished legacy session that was marked pending', function () {
    $user = User::factory()->create(['kyc_status' => 'pending']);
    UserVerification::create([
        'user_id' => $user->id,
        'provider' => 'didit',
        'didit_session_id' => 'legacy-unfinished-session',
        'didit_decision_status' => 'Abandoned',
        'status' => 'pending',
    ]);

    fakeDiditSessionCreation('retry-session');
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/verification/didit/start')->assertOk();

    expect(UserVerification::where('user_id', $user->id)->first()->didit_session_id)
        ->toBe('retry-session');
});

it('refuses to start a new session for an approved or in-review verification', function (string $status, string $diditStatus) {
    Http::fake();
    $user = User::factory()->create();
    UserVerification::create([
        'user_id' => $user->id,
        'provider' => 'didit',
        'didit_session_id' => "blocked-{$status}",
        'didit_decision_status' => $diditStatus,
        'status' => $status,
        'face_id_image' => 'verification/face-id/didit/blocked/selfie.jpg',
    ]);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/verification/didit/start')->assertStatus(409);

    $verification = UserVerification::where('user_id', $user->id)->first();
    expect($verification->status)->toBe($status)
        ->and($verification->didit_session_id)->toBe("blocked-{$status}")
        ->and($verification->face_id_image)->not->toBeNull();
    Http::assertNothingSent();
})->with([
    ['approved', 'Approved'],
    ['pending', 'In Review'],
]);
