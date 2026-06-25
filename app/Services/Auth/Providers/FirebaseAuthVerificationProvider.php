<?php

namespace App\Services\Auth\Providers;

use App\Contracts\Auth\AuthVerificationProvider;
use App\DTOs\Auth\VerifiedIdentity;
use App\Exceptions\Auth\AuthException;
use Kreait\Firebase\Auth as FirebaseAuth;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;
use Throwable;

/**
 * Firebase implementation of AuthVerificationProvider.
 * Replace this class with TwilioAuthVerificationProvider / Auth0VerificationProvider
 * without touching any business logic or frontend contracts.
 */
class FirebaseAuthVerificationProvider implements AuthVerificationProvider
{
    public function __construct(private readonly FirebaseAuth $firebaseAuth) {}

    public function verifyPhone(string $phone, string $providerToken): VerifiedIdentity
    {
        try {
            $token = $this->firebaseAuth->verifyIdToken($providerToken);
            $claims = $token->claims();

            $phoneFromToken = $claims->get('phone_number');

            if (empty($phoneFromToken)) {
                throw AuthException::phoneVerificationFailed('Token has no phone claim.');
            }

            return VerifiedIdentity::make(
                providerUid: $claims->get('sub'),
                provider: 'phone',
                phone: $phoneFromToken,
            );
        } catch (AuthException $e) {
            throw $e;
        } catch (FailedToVerifyToken $e) {
            throw AuthException::phoneVerificationFailed('Invalid or expired token.');
        } catch (Throwable $e) {
            throw AuthException::phoneVerificationFailed($e->getMessage());
        }
    }

    public function verifyEmail(string $email, string $providerToken): VerifiedIdentity
    {
        try {
            $token  = $this->firebaseAuth->verifyIdToken($providerToken);
            $claims = $token->claims();

            return VerifiedIdentity::make(
                providerUid: $claims->get('sub'),
                provider: 'email',
                email: $claims->get('email'),
                name: $claims->get('name'),
            );
        } catch (AuthException $e) {
            throw $e;
        } catch (Throwable) {
            throw AuthException::emailVerificationFailed('Invalid or expired token.');
        }
    }

    public function validateGoogle(string $providerToken): VerifiedIdentity
    {
        try {
            $token  = $this->firebaseAuth->verifyIdToken($providerToken);
            $claims = $token->claims();

            return VerifiedIdentity::make(
                providerUid: $claims->get('sub'),
                provider: 'google',
                email: $claims->get('email'),
                name: $claims->get('name'),
                avatarUrl: $claims->get('picture'),
            );
        } catch (AuthException $e) {
            throw $e;
        } catch (Throwable) {
            throw AuthException::socialValidationFailed('Google token is invalid or expired.');
        }
    }

    public function validateApple(string $providerToken): VerifiedIdentity
    {
        try {
            $token  = $this->firebaseAuth->verifyIdToken($providerToken);
            $claims = $token->claims();

            return VerifiedIdentity::make(
                providerUid: $claims->get('sub'),
                provider: 'apple',
                email: $claims->get('email'),
                name: $claims->get('name'),
            );
        } catch (AuthException $e) {
            throw $e;
        } catch (Throwable) {
            throw AuthException::socialValidationFailed('Apple token is invalid or expired.');
        }
    }

    public function validateToken(string $provider, string $providerToken): VerifiedIdentity
    {
        return match ($provider) {
            'google' => $this->validateGoogle($providerToken),
            'apple'  => $this->validateApple($providerToken),
            'phone'  => $this->verifyPhone('', $providerToken),
            'email'  => $this->verifyEmail('', $providerToken),
            default  => throw AuthException::socialValidationFailed("Unknown provider: $provider"),
        };
    }
}
