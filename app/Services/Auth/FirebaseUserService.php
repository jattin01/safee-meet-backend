<?php

namespace App\Services\Auth;

use Kreait\Firebase\Contract\Auth as FirebaseAuth;
use Kreait\Firebase\Exception\Auth\UserNotFound;

class FirebaseUserService
{
    public function __construct(private readonly FirebaseAuth $firebaseAuth) {}

    /**
     * Delete a Firebase Authentication user by UID.
     *
     * The operation is idempotent: a missing or blank UID is treated as an
     * already-deleted user. Other Firebase errors are allowed to bubble up so
     * the caller does not silently complete a partial account deletion.
     */
    public function deleteUser(?string $firebaseUid): void
    {
        if (blank($firebaseUid)) {
            return;
        }

        try {
            $this->firebaseAuth->deleteUser($firebaseUid);
        } catch (UserNotFound) {
            // The desired end state has already been reached.
        }
    }
}
