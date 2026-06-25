<?php

namespace App\Exceptions\Auth;

use Exception;
use Illuminate\Http\JsonResponse;

class AuthException extends Exception
{
    public function __construct(
        private readonly string $errorCode,
        string $message,
        private readonly int $httpStatus = 400,
    ) {
        parent::__construct($message);
    }

    public static function userNotRegistered(): self
    {
        return new self(
            'USER_NOT_REGISTERED',
            'You are not registered right now. Please register yourself first.',
            404,
        );
    }

    public static function userAlreadyExists(): self
    {
        return new self('USER_ALREADY_EXISTS', 'This account already exists. Please login.', 409);
    }

    public static function invalidCredentials(): self
    {
        return new self('INVALID_CREDENTIALS', 'Invalid credentials provided.', 401);
    }

    public static function phoneVerificationFailed(string $detail = ''): self
    {
        return new self(
            'PHONE_VERIFICATION_FAILED',
            'Phone verification failed.' . ($detail ? " $detail" : ''),
            422,
        );
    }

    public static function emailVerificationFailed(string $detail = ''): self
    {
        return new self(
            'EMAIL_VERIFICATION_FAILED',
            'Email verification failed.' . ($detail ? " $detail" : ''),
            422,
        );
    }

    public static function socialValidationFailed(string $detail = ''): self
    {
        return new self(
            'SOCIAL_VALIDATION_FAILED',
            'Social authentication validation failed.' . ($detail ? " $detail" : ''),
            422,
        );
    }

    public static function accountBlocked(): self
    {
        return new self('ACCOUNT_BLOCKED', 'Your account has been blocked. Contact support.', 403);
    }

    public static function accountInactive(): self
    {
        return new self('ACCOUNT_INACTIVE', 'Your account is not active.', 403);
    }

    public static function unauthorized(): self
    {
        return new self('UNAUTHORIZED', 'Unauthorized access.', 401);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'code'    => $this->errorCode,
            'message' => $this->getMessage(),
        ], $this->httpStatus);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }
}
