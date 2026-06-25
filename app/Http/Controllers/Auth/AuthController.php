<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\Auth\AuthException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CheckUserExistsRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\Auth\UserResource;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService) {}

    // ── POST /api/v1/auth/register ────────────────────────────────────────────

    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->register($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Registration successful.',
                'data'    => [
                    'accessToken'  => $result['accessToken'],
                    'refreshToken' => $result['refreshToken'],
                    'user'         => new UserResource($result['user']),
                    'isRegistered' => true,
                ],
            ], 201);
        } catch (AuthException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Registration error', ['error' => $e->getMessage()]);
            return $this->serverError();
        }
    }

    // ── POST /api/v1/auth/login ───────────────────────────────────────────────

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->login($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Login successful.',
                'data'    => [
                    'accessToken'  => $result['accessToken'],
                    'refreshToken' => $result['refreshToken'],
                    'user'         => new UserResource($result['user']),
                ],
            ]);
        } catch (AuthException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Login error', ['error' => $e->getMessage()]);
            return $this->serverError();
        }
    }

    // ── POST /api/v1/auth/logout ──────────────────────────────────────────────

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ]);
    }

    // ── GET /api/v1/auth/me ───────────────────────────────────────────────────

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => ['user' => new UserResource($request->user())],
        ]);
    }

    // ── POST /api/v1/auth/check-user-exists ──────────────────────────────────

    public function checkUserExists(CheckUserExistsRequest $request): JsonResponse
    {
        $exists = $this->authService->checkUserExists(
            $request->input('email'),
            $request->input('phone'),
            $request->input('providerUid'),
        );

        return response()->json([
            'success' => true,
            'data'    => ['exists' => $exists],
        ]);
    }

    // ── POST /api/v1/auth/verify-phone ───────────────────────────────────────

    public function verifyPhone(Request $request): JsonResponse
    {
        $request->validate([
            'phone'         => ['required', 'string'],
            'providerToken' => ['required', 'string'],
        ]);

        try {
            $identity = app(\App\Contracts\Auth\AuthVerificationProvider::class)
                ->verifyPhone($request->phone, $request->providerToken);

            return response()->json([
                'success' => true,
                'message' => 'Phone verified.',
                'data'    => ['providerUid' => $identity->providerUid, 'phone' => $identity->phone],
            ]);
        } catch (AuthException $e) {
            throw $e;
        } catch (Throwable) {
            throw AuthException::phoneVerificationFailed();
        }
    }

    // ── POST /api/v1/auth/email-otp/send ────────────────────────────────────

    public function sendEmailOtp(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $email   = strtolower(trim($request->email));
        $otp     = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $cacheKey = 'email_otp_' . hash('sha256', $email);

        Cache::put($cacheKey, $otp, now()->addMinutes(10));

        // TODO production: send via mail driver (Mailgun/SES/SMTP)
        // Mail::to($email)->send(new OtpMail($otp));

        $response = [
            'success' => true,
            'message' => 'OTP sent to ' . $email,
            'data'    => ['email' => $email],
        ];

        // Return OTP in response only in local dev — remove before production
        if (app()->environment('local')) {
            $response['data']['dev_otp'] = $otp;
        }

        return response()->json($response);
    }

    // ── POST /api/v1/auth/email-otp/verify ──────────────────────────────────

    public function verifyEmailOtp(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'otp'   => ['required', 'string', 'size:6'],
        ]);

        $email    = strtolower(trim($request->email));
        $cacheKey = 'email_otp_' . hash('sha256', $email);
        $stored   = Cache::get($cacheKey);

        if (!$stored || $stored !== $request->otp) {
            return response()->json([
                'success' => false,
                'code'    => 'INVALID_OTP',
                'message' => 'Invalid or expired OTP. Please try again.',
            ], 422);
        }

        Cache::forget($cacheKey);

        return response()->json([
            'success' => true,
            'message' => 'Email verified successfully.',
            'data'    => ['email' => $email, 'emailVerified' => true],
        ]);
    }

    // ── POST /api/v1/auth/verify-email (Firebase token — kept for compat) ───

    public function verifyEmail(Request $request): JsonResponse
    {
        $request->validate([
            'email'         => ['required', 'email'],
            'providerToken' => ['required', 'string'],
        ]);

        try {
            $identity = app(\App\Contracts\Auth\AuthVerificationProvider::class)
                ->verifyEmail($request->email, $request->providerToken);

            return response()->json([
                'success' => true,
                'message' => 'Email verified.',
                'data'    => ['providerUid' => $identity->providerUid, 'email' => $identity->email],
            ]);
        } catch (AuthException $e) {
            throw $e;
        } catch (Throwable) {
            throw AuthException::emailVerificationFailed();
        }
    }

    // ── POST /api/v1/auth/social/validate ────────────────────────────────────

    public function socialValidate(Request $request): JsonResponse
    {
        $request->validate([
            'provider'      => ['required', 'string', 'in:google,apple'],
            'providerToken' => ['required', 'string'],
        ]);

        try {
            $identity = app(\App\Contracts\Auth\AuthVerificationProvider::class)
                ->validateToken($request->provider, $request->providerToken);

            return response()->json([
                'success' => true,
                'message' => 'Social token validated.',
                'data'    => [
                    'providerUid' => $identity->providerUid,
                    'email'       => $identity->email,
                    'name'        => $identity->name,
                    'provider'    => $identity->provider,
                ],
            ]);
        } catch (AuthException $e) {
            throw $e;
        } catch (Throwable) {
            throw AuthException::socialValidationFailed();
        }
    }

    private function serverError(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'code'    => 'SERVER_ERROR',
            'message' => 'An unexpected error occurred. Please try again.',
        ], 500);
    }
}
