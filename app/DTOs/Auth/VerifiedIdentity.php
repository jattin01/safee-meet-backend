<?php

namespace App\DTOs\Auth;

/**
 * Provider-neutral identity returned by any AuthVerificationProvider.
 * Business logic depends only on this DTO, never on Firebase-specific types.
 */
final class VerifiedIdentity
{
    public function __construct(
        public readonly string  $providerUid,
        public readonly string  $provider,      // 'google' | 'apple' | 'phone' | 'email'
        public readonly ?string $email,
        public readonly ?string $phone,
        public readonly ?string $name,
        public readonly ?string $avatarUrl,
    ) {}

    public static function make(
        string  $providerUid,
        string  $provider,
        ?string $email     = null,
        ?string $phone     = null,
        ?string $name      = null,
        ?string $avatarUrl = null,
    ): self {
        return new self($providerUid, $provider, $email, $phone, $name, $avatarUrl);
    }
}
