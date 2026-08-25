<?php

use App\Jobs\Verification\StoreDiditVerificationImages;
use App\Models\User;
use App\Models\UserVerification;
use App\Services\Verification\DiditVerificationImageStorage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

function createDiditVerification(array $payload, array $attributes = []): UserVerification
{
    $user = User::factory()->create();

    return UserVerification::create(array_merge([
        'user_id' => $user->id,
        'provider' => 'didit',
        'didit_session_id' => 'session-images',
        'didit_decision_status' => 'Approved',
        'didit_payload' => $payload,
        'status' => 'approved',
    ], $attributes));
}

it('stores Didit selfie and document images without changing the payload', function () {
    Storage::fake('public');
    Http::fake([
        'https://media.didit.test/selfie.jpg' => Http::response('selfie-bytes', 200, ['Content-Type' => 'image/jpeg']),
        'https://media.didit.test/front.png' => Http::response('front-bytes', 200, ['Content-Type' => 'image/png']),
        'https://media.didit.test/back.webp' => Http::response('back-bytes', 200, ['Content-Type' => 'image/webp']),
    ]);

    $payload = [
        'session_id' => 'session-images',
        'status' => 'Approved',
        'decision' => [
            'id_verifications' => [[
                'status' => 'Approved',
                'front_image' => 'https://media.didit.test/front.png',
                'back_image' => 'https://media.didit.test/back.webp',
            ]],
            'liveness_checks' => [[
                'status' => 'Approved',
                'reference_image' => 'https://media.didit.test/selfie.jpg',
            ]],
        ],
    ];
    $verification = createDiditVerification($payload);

    (new StoreDiditVerificationImages($verification->id))
        ->handle(app(DiditVerificationImageStorage::class));

    $verification->refresh();

    expect($verification->didit_payload)->toBe($payload)
        ->and($verification->face_id_image)->toBe('verification/face-id/didit/session-images/selfie.jpg')
        ->and($verification->national_id_front_image)->toBe('verification/national-id/didit/session-images/front.png')
        ->and($verification->national_id_back_image)->toBe('verification/national-id/didit/session-images/back.webp');

    Storage::disk('public')->assertExists($verification->face_id_image);
    Storage::disk('public')->assertExists($verification->national_id_front_image);
    Storage::disk('public')->assertExists($verification->national_id_back_image);

    (new StoreDiditVerificationImages($verification->id))
        ->handle(app(DiditVerificationImageStorage::class));
    Http::assertSentCount(3);
});

it('keeps existing images when Didit omits an image or a download fails', function () {
    Storage::fake('public');
    Http::fake([
        'https://media.didit.test/front.jpg' => Http::response('', 503),
        'https://media.didit.test/back.jpg' => Http::response('new-back', 200, ['Content-Type' => 'image/jpeg']),
    ]);

    $payload = [
        'decision' => [
            'id_verifications' => [[
                'status' => 'Approved',
                'front_image' => 'https://media.didit.test/front.jpg',
                'back_image' => 'https://media.didit.test/back.jpg',
            ]],
            'liveness_checks' => [],
        ],
    ];
    $verification = createDiditVerification($payload, [
        'face_id_image' => 'verification/face-id/existing.jpg',
        'national_id_front_image' => 'verification/national-id/existing-front.jpg',
        'national_id_back_image' => 'verification/national-id/existing-back.jpg',
    ]);

    $failures = app(DiditVerificationImageStorage::class)->storeAvailableImages($verification);
    $verification->refresh();

    expect($failures)->toBe(['national_id_front_image'])
        ->and($verification->face_id_image)->toBe('verification/face-id/existing.jpg')
        ->and($verification->national_id_front_image)->toBe('verification/national-id/existing-front.jpg')
        ->and($verification->national_id_back_image)->toBe('verification/national-id/didit/session-images/back.jpg');
});

it('uses approved verification nodes and the face-match selfie fallback', function () {
    Storage::fake('public');
    Http::fake([
        'https://media.didit.test/full-front.jpg' => Http::response('front', 200, ['Content-Type' => 'image/jpeg']),
        'https://media.didit.test/full-back.jpg' => Http::response('back', 200, ['Content-Type' => 'image/jpeg']),
        'https://media.didit.test/target.jpg' => Http::response('selfie', 200, ['Content-Type' => 'image/jpeg']),
    ]);

    $verification = createDiditVerification([
        'id_verifications' => [
            [
                'status' => 'Declined',
                'front_image' => 'https://media.didit.test/declined-front.jpg',
                'back_image' => 'https://media.didit.test/declined-back.jpg',
            ],
            [
                'status' => 'Approved',
                'full_front_image' => 'https://media.didit.test/full-front.jpg',
                'full_back_image' => 'https://media.didit.test/full-back.jpg',
            ],
        ],
        'liveness_checks' => [],
        'face_matches' => [[
            'status' => 'Approved',
            'target_image' => 'https://media.didit.test/target.jpg',
        ]],
    ]);

    $failures = app(DiditVerificationImageStorage::class)->storeAvailableImages($verification);
    $verification->refresh();

    expect($failures)->toBe([])
        ->and($verification->face_id_image)->toEndWith('/selfie.jpg')
        ->and($verification->national_id_front_image)->toEndWith('/front.jpg')
        ->and($verification->national_id_back_image)->toEndWith('/back.jpg');

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'declined-'));
});

it('saves the complete webhook payload before dispatching image storage', function () {
    Queue::fake();
    config(['services.didit.webhook_secret' => 'test-webhook-secret']);

    $verification = createDiditVerification(['old' => 'payload'], [
        'didit_session_id' => 'webhook-session',
        'didit_decision_status' => 'In Progress',
        'status' => 'pending',
    ]);
    $payload = [
        'event_id' => 'event-1',
        'session_id' => 'webhook-session',
        'status' => 'In Review',
        'webhook_type' => 'status.updated',
        'timestamp' => now()->timestamp,
        'decision' => [
            'status' => 'In Review',
            'id_verifications' => [],
            'liveness_checks' => [],
        ],
    ];
    $rawPayload = json_encode($payload, JSON_UNESCAPED_SLASHES);

    $this->call('POST', '/api/webhooks/didit', server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_TIMESTAMP' => (string) $payload['timestamp'],
        'HTTP_X_SIGNATURE' => hash_hmac('sha256', $rawPayload, 'test-webhook-secret'),
    ], content: $rawPayload)->assertOk();

    expect($verification->fresh()->didit_payload)->toBe($payload);
    Queue::assertPushed(
        StoreDiditVerificationImages::class,
        fn (StoreDiditVerificationImages $job) => (string) $job->verificationId === (string) $verification->id,
    );
});
