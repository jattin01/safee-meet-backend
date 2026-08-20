<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\Auth\AuthException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CheckUserExistsRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\Auth\UserResource;
use App\Models\User;
use App\Services\Auth\AuthService;
use App\Services\SafetyPointService;
use App\Services\Sms\TelesignSmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Kreait\Firebase\Contract\Auth as FirebaseAuth;
use Kreait\Firebase\Exception\Auth\UserNotFound;
use Throwable;

/**
 * AuthController - Handles two authentication flows:
 * 
 * ═══════════════════════════════════════════════════════════════════════════════
 * FLOW 1: EMAIL/SOCIAL AUTHENTICATION (Google, Apple, Email)
 * ═══════════════════════════════════════════════════════════════════════════════
 * Single API call - Direct login with Firebase token verification
 * 
 * Endpoint: POST /api/v1/auth/login
 * 
 * Request Body:
 * {
 *   "provider": "google|apple|email",
 *   "providerToken": "<firebase_id_token>",
 *   "name": "John Doe",
 *   "email": "john@example.com",
 *   "accountType": "normal|employer",
 *   "companyName": "Company Inc",  // optional, only for employer accounts
 *   "consentAccepted": true
 * }
 * 
 * Response:
 * {
 *   "success": true,
 *   "message": "Registration successful." | "Login successful.",
 *   "data": {
 *     "accessToken": "...",
 *     "refreshToken": null,
 *     "user": {...},
 *     "isNewUser": true|false
 *   }
 * }
 * 
 * ═══════════════════════════════════════════════════════════════════════════════
 * FLOW 2: PHONE AUTHENTICATION (3-Step Process with Telesign OTP)
 * ═══════════════════════════════════════════════════════════════════════════════
 * 
 * Step 1: Send OTP
 * ────────────────
 * Endpoint: POST /api/v1/auth/send-otp
 * 
 * Request:
 * {
 *   "phone": "+919812374311"
 * }
 * 
 * Response:
 * {
 *   "success": true,
 *   "message": "OTP sent successfully to +919812374311",
 *   "data": {
 *     "phone": "+919812374311",
 *     "expires_in": 600,
 *     "dev_otp": "695347"  // only in local environment
 *   }
 * }
 * 
 * Step 2: Verify OTP
 * ──────────────────
 * Endpoint: POST /api/v1/auth/verify-otp
 * 
 * Request:
 * {
 *   "phone": "+919812374311",
 *   "otp": "695347"
 * }
 * 
 * Response:
 * {
 *   "success": true,
 *   "message": "OTP verified successfully.",
 *   "data": {
 *     "phone": "+919812374311",
 *     "verified": true
 *   }
 * }
 * 
 * Step 3: Register/Login
 * ──────────────────────
 * Endpoint: POST /api/v1/auth/register
 * 
 * Request (Employer):
 * {
 *   "phone": "+919812374311",
 *   "provider": "phone",
 *   "name": "Rahul Sharma",
 *   "email": "rahul@company.com",
 *   "accountType": "employer",
 *   "companyName": "Acme Pvt Ltd",
 *   "consentAccepted": true
 * }
 * 
 * Request (Normal User):
 * {
 *   "phone": "+919812374311",
 *   "provider": "phone",
 *   "name": "Jane Doe",
 *   "email": "jane@example.com",  // optional
 *   "accountType": "normal",
 *   "consentAccepted": true
 * }
 * 
 * Response (New User - 201):
 * {
 *   "success": true,
 *   "message": "Registration successful.",
 *   "data": {
 *     "accessToken": "...",
 *     "refreshToken": "...",
 *     "user": {...},
 *     "isNewUser": true
 *   }
 * }
 * 
 * Response (Existing User - 200):
 * {
 *   "success": true,
 *   "message": "Login successful.",
 *   "data": {
 *     "accessToken": "...",
 *     "refreshToken": null,
 *     "user": {...},
 *     "isNewUser": false
 *   }
 * }
 * 
 * ═══════════════════════════════════════════════════════════════════════════════
 */
