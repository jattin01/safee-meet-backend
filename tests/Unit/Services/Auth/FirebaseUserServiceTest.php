<?php

use App\Services\Auth\FirebaseUserService;
use Kreait\Firebase\Contract\Auth as FirebaseAuth;
use Kreait\Firebase\Exception\Auth\UserNotFound;

it('deletes a Firebase Authentication user by UID', function () {
    $firebaseAuth = Mockery::mock(FirebaseAuth::class);
    $firebaseAuth->shouldReceive('deleteUser')
        ->once()
        ->with('firebase-uid-123');

    (new FirebaseUserService($firebaseAuth))->deleteUser('firebase-uid-123');
});

it('does nothing when the Firebase UID is missing', function (?string $firebaseUid) {
    $firebaseAuth = Mockery::mock(FirebaseAuth::class);
    $firebaseAuth->shouldNotReceive('deleteUser');

    (new FirebaseUserService($firebaseAuth))->deleteUser($firebaseUid);
})->with([null, '', '   ']);

it('treats an already deleted Firebase user as success', function () {
    $firebaseAuth = Mockery::mock(FirebaseAuth::class);
    $firebaseAuth->shouldReceive('deleteUser')
        ->once()
        ->with('missing-firebase-uid')
        ->andThrow(new UserNotFound('User not found'));

    (new FirebaseUserService($firebaseAuth))->deleteUser('missing-firebase-uid');
});
