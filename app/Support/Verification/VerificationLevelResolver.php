<?php

namespace App\Support\Verification;

class VerificationLevelResolver
{
    public static function fromUser(?string $kycStatus, ?string $trustTier): string
    {
        if ($kycStatus !== 'verified') {
            return 'none';
        }

        return match ($trustTier) {
            'medium' => 'medium',
            'high', 'verified' => 'high',
            default => 'low',
        };
    }
}