class AuthController extends Controller
{
    // ── POST /api/v1/auth/register ────────────────────────────────────────────

    public function register(RegisterRequest $request, AuthService $authService): JsonResponse
    {
        try {
            $result = $authService->register($request->validated());

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

    public function login(LoginRequest $request, AuthService $authService): JsonResponse
    {
        try {
            $result = $authService->login($request->validated());

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

    public function logout(Request $request, AuthService $authService): JsonResponse
    {
        $authService->logout($request->user());

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

    public function checkUserExists(CheckUserExistsRequest $request, AuthService $authService): JsonResponse
    {
        $exists = $authService->checkUserExists(
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

    // ── POST /api/v1/auth/phone-otp/send ─────────────────────────────────────
    
  public function sendPhoneOtp(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => [
                'required',
                'string',
                'regex:/^\+?[1-9]\d{7,14}$/'
            ],
            'name' => [
                'nullable',
                'string',
                'max:150'
            ],
        ]);

        $phone = $this->normalizePhone($request->input('phone'));
        $name = $request->input('name');

        /*
        |--------------------------------------------------------------------------
        | Static OTP Test Numbers
        |--------------------------------------------------------------------------
        |
        | India:
        | +919812374311
        |
        | International:
        | +(732)207-5598
        | +17322075598
        |
        */

        $isTestPhone = in_array($phone, [
            '+919812374311',
            '9812374311',
            '+17322075598',
            '17322075598',
        ], true);

        /*
        |--------------------------------------------------------------------------
        | Generate OTP
        |--------------------------------------------------------------------------
        */

        $otp = $isTestPhone
            ? '123456'
            : str_pad(
                random_int(0, 999999),
                6,
                '0',
                STR_PAD_LEFT
            );

        $cacheKey = 'phone_otp_' . hash('sha256', $phone);

        /*
        |--------------------------------------------------------------------------
        | Store OTP in Cache
        |--------------------------------------------------------------------------
        */

        Cache::put(
            $cacheKey,
            [
                'otp' => $otp,
                'name' => $name,
                'phone' => $phone,
                'attempts' => 0,
            ],
            now()->addMinutes(10)
        );

        /*
        |--------------------------------------------------------------------------
        | Send OTP via Telesign
        |--------------------------------------------------------------------------
        |
        | Test numbers:
        | SMS will NOT be sent.
        |
        | Other numbers:
        | SMS will be sent through Telesign.
        |
        */

        if (!$isTestPhone) {

            $smsSent = app(TelesignSmsService::class)->sendOtp($phone, $otp);

        } else {

            // Test phone - skip SMS
            $smsSent = true;

            Log::info('Test phone - using static OTP 123456', [
                'phone' => $phone,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Response Data
        |--------------------------------------------------------------------------
        */

        $data = [
            'phone' => $phone,
            'expires_in' => 600,
        ];

        // Only expose OTP in local environment
        if (app()->environment('local')) {
            $data['dev_otp'] = $otp;
        }

        /*
        |--------------------------------------------------------------------------
        | SMS Failed
        |--------------------------------------------------------------------------
        */

        if (!$smsSent && !app()->environment('local')) {

            return response()->json([
                'success' => false,
                'message' => 'Failed to send OTP. Please try again.',
                'error' => 'SMS delivery failed',
            ], 500);
        }

        /*
        |--------------------------------------------------------------------------
        | Success Response
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' => true,
            'message' => 'OTP sent successfully to ' . $phone,
            'data' => $data,
        ]);
    }

    // ── POST /api/v1/auth/resend-otp ────────────────────────────────────────

    public function resendPhoneOtp(Request $request): JsonResponse
    {
        return $this->sendPhoneOtp($request);
    }

    public function sendRegisterOtp(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => ['required', 'string', 'regex:/^\+?[1-9]\d{7,14}$/'],
            'name' => ['nullable', 'string', 'max:150'],
        ]);

        $phone = $this->normalizePhone($request->input('phone'));

        if (User::where('phone', $phone)->exists()) {
            return response()->json([
                'status' => false,
                'message' => 'An account already exists for this mobile number. Please log in.',
            ], 409);
        }

        $request->merge(['phone' => $phone]);

        return $this->sendPhoneOtp($request);
    }

    // ── POST /api/v1/auth/verify-otp ──────────────────────────────────────────
    // Step 2: Verify OTP only (does not register/login user)
    
    public function verifyPhoneOtpOnly(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => ['required', 'string', 'regex:/^\+?[1-9]\d{7,14}$/'],
            'otp'   => ['required', 'string', 'size:6'],
            'name'  => ['nullable', 'string', 'max:150'],
        ]);

        $phone = $this->normalizePhone($request->input('phone'));
        $name = $request->input('name');
        $cacheKey = 'phone_otp_' . hash('sha256', $phone);
        $stored = Cache::get($cacheKey);

        if (!$stored || !is_array($stored)) {
            return response()->json([
                'success' => false,
                'code'    => 'INVALID_OTP',
                'message' => 'Invalid or expired OTP. Please try again.',
            ], 422);
        }

        // Check max attempts
        if (($stored['attempts'] ?? 0) >= 5) {
            Cache::forget($cacheKey);
            return response()->json([
                'success' => false,
                'code'    => 'TOO_MANY_ATTEMPTS',
                'message' => 'Too many incorrect attempts. Please request a new OTP.',
            ], 429);
        }

        // Verify OTP
        if ($stored['otp'] !== $request->input('otp')) {
            $stored['attempts'] = ($stored['attempts'] ?? 0) + 1;
            Cache::put($cacheKey, $stored, now()->addMinutes(10));

            return response()->json([
                'success' => false,
                'code'    => 'INVALID_OTP',
                'message' => 'The OTP is incorrect.',
                'attempts_remaining' => 5 - $stored['attempts'],
            ], 422);
        }

        // OTP is valid - mark as verified but don't clear cache yet
        $stored['verified'] = true;
        $stored['verified_at'] = now()->timestamp;
        Cache::put($cacheKey, $stored, now()->addMinutes(10));

        // Sync with Firebase - create or get Firebase user
        try {
            $firebaseAuth = app(FirebaseAuth::class);
            $firebaseUid = null;
            $firebaseUserFound = false;

            // Try to find existing Firebase user by phone
            try {
                $firebaseUser = $firebaseAuth->getUserByPhoneNumber($phone);
                $firebaseUid = $firebaseUser->uid;
                $firebaseUserFound = true;
                
                Log::info('Firebase user found by phone', [
                    'phone' => $phone,
                    'firebase_uid' => $firebaseUid,
                ]);
            } catch (UserNotFound) {
                // Create new Firebase user
                $displayName = $name ?? $stored['name'] ?? 'SAFEE User';
                
                $userData = [
                    'phoneNumber' => $phone,
                    'displayName' => $displayName,
                ];
                
                $firebaseUser = $firebaseAuth->createUser($userData);
                $firebaseUid = $firebaseUser->uid;
                $firebaseUserFound = false;
                
                Log::info('Firebase user created', [
                    'phone' => $phone,
                    'display_name' => $displayName,
                    'firebase_uid' => $firebaseUid,
                ]);
            }

            // Generate custom token
            $customToken = $firebaseAuth->createCustomToken($firebaseUid)->toString();

            // Store Firebase UID in cache for later use in registration
            $stored['firebase_uid'] = $firebaseUid;
            $stored['firebase_custom_token'] = $customToken;
            Cache::put($cacheKey, $stored, now()->addMinutes(10));

            return response()->json([
                'success' => true,
                'message' => 'OTP verified successfully.',
                'data' => [
                    'phone' => $phone,
                    'verified' => true,
                    'firebaseUid' => $firebaseUid,
                    'firebaseCustomToken' => $customToken,
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('Firebase sync failed during OTP verification', [
                'phone' => $phone,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return success even if Firebase fails (don't block the flow)
            return response()->json([
                'success' => true,
                'message' => 'OTP verified successfully.',
                'data' => [
                    'phone' => $phone,
                    'verified' => true,
                ],
            ]);
        }
    }

    // ── POST /api/v1/auth/phone-otp/verify ────────────────────────────────────

    public function verifyPhoneOtp(Request $request, AuthService $authService): JsonResponse
    {
        $request->validate([
            'phone'           => ['required', 'string', 'regex:/^\+?[1-9]\d{7,14}$/'],
            'otp'             => ['required', 'string', 'size:6'],
            'name'            => ['nullable', 'string', 'max:150'],
            'email'           => ['nullable', 'email', 'max:200'],
            'consentAccepted' => ['nullable', 'boolean'],
            'accountType'     => ['nullable', 'string', 'in:normal,employer'],
            'companyName'     => ['nullable', 'string', 'max:255'],
        ]);

        $phone = $this->normalizePhone($request->input('phone'));
        $cacheKey = 'phone_otp_' . hash('sha256', $phone);
        $stored = Cache::get($cacheKey);

        if (!$stored || !is_array($stored)) {
            return response()->json([
                'success' => false,
                'code'    => 'INVALID_OTP',
                'message' => 'Invalid or expired OTP. Please try again.',
            ], 422);
        }

        // Check max attempts
        if (($stored['attempts'] ?? 0) >= 5) {
            Cache::forget($cacheKey);
            return response()->json([
                'success' => false,
                'code'    => 'TOO_MANY_ATTEMPTS',
                'message' => 'Too many incorrect attempts. Please request a new OTP.',
            ], 429);
        }

        // Verify OTP
        if ($stored['otp'] !== $request->input('otp')) {
            $stored['attempts'] = ($stored['attempts'] ?? 0) + 1;
            Cache::put($cacheKey, $stored, now()->addMinutes(10));

            return response()->json([
                'success' => false,
                'code'    => 'INVALID_OTP',
                'message' => 'The OTP is incorrect.',
                'attempts_remaining' => 5 - $stored['attempts'],
            ], 422);
        }

        // OTP is valid, clear cache
        Cache::forget($cacheKey);

        // Check if user exists
        $user = User::where('phone', $phone)->first();

        if ($user) {
            // Login existing user
            $this->assertAccountIsActive($user);
            $user->update(['last_login_at' => now(), 'last_seen_at' => now()]);
            
            $user->tokens()->delete();
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Login successful.',
                'data'    => [
                    'accessToken'  => $token,
                    'refreshToken' => null,
                    'user'         => new UserResource($user),
                    'isNewUser'    => false,
                ],
            ]);
        } else {
            // Register new user
            $payload = [
                'name'            => $request->input('name') ?? $stored['name'] ?? 'SAFEE User',
                'email'           => $request->input('email'),
                'phone'           => $phone,
                'provider'        => 'phone',
                'consentAccepted' => $request->input('consentAccepted', true),
                'accountType'     => $request->input('accountType', 'normal'),
                'companyName'     => $request->input('companyName'),
            ];

            $result = $authService->register($payload);

            return response()->json([
                'success' => true,
                'message' => 'Registration successful.',
                'data'    => [
                    'accessToken'  => $result['accessToken'],
                    'refreshToken' => $result['refreshToken'],
                    'user'         => new UserResource($result['user']),
                    'isNewUser'    => true,
                ],
            ], 201);
        }
    }

    // ── POST /api/v1/auth/login ───────────────────────────────────────────────
    // Phone login after OTP verification
    
    public function loginUser(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => ['required', 'string', 'regex:/^\+?[1-9]\d{7,14}$/'],
        ]);

        $phone = $this->normalizePhone($request->input('phone'));
        $cacheKey = 'phone_otp_' . hash('sha256', $phone);
        $stored = Cache::get($cacheKey);

        // Debug logging
        Log::info('Login attempt', [
            'phone' => $phone,
            'cache_key' => $cacheKey,
            'stored_data' => $stored,
            'has_stored' => !is_null($stored),
            'is_array' => is_array($stored),
            'verified' => $stored['verified'] ?? 'not_set',
            'verified_at' => $stored['verified_at'] ?? 'not_set',
        ]);

        // Check if OTP was verified
        if (!$stored || !is_array($stored) || !($stored['verified'] ?? false)) {
            Log::warning('OTP not verified for login', ['phone' => $phone, 'stored' => $stored]);
            return response()->json([
                'success' => false,
                'code'    => 'OTP_NOT_VERIFIED',
                'message' => 'Please verify OTP first before login.',
            ], 422);
        }

        // Check if verification is still valid (within 10 minutes)
        $verifiedAt = $stored['verified_at'] ?? 0;
        $currentTime = now()->timestamp;
        $timeDiff = $currentTime - $verifiedAt;
        
        if ($verifiedAt === 0 || $timeDiff > 600) {
            Cache::forget($cacheKey);
            return response()->json([
                'success' => false,
                'code'    => 'VERIFICATION_EXPIRED',
                'message' => 'OTP verification expired. Please verify again.',
            ], 422);
        }

        try {
            // Check if user exists
            $user = User::where('phone', $phone)->first();
            
            if (!$user) {
                // User not registered
                Cache::forget($cacheKey);
                
                return response()->json([
                    'success' => false,
                    'code'    => 'USER_NOT_REGISTERED',
                    'message' => 'This phone number is not registered. Please use the register endpoint.',
                    'data'    => [
                        'phone' => $phone,
                        'registered' => false,
                    ],
                ], 404);
            }

            // User exists, perform login
            $this->assertAccountIsActive($user);
            $user->update(['last_login_at' => now(), 'last_seen_at' => now()]);
            
            $user->tokens()->delete();
            $token = $user->createToken('auth_token')->plainTextToken;

            Cache::forget($cacheKey);

            return response()->json([
                'success' => true,
                'message' => 'Login successful.',
                'data'    => [
                    'accessToken'  => $token,
                    'refreshToken' => null,
                    'user'         => new UserResource($user),
                    'isNewUser'    => false,
                ],
            ]);
        } catch (AuthException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Login error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return $this->serverError();
        }
    }

    // ── POST /api/v1/auth/register ────────────────────────────────────────────
    // Step 3: Complete registration after OTP verification
    
    public function registerUser(Request $request, AuthService $authService): JsonResponse
    {
        $request->merge([
            'phone' => $request->filled('phone')
                ? $this->normalizePhone($request->input('phone'))
                : $request->input('phone'),
            'email' => $request->filled('email')
                ? strtolower(trim($request->input('email')))
                : $request->input('email'),
        ]);

        $validator = Validator::make($request->all(), [
            'phone'           => ['required', 'string', 'regex:/^\+?[1-9]\d{7,14}$/', 'unique:users,phone'],
            'provider'        => ['required', 'string', 'in:phone,email'],
            'name'            => ['required', 'string', 'max:150'],
            'email'           => ['nullable', 'email', 'max:200', 'unique:users,email'],
            'accountType'     => ['required', 'string', 'in:normal,employer'],
            'companyName'     => ['nullable', 'string', 'max:255'],
            'consentAccepted' => ['required', 'boolean'],
        ], [
            'phone.unique' => 'An account already exists for this mobile number. Please log in.',
            'email.unique' => 'This email address is already registered with another account.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $phone = $this->normalizePhone($request->input('phone'));
        $cacheKey = 'phone_otp_' . hash('sha256', $phone);
        $stored = Cache::get($cacheKey);
       

        // Debug logging
        Log::info('Register attempt', [
            'phone' => $phone,
            'cache_key' => $cacheKey,
            'stored_data' => $stored,
            'has_stored' => !is_null($stored),
            'is_array' => is_array($stored),
            'verified' => $stored['verified'] ?? 'not_set',
            'verified_at' => $stored['verified_at'] ?? 'not_set',
        ]);

        // Check if OTP was verified
        if (!$stored || !is_array($stored) || !($stored['verified'] ?? false)) {
            Log::warning('OTP not verified', ['phone' => $phone, 'stored' => $stored]);
            return response()->json([
                'success' => false,
                'code'    => 'OTP_NOT_VERIFIED',
                'message' => 'Please verify OTP first before registration.',
            ], 422);
        }

        // Check if verification is still valid (within 10 minutes)
        $verifiedAt = $stored['verified_at'] ?? 0;
        $currentTime = now()->timestamp;
        $timeDiff = $currentTime - $verifiedAt;
        
        Log::info('Verification time check', [
            'verified_at' => $verifiedAt,
            'current_time' => $currentTime,
            'time_diff' => $timeDiff,
            'is_expired' => $timeDiff > 600,
        ]);
        
        if ($verifiedAt === 0 || $timeDiff > 600) {
            Cache::forget($cacheKey);
            Log::warning('Verification expired', [
                'phone' => $phone,
                'verified_at' => $verifiedAt,
                'time_diff' => $timeDiff,
            ]);
            return response()->json([
                'success' => false,
                'code'    => 'VERIFICATION_EXPIRED',
                'message' => 'OTP verification expired. Please verify again.',
            ], 422);
        }

        try {
            // Check if user already exists by phone
            $existingUser = User::where('phone', $phone)->first();
            
            if ($existingUser) {
                // User already registered with this phone - return error
                Cache::forget($cacheKey);
                
                return response()->json([
                    'success' => false,
                    'code'    => 'PHONE_ALREADY_REGISTERED',
                    'message' => 'This phone number is already registered. Please use the login endpoint instead.',
                    'data'    => [
                        'phone' => $phone,
                        'registered' => true,
                    ],
                ], 409); // 409 Conflict
            }

            // Check if email already exists (if email is provided)
            $email = $request->input('email');
            if ($email) {
                $emailExists = User::where('email', strtolower(trim($email)))->first();
                
                if ($emailExists) {
                    // Email already registered - return error
                    Cache::forget($cacheKey);
                    
                    return response()->json([
                        'success' => false,
                        'code'    => 'EMAIL_ALREADY_REGISTERED',
                        'message' => 'This email address is already registered with another account.',
                        'data'    => [
                            'email' => $email,
                            'registered' => true,
                        ],
                    ], 409); // 409 Conflict
                }
            }

            // Register new user (phone registration - no providerToken)
            $user = DB::transaction(function () use ($request, $phone, $stored) {
                $userData = [
                    'safee_id'        => $this->generateSafeeId(),
                    'account_type'    => $request->input('accountType'),
                    'auth_provider'   => 'phone',
                    'name'            => $request->input('name'),
                    'display_name'    => $request->input('name'),
                    'email'           => $request->input('email') ? strtolower(trim($request->input('email'))) : null,
                    'phone'           => $phone,
                    'phone_verified_at' => now(),
                    'firebase_uid'    => $stored['firebase_uid'] ?? null,
                    'status'          => 'active',
                    'onboarding_status' => 'completed',
                    'kyc_status'      => 'not_started',
                    'trust_score'     => 0,
                    'trust_tier'      => 'low',
                    'is_chat_enabled' => true,
                    'is_meeting_enabled' => true,
                    'is_sos_enabled'  => true,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ];

                // Add company_name only if provided
                if ($request->input('companyName')) {
                    $userData['company_name'] = $request->input('companyName');
                }

                $user = User::create($userData);

                // Create default notification preferences
                $user->notificationPreferences()->create([]);

                app(SafetyPointService::class)->addPoints(
                    userId: $user->id,
                    eventKey: 'phone_verified',
                    points: 10,
                    referenceType: 'user',
                    description: 'Phone number verified during registration.'
                );

                return $user;
            });

            // Clear OTP cache after successful registration
            Cache::forget($cacheKey);

            // Issue Sanctum token
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Registration successful.',
                'data'    => [
                    'accessToken'  => $token,
                    'refreshToken' => null,
                    'user'         => new UserResource($user),
                    'isNewUser'    => true,
                ],
            ], 201);
        } catch (AuthException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Registration error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return $this->serverError();
        }
    }

    // ── POST /api/v1/auth/unified ─────────────────────────────────────────────
    // Unified endpoint: handles both email (Firebase token) and phone (OTP) auth

    public function unifiedAuth(Request $request, AuthService $authService): JsonResponse
    {
        $request->validate([
            'name'            => ['nullable', 'string', 'max:150'],
            'email'           => ['nullable', 'email', 'max:200'],
            'phone'           => ['nullable', 'string', 'regex:/^\+?[1-9]\d{7,14}$/'],
            'provider'        => ['required', 'string', 'in:email,phone,google,apple'],
            'providerToken'   => ['required', 'string'],
            'consentAccepted' => ['nullable', 'boolean'],
            'accountType'     => ['nullable', 'string', 'in:normal,employer'],
            'companyName'     => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $provider = $request->input('provider');
            $providerToken = $request->input('providerToken');

            // Verify the provider token (works for email, google, apple, phone)
            $identity = app(\App\Contracts\Auth\AuthVerificationProvider::class)
                ->validateToken($provider, $providerToken);

            // Check if user exists
            $user = $this->findUserByIdentity($identity, $request->input('phone'));

            if ($user) {
                // Login existing user
                $this->assertAccountIsActive($user);
                $user->update(['last_login_at' => now(), 'last_seen_at' => now()]);
                
                // Issue Sanctum token
                $user->tokens()->delete(); // single active token per user
                $token = $user->createToken('auth_token')->plainTextToken;

                return response()->json([
                    'success' => true,
                    'message' => 'Login successful.',
                    'data'    => [
                        'accessToken'  => $token,
                        'refreshToken' => null,
                        'user'         => new UserResource($user),
                        'isNewUser'    => false,
                    ],
                ]);
            } else {
                // Register new user
                $payload = [
                    'name'            => $request->input('name') ?? $identity->name ?? 'SAFEE User',
                    'email'           => $request->input('email') ?? $identity->email,
                    'phone'           => $request->input('phone') ?? $identity->phone,
                    'provider'        => $provider,
                    'providerToken'   => $providerToken,
                    'consentAccepted' => $request->input('consentAccepted', true),
                    'accountType'     => $request->input('accountType', 'normal'),
                    'companyName'     => $request->input('companyName'),
                ];

                $result = $authService->register($payload);

                return response()->json([
                    'success' => true,
                    'message' => 'Registration successful.',
                    'data'    => [
                        'accessToken'  => $result['accessToken'],
                        'refreshToken' => $result['refreshToken'],
                        'user'         => new UserResource($result['user']),
                        'isNewUser'    => true,
                    ],
                ], 201);
            }
        } catch (AuthException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Unified auth error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return $this->serverError();
        }
    }

    // Helper to find user by identity
    private function findUserByIdentity($identity, ?string $phoneFromRequest): ?User
    {
        // Try to find by provider UID first
        if ($identity->providerUid) {
            $user = User::where('firebase_uid', $identity->providerUid)->first();
            if ($user) return $user;
        }

        // Try to find by email
        if ($identity->email) {
            $user = User::where('email', strtolower(trim($identity->email)))->first();
            if ($user) return $user;
        }

        // Try to find by phone (from identity or request)
        $phone = $identity->phone ?? $phoneFromRequest;
        if ($phone) {
            $user = User::where('phone', $phone)->first();
            if ($user) return $user;
        }

        return null;
    }

    // Helper to check account status
    private function assertAccountIsActive(User $user): void
    {
        match ($user->status) {
            'blocked'   => throw AuthException::accountBlocked(),
            'suspended' => throw AuthException::accountBlocked(),
            'deleted'   => throw AuthException::userNotRegistered(),
            'pending'   => throw AuthException::accountInactive(),
            default     => null,
        };
    }

    private function serverError(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'code'    => 'SERVER_ERROR',
            'message' => 'An unexpected error occurred. Please try again.',
        ], 500);
    }

    private function normalizePhone(string $phone): string
    {
        $phone = trim($phone);
        $prefix = str_starts_with($phone, '+') ? '+' : '';
        return $prefix . preg_replace('/\D+/', '', $phone);
    }

    private function generateSafeeId(): string
    {
        $column = User::safeeColumn();

        do {
            $id = 'SM' . strtoupper(Str::random(8));
        } while (User::where($column, $id)->exists());

        return $id;
    }
}
