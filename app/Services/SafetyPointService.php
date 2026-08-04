<?php

namespace App\Services;

use App\Models\UserSafetyPointHistory;

class SafetyPointService
{
    /**
     * Add/Deduct safety points.
     *
     * @param int $userId
     * @param string $eventKey
     * @param int $points
     * @param string|null $referenceType
     * @param int|null $referenceId
     * @param string|null $description
     */
    public function addPoints(
        int $userId,
        string $eventKey,
        int $points,
        ?string $referenceType = null,
        ?string $description = null
    ): UserSafetyPointHistory {

        return UserSafetyPointHistory::create([
            'user_id'        => $userId,
            'event_key'      => $eventKey,
            'points'         => $points,
            'reference_type' => $referenceType,
            'description'    => $description,
        ]);
    }
}