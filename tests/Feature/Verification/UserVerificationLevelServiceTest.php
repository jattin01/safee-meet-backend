<?php

use App\Models\User;
use App\Models\UserVerification;
use App\Models\VerificationLevel;
use App\Services\Verification\UserVerificationLevelService;

function createCatalogLevel(string $slug, int $sortOrder): VerificationLevel
{
    return VerificationLevel::create([
        'slug' => $slug,
        'name' => str($slug)->replace('_', ' ')->title()->toString(),
        'badge_icon' => "verification-levels/{$slug}.png",
        'sort_order' => $sortOrder,
        'is_active' => true,
    ]);
}

it('keeps the user level, catalog badge id, verification row, and trust score in sync', function () {
    $levelOne = createCatalogLevel('level_1_verified', 1);
    $levelTwo = createCatalogLevel('level_2_verified', 2);
    $professional = createCatalogLevel('professional', 3);
    $user = User::factory()->create(['verification_level' => 'none']);
    $verification = UserVerification::create([
        'user_id' => $user->id,
        'verification_level' => 0,
        'status' => 'pending',
    ]);
    $service = app(UserVerificationLevelService::class);

    $service->promote($user, 'level1', $verification);
    $user->refresh()->load('verificationLevel');

    expect($verification->fresh()->verification_level)->toBe(1)
        ->and($user->verification_level)->toBe('level1')
        ->and($user->verification_level_id)->toBe($levelOne->id)
        ->and($user->verificationLevel->badge_icon)->toBe('verification-levels/level_1_verified.png')
        ->and($user->trust_score)->toBe(33);

    $service->promote($user, 'level2');
    $user->refresh();

    expect($user->verification_level)->toBe('level2')
        ->and($user->verification_level_id)->toBe($levelTwo->id)
        ->and($user->trust_score)->toBe(67);

    $service->promote($user, 'professional');
    $user->refresh();

    expect($user->verification_level)->toBe('professional')
        ->and($user->verification_level_id)->toBe($professional->id)
        ->and($user->trust_score)->toBe(100);
});

it('repairs a missing badge id when the same level is promoted again', function () {
    $levelOne = createCatalogLevel('level_1_verified', 1);
    $user = User::factory()->create([
        'verification_level' => 'level1',
        'verification_level_id' => null,
    ]);

    app(UserVerificationLevelService::class)->promote($user, 'level1');

    expect($user->fresh()->verification_level_id)->toBe($levelOne->id);
});

it('never replaces a higher badge when a delayed lower-level event arrives', function () {
    createCatalogLevel('level_1_verified', 1);
    $professional = createCatalogLevel('professional', 3);
    $user = User::factory()->create([
        'verification_level' => 'professional',
        'verification_level_id' => $professional->id,
        'trust_score' => 100,
    ]);
    $verification = UserVerification::create([
        'user_id' => $user->id,
        'verification_level' => 0,
        'status' => 'pending',
    ]);

    app(UserVerificationLevelService::class)->promote($user, 'level1', $verification);

    expect($verification->fresh()->verification_level)->toBe(1)
        ->and($user->fresh()->verification_level)->toBe('professional')
        ->and($user->fresh()->verification_level_id)->toBe($professional->id)
        ->and($user->fresh()->trust_score)->toBe(100);
});

it('rolls back the verification upgrade when its active catalog level is missing', function () {
    $user = User::factory()->create(['verification_level' => 'none']);
    $verification = UserVerification::create([
        'user_id' => $user->id,
        'verification_level' => 0,
        'status' => 'pending',
    ]);

    expect(fn () => app(UserVerificationLevelService::class)
        ->promote($user, 'level1', $verification))
        ->toThrow(RuntimeException::class, 'active level1 verification catalog record is missing');

    expect($verification->fresh()->verification_level)->toBe(0)
        ->and($user->fresh()->verification_level)->toBe('none')
        ->and($user->fresh()->verification_level_id)->toBeNull();
});
