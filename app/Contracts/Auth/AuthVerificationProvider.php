<?php

namespace App\Contracts\Auth;

use App\DTOs\Auth\VerifiedIdentity;

/**
 * Provider-independent auth verification contract.
 * Swap Firebase → Twilio / Auth0 / AWS Cognito by binding a new implementation.
 */
interface AuthVerificationProvider
{
    /**
     * Verify a phone number via an OTP token issued by the current provider.
     * Returns a normalised identity on success; throws on failure.
     */
    public function verifyPhone(string $phone, string $providerToken): VerifiedIdentity;

    /**
     * Verify an email address via a token issued by the current provider.
     */
    public function verifyEmail(string $email, string $providerToken): VerifiedIdentity;

    /**
     * Validate a Google (or Firebase-Google) ID token.
     */
    public function validateGoogle(string $providerToken): VerifiedIdentity;

    /**
     * Validate an Apple Sign-In identity token.
     */
    public function validateApple(string $providerToken): VerifiedIdentity;

    /**
     * Generic: validate any provider token and return a normalised identity.
     * Used when the provider type is passed as a string at runtime.
     */
    public function validateToken(string $provider, string $providerToken): VerifiedIdentity;
}
