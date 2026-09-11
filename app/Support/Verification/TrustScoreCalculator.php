<?php

namespace App\Support\Verification;

use App\Models\User;

class TrustScoreCalculator
{
    private const SCORES = [
        'none' => 0,
        'level1' => 50,
        'level2' => 100,
        'professional' => 100,
    ];

    public static function scoreFor(?string $verificationLevel): int
    {
        return self::SCORES[$verificationLevel] ?? 0;
    }

    public static function recalculate(User $user): void
    {
        $user->trust_score = self::scoreFor($user->verification_level);
        $user->save();
    }
}
