<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PushNotificationService;
use App\Services\Sms\TelesignSmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Services\SafetyPointService;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\JsonResponse;
use Kreait\Firebase\Contract\Auth as FirebaseAuth;
use Kreait\Firebase\Exception\Auth\UserNotFound;


class AuthController extends Controller
{
    private const OTP_TTL_MINUTES = 10;

    private const OTP_MAX_ATTEMPTS = 5;

    /**
     * Start registration by sending an OTP to a new mobile number.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $this->validatePhoneRequest($request, true);

        if (User::where('phone', $validated['phone'])->exists()) {
            return response()->json([
                'message' => 'An account already exists for this mobile number. Please log in.',
            ], 409);
        }

        return $this->issueOtp($validated['phone'], 'register', $validated['name']);
    }

    /**
     * Start login by sending an OTP to an existing mobile number.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $this->validatePhoneRequest($request);

        if (! User::where('phone', $validated['phone'])->exists()) {
            return response()->json([
                'message' => 'No account exists for this mobile number. Please register.',
            ], 404);
        }

        return $this->issueOtp($validated['phone'], 'login');
    }

    /**
     * Convenient single endpoint: login existing users or register new users.
     */
    public function loginOrRegister(Request $request): JsonResponse
    {
        $validated = $this->validatePhoneRequest($request);
        $userExists = User::where('phone', $validated['phone'])->exists();

        if (! $userExists) {
            $request->validate(['name' => ['required', 'string', 'max:255']]);
        }

        return $this->issueOtp(
            $validated['phone'],
            $userExists ? 'login' : 'register',
            $request->string('name')->trim()->toString() ?: null,
        );
    }

    /**
     * Verify the OTP, create the user when necessary, and issue a bearer token.
     */
    // public function verifyOtp(Request $request): JsonResponse
    // {
       
    //     $request->merge(['phone' => $this->normalizePhone((string) $request->input('phone'))]);

    //     $validated = $request->validate([
    //         'phone' => ['required', 'regex:/^\+?[1-9]\d{7,14}$/'],
    //         'otp' => ['required', 'digits:6'],
    //         'device_name' => ['nullable', 'string', 'max:100'],
    //     ]);

    //     $cacheKey = $this->otpCacheKey($validated['phone']);
    //     $challenge = Cache::get($cacheKey);
       

    //     if (! is_array($challenge)) {
    //         return response()->json([
    //             'message' => 'The OTP is invalid or has expired. Please request a new OTP.',
    //         ], 422);
    //     }

    //     if (($challenge['attempts'] ?? 0) >= self::OTP_MAX_ATTEMPTS) {
    //         Cache::forget($cacheKey);

    //         return response()->json([
    //             'message' => 'Too many incorrect attempts. Please request a new OTP.',
    //         ], 429);
    //     }

    //     if (! Hash::check($validated['otp'], $challenge['otp_hash'])) {
    //         $challenge['attempts'] = ($challenge['attempts'] ?? 0) + 1;
    //         Cache::put($cacheKey, $challenge, now()->addMinutes(self::OTP_TTL_MINUTES));

    //         return response()->json([
    //             'message' => 'The OTP is incorrect.',
    //             'attempts_remaining' => self::OTP_MAX_ATTEMPTS - $challenge['attempts'],
    //         ], 422);
    //     }

    //     $user = DB::transaction(function () use ($validated, $challenge): User {
    //         $user = User::where('phone', $validated['phone'])->lockForUpdate()->first();

    //         if (($challenge['intent'] ?? null) === 'login' && ! $user) {
    //             throw ValidationException::withMessages([
    //                 'phone' => ['The account no longer exists. Please register again.'],
    //             ]);
    //         }

    //         if (! $user) {
    //             $user = User::create([
    //                 'name' => $challenge['name'],
    //                 'phone' => $validated['phone'],
    //                 'phone_verified_at' => now(),
    //                 'safee_pin' => User::generateSafeePin(),
    //                 'subscription_status' => 'trial',
    //                 'status' => 'active',
    //                 'auth_provider' => 'phone',
    //             ]);
    //             app(SafetyPointService::class)->addPoints(
    //                 userId: $user->id,
    //                 eventKey: 'phone_verified',
    //                 points: 10,
    //                 referenceType: 'user',
    //                 description: 'Phone number verified during registration.'
    //             );
    //         } elseif (! $user->phone_verified_at) {
    //             $user->forceFill(['phone_verified_at' => now()])->save();
    //         }

    //         return $user;
    //     });

    //     Cache::forget($cacheKey);

    //     app(PushNotificationService::class)->sendToUser(
    //         $user,
    //         'Registration successful',
    //         ($challenge['intent'] ?? null) === 'register'
    //             ? 'Your account was created successfully.'
    //             : 'You are now logged in successfully.',
    //         [
    //             'type' => 'account_registered',
    //             'user_id' => (string) $user->id,
    //             'flow' => $challenge['intent'] ?? 'login',
    //         ],
    //     );

    //     $token = $user->createToken($validated['device_name'] ?? 'safee-meet-app')->plainTextToken;
    //     $firebaseData = $this->syncFirebaseUser($user, $validated['phone'], $challenge['name'] ?? null);

    //     return response()->json([
    //         'message' => ($challenge['intent'] ?? null) === 'register'
    //             ? 'Registration successful.'
    //             : 'Login successful.',
    //         'data' => [
    //             'token_type' => 'Bearer',
    //             'access_token' => $token,
    //             'refresh_token' => null,
    //             'firebase_custom_token' => $firebaseData['custom_token'],
    //             'firebase_uid' => $firebaseData['firebase_uid'],
    //             'is_new_user' => ($challenge['intent'] ?? null) === 'register',
    //             'user' => $user,
    //         ],
    //     ], ($challenge['intent'] ?? null) === 'register' ? 201 : 200);
    // }

    // public function verifyOtp(Request $request): JsonResponse
    // {
    //     $request->merge([
    //         'phone' => $this->normalizePhone(
    //             (string) $request->input('phone')
    //         ),
    //     ]);

    //     $validated = $request->validate([
    //         'phone' => ['required', 'regex:/^\+?[1-9]\d{7,14}$/'],
    //         'otp' => ['required', 'digits:6'],
    //         'device_name' => ['nullable', 'string', 'max:100'],
    //     ]);

    //     $cacheKey = $this->otpCacheKey($validated['phone']);

    //     $challenge = Cache::get($cacheKey);

    //     if (!is_array($challenge)) {
    //         return response()->json([
    //             'message' => 'The OTP is invalid or has expired. Please request a new OTP.',
    //         ], 422);
    //     }

    //     /*
    //     |--------------------------------------------------------------------------
    //     | Check Maximum OTP Attempts
    //     |--------------------------------------------------------------------------
    //     */

    //     if (($challenge['attempts'] ?? 0) >= self::OTP_MAX_ATTEMPTS) {
    //         Cache::forget($cacheKey);

    //         return response()->json([
    //             'message' => 'Too many incorrect attempts. Please request a new OTP.',
    //         ], 429);
    //     }

    //     /*
    //     |--------------------------------------------------------------------------
    //     | Static OTP for Specific International Number
    //     |--------------------------------------------------------------------------
    //     |
    //     | +(732)207-5598
    //     | +17322075598
    //     | 17322075598
    //     |
    //     | All will be normalized to:
    //     | 17322075598
    //     |
    //     */

    //     $phoneNumber = preg_replace('/\D+/', '', $validated['phone']);

    //     $isStaticOtpValid = (
    //         $phoneNumber === '17322075598'
    //         && $validated['otp'] === '123456'
    //     );

    //     /*
    //     |--------------------------------------------------------------------------
    //     | Normal OTP Verification
    //     |--------------------------------------------------------------------------
    //     */

    //     $isOtpValid = $isStaticOtpValid
    //         || Hash::check(
    //             $validated['otp'],
    //             $challenge['otp_hash']
    //         );

    //     /*
    //     |--------------------------------------------------------------------------
    //     | Invalid OTP
    //     |--------------------------------------------------------------------------
    //     */

    //     if (!$isOtpValid) {
    //         $challenge['attempts'] = ($challenge['attempts'] ?? 0) + 1;

    //         Cache::put(
    //             $cacheKey,
    //             $challenge,
    //             now()->addMinutes(self::OTP_TTL_MINUTES)
    //         );

    //         return response()->json([
    //             'message' => 'The OTP is incorrect.',
    //             'attempts_remaining' =>
    //                 self::OTP_MAX_ATTEMPTS - $challenge['attempts'],
    //         ], 422);
    //     }

    //     /*
    //     |--------------------------------------------------------------------------
    //     | Create / Find User
    //     |--------------------------------------------------------------------------
    //     */

    //     $user = DB::transaction(function () use ($validated, $challenge): User {

    //         $user = User::where(
    //             'phone',
    //             $validated['phone']
    //         )
    //         ->lockForUpdate()
    //         ->first();

    //         /*
    //         |--------------------------------------------------------------------------
    //         | Login - User Must Exist
    //         |--------------------------------------------------------------------------
    //         */

    //         if (
    //             ($challenge['intent'] ?? null) === 'login'
    //             && !$user
    //         ) {
    //             throw ValidationException::withMessages([
    //                 'phone' => [
    //                     'The account no longer exists. Please register again.'
    //                 ],
    //             ]);
    //         }

    //         /*
    //         |--------------------------------------------------------------------------
    //         | Register New User
    //         |--------------------------------------------------------------------------
    //         */

    //         if (!$user) {

    //             $user = User::create([
    //                 'name' => $challenge['name'] ?? null,
    //                 'phone' => $validated['phone'],
    //                 'phone_verified_at' => now(),
    //                 'safee_pin' => User::generateSafeePin(),
    //                 'subscription_status' => 'trial',
    //                 'status' => 'active',
    //                 'auth_provider' => 'phone',
    //             ]);

    //             app(SafetyPointService::class)->addPoints(
    //                 userId: $user->id,
    //                 eventKey: 'phone_verified',
    //                 points: 10,
    //                 referenceType: 'user',
    //                 description: 'Phone number verified during registration.'
    //             );

    //         } elseif (!$user->phone_verified_at) {

    //             /*
    //             |--------------------------------------------------------------------------
    //             | Mark Existing User Phone as Verified
    //             |--------------------------------------------------------------------------
    //             */

    //             $user->forceFill([
    //                 'phone_verified_at' => now(),
    //             ])->save();
    //         }

    //         return $user;
    //     });

    //     /*
    //     |--------------------------------------------------------------------------
    //     | Remove OTP From Cache
    //     |--------------------------------------------------------------------------
    //     */

    //     Cache::forget($cacheKey);

    //     /*
    //     |--------------------------------------------------------------------------
    //     | Push Notification
    //     |--------------------------------------------------------------------------
    //     */

    //     app(PushNotificationService::class)->sendToUser(
    //         $user,
    //         'Registration successful',
    //         ($challenge['intent'] ?? null) === 'register'
    //             ? 'Your account was created successfully.'
    //             : 'You are now logged in successfully.',
    //         [
    //             'type' => 'account_registered',
    //             'user_id' => (string) $user->id,
    //             'flow' => $challenge['intent'] ?? 'login',
    //         ],
    //     );

    //     /*
    //     |--------------------------------------------------------------------------
    //     | Create Access Token
    //     |--------------------------------------------------------------------------
    //     */

    //     $token = $user
    //         ->createToken(
    //             $validated['device_name'] ?? 'safee-meet-app'
    //         )
    //         ->plainTextToken;

    //     /*
    //     |--------------------------------------------------------------------------
    //     | Firebase Sync
    //     |--------------------------------------------------------------------------
    //     */

    //     $firebaseData = $this->syncFirebaseUser(
    //         $user,
    //         $validated['phone'],
    //         $challenge['name'] ?? null
    //     );

    //     /*
    //     |--------------------------------------------------------------------------
    //     | Response
    //     |--------------------------------------------------------------------------
    //     */

    //     return response()->json([
    //         'message' => ($challenge['intent'] ?? null) === 'register'
    //             ? 'Registration successful.'
    //             : 'Login successful.',

    //         'data' => [
    //             'token_type' => 'Bearer',
    //             'access_token' => $token,
    //             'refresh_token' => null,

    //             'firebase_custom_token' =>
    //                 $firebaseData['custom_token'],

    //             'firebase_uid' =>
    //                 $firebaseData['firebase_uid'],

    //             'is_new_user' =>
    //                 ($challenge['intent'] ?? null) === 'register',

    //             'user' => $user,
    //         ],
    //     ], ($challenge['intent'] ?? null) === 'register' ? 201 : 200);
    // }

    public function verifyOtp(Request $request): JsonResponse
    {
        $request->merge([
            'phone' => $this->normalizePhone(
                (string) $request->input('phone')
            ),
        ]);

        $validated = $request->validate([
            'phone' => ['required', 'regex:/^\+?[1-9]\d{7,14}$/'],
            'otp' => ['required', 'digits:6'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $cacheKey = $this->otpCacheKey($validated['phone']);

        $challenge = Cache::get($cacheKey);

        if (!is_array($challenge)) {
            return response()->json([
                'message' => 'The OTP is invalid or has expired. Please request a new OTP.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Static OTP Number
        |--------------------------------------------------------------------------
        */

        $phoneNumber = preg_replace('/\D+/', '', $validated['phone']);

        $isStaticNumber = $phoneNumber === '17322075598';

        /*
        |--------------------------------------------------------------------------
        | OTP Verification
        |--------------------------------------------------------------------------
        |
        | For static number:
        | Directly approve OTP without Hash::check()
        |
        | For all other numbers:
        | Normal Hash::check() verification
        |
        */

        if ($isStaticNumber) {

            // Direct approval - no hash check
            $isOtpValid = true;

        } else {

            if (($challenge['attempts'] ?? 0) >= self::OTP_MAX_ATTEMPTS) {
                Cache::forget($cacheKey);

                return response()->json([
                    'message' => 'Too many incorrect attempts. Please request a new OTP.',
                ], 429);
            }

            $isOtpValid = Hash::check(
                $validated['otp'],
                $challenge['otp_hash']
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Normal OTP Failed
        |--------------------------------------------------------------------------
        */

        if (!$isOtpValid) {
            $challenge['attempts'] = ($challenge['attempts'] ?? 0) + 1;

            Cache::put(
                $cacheKey,
                $challenge,
                now()->addMinutes(self::OTP_TTL_MINUTES)
            );

            return response()->json([
                'message' => 'The OTP is incorrect.',
                'attempts_remaining' =>
                    self::OTP_MAX_ATTEMPTS - $challenge['attempts'],
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Find / Create User
        |--------------------------------------------------------------------------
        */

        $user = DB::transaction(function () use ($validated, $challenge): User {

            $user = User::where(
                'phone',
                $validated['phone']
            )
            ->lockForUpdate()
            ->first();

            if (
                ($challenge['intent'] ?? null) === 'login'
                && !$user
            ) {
                throw ValidationException::withMessages([
                    'phone' => [
                        'The account no longer exists. Please register again.'
                    ],
                ]);
            }

            if (!$user) {

                $user = User::create([
                    'name' => $challenge['name'] ?? null,
                    'phone' => $validated['phone'],
                    'phone_verified_at' => now(),
                    'safee_pin' => User::generateSafeePin(),
                    'subscription_status' => 'trial',
                    'status' => 'active',
                    'auth_provider' => 'phone',
                ]);

                app(SafetyPointService::class)->addPoints(
                    userId: $user->id,
                    eventKey: 'phone_verified',
                    points: 10,
                    referenceType: 'user',
                    description: 'Phone number verified during registration.'
                );

            } elseif (!$user->phone_verified_at) {

                $user->forceFill([
                    'phone_verified_at' => now(),
                ])->save();
            }

            return $user;
        });

        Cache::forget($cacheKey);

        app(PushNotificationService::class)->sendToUser(
            $user,
            'Registration successful',
            ($challenge['intent'] ?? null) === 'register'
                ? 'Your account was created successfully.'
                : 'You are now logged in successfully.',
            [
                'type' => 'account_registered',
                'user_id' => (string) $user->id,
                'flow' => $challenge['intent'] ?? 'login',
            ],
        );

        $token = $user
            ->createToken(
                $validated['device_name'] ?? 'safee-meet-app'
            )
            ->plainTextToken;

        $firebaseData = $this->syncFirebaseUser(
            $user,
            $validated['phone'],
            $challenge['name'] ?? null
        );

        return response()->json([
            'message' => ($challenge['intent'] ?? null) === 'register'
                ? 'Registration successful.'
                : 'Login successful.',

            'data' => [
                'token_type' => 'Bearer',
                'access_token' => $token,
                'refresh_token' => null,
                'firebase_custom_token' => $firebaseData['custom_token'],
                'firebase_uid' => $firebaseData['firebase_uid'],
                'is_new_user' => ($challenge['intent'] ?? null) === 'register',
                'user' => $user,
            ],
        ], ($challenge['intent'] ?? null) === 'register' ? 201 : 200);
    }

    /**
     * Delete a user account by phone number, no bearer token required.
     * Removes dependent records in a safe order before soft-deleting the user.
     */
    // public function deleteAccountByPhone(Request $request): JsonResponse
    // {
    //     $validated = $request->validate([
    //         'phone' => ['required', 'regex:/^\+?[1-9]\d{7,14}$/'],
    //     ]);

    //     $phone = $this->normalizePhone((string) $validated['phone']);
    //     $user = User::where('phone', $phone)->first();

    //     if (! $user) {
    //         return response()->json([
    //             'message' => 'No account exists for this mobile number.',
    //         ], 404);
    //     }

    //     DB::transaction(function () use ($user): void {
    //         $user->tokens()->delete();
    //         $user->currentAccessToken()?->delete();

    //         $user->notifications()->delete();
    //         $user->notificationPreferences()->delete();

    //         if (Schema::hasTable('user_devices')) {
    //             $user->devices()->delete();
    //         }

    //         if (Schema::hasTable('auth_sessions')) {
    //             $user->authSessions()->delete();
    //         }

    //         if (Schema::hasTable('login_events')) {
    //             $user->loginEvents()->delete();
    //         }

    //         if (Schema::hasTable('user_roles')) {
    //             $user->roles()->delete();
    //         }

    //         if (Schema::hasTable('identity_verifications')) {
    //             $user->identityVerifications()->delete();
    //         }

    //         if (Schema::hasTable('user_verifications')) {
    //             $user->userVerification()->delete();
    //         }

    //         if (Schema::hasTable('trust_score_snapshots')) {
    //             $user->trustScoreSnapshots()->delete();
    //         }

    //         if (Schema::hasTable('user_badges')) {
    //             $user->badges()->delete();
    //         }

    //         if (Schema::hasTable('risk_flags')) {
    //             $user->riskFlags()->delete();
    //         }

    //         if (Schema::hasTable('safe_pins')) {
    //             $user->safePin()->delete();
    //         }

    //         if (Schema::hasTable('chat_user_mappings')) {
    //             $user->chatMapping()->delete();
    //         }

    //         if (Schema::hasTable('emergency_contacts')) {
    //             $user->emergencyContacts()->delete();
    //         }

    //         if (Schema::hasTable('subscriptions')) {
    //             $user->subscriptions()->delete();
    //         }

    //         if (Schema::hasTable('payments')) {
    //             $user->payments()->delete();
    //         }

    //         if (Schema::hasTable('verification_requests')) {
    //             $user->verificationRequests()->delete();
    //         }

    //         if (Schema::hasTable('search_history')) {
    //             $user->searchHistory()->delete();
    //         }

    //         \App\Models\Meeting::where('host_user_id', $user->id)
    //             ->orWhere('guest_user_id', $user->id)
    //             ->delete();

    //         if (Schema::hasTable('meeting_locations')) {
    //             \App\Models\MeetingLocation::where('user_id', $user->id)->delete();
    //         }

    //         if (Schema::hasTable('incidents')) {
    //             \App\Models\Incident::where('reporter_user_id', $user->id)->delete();
    //         }

    //         if (Schema::hasTable('sos_incidents')) {
    //             \App\Models\SosIncident::where('triggered_by_user_id', $user->id)->delete();
    //         }

    //         if (Schema::hasTable('search_history')) {
    //             \App\Models\SearchHistory::where('searcher_id', $user->id)
    //                 ->orWhere('found_user_id', $user->id)
    //                 ->delete();
    //         }

    //         $user->delete();
    //     });

    //     return response()->json([
    //         'message' => 'Account deleted successfully.',
    //     ]);
    // }

    // public function deleteAccountByPhone(Request $request): JsonResponse
    // {

    // \Log::info('DELETE ACCOUNT API HIT', [
    //         'phone' => $request->phone,
    //         'method' => $request->method(),
    //         'url' => $request->fullUrl(),
    //     ]);
    //     $validated = $request->validate([
    //         'phone' => ['required', 'regex:/^\+?[1-9]\d{7,14}$/'],
    //     ]);

    //     $phone = $this->normalizePhone((string) $validated['phone']);

    //     Log::info('Account deletion requested', [
    //         'phone' => $phone,
    //     ]);

    //     $user = User::where('phone', $phone)->first();

    //     if (! $user) {
    //         Log::warning('Account deletion failed: user not found', [
    //             'phone' => $phone,
    //         ]);

    //         return response()->json([
    //             'message' => 'No account exists for this mobile number.',
    //         ], 404);
    //     }

    //     Log::info('User found for account deletion', [
    //         'user_id' => $user->id,
    //     ]);

    //     try {

    //         DB::transaction(function () use ($user): void {

    //             Log::info('Account deletion transaction started', [
    //                 'user_id' => $user->id,
    //             ]);

    //             // Tokens
    //             $count = $user->tokens()->delete();

    //             Log::info('Personal access tokens deleted', [
    //                 'user_id' => $user->id,
    //                 'deleted_count' => $count,
    //             ]);


    //             // Notifications
    //             $count = $user->notifications()->delete();

    //             Log::info('Notifications deleted', [
    //                 'user_id' => $user->id,
    //                 'deleted_count' => $count,
    //             ]);


    //             // Notification Preferences
    //             $count = $user->notificationPreferences()->delete();

    //             Log::info('Notification preferences deleted', [
    //                 'user_id' => $user->id,
    //                 'deleted_count' => $count,
    //             ]);


    //             // User Devices
    //             if (Schema::hasTable('user_devices')) {

    //                 $count = $user->devices()->delete();

    //                 Log::info('User devices deleted', [
    //                     'user_id' => $user->id,
    //                     'deleted_count' => $count,
    //                 ]);
    //             }


    //             // Auth Sessions
    //             if (Schema::hasTable('auth_sessions')) {

    //                 $count = $user->authSessions()->delete();

    //                 Log::info('Auth sessions deleted', [
    //                     'user_id' => $user->id,
    //                     'deleted_count' => $count,
    //                 ]);
    //             }


    //             // Login Events
    //             if (Schema::hasTable('login_events')) {

    //                 $count = $user->loginEvents()->delete();

    //                 Log::info('Login events deleted', [
    //                     'user_id' => $user->id,
    //                     'deleted_count' => $count,
    //                 ]);
    //             }


    //             // User Roles
    //             if (Schema::hasTable('user_roles')) {

    //                 $count = $user->roles()->delete();

    //                 Log::info('User roles deleted', [
    //                     'user_id' => $user->id,
    //                     'deleted_count' => $count,
    //                 ]);
    //             }


    //             // Identity Verifications
    //             if (Schema::hasTable('identity_verifications')) {

    //                 $count = $user->identityVerifications()->delete();

    //                 Log::info('Identity verifications deleted', [
    //                     'user_id' => $user->id,
    //                     'deleted_count' => $count,
    //                 ]);
    //             }


    //             // User Verification
    //             if (Schema::hasTable('user_verifications')) {

    //                 $count = $user->userVerification()->delete();

    //                 Log::info('User verification deleted', [
    //                     'user_id' => $user->id,
    //                     'deleted_count' => $count,
    //                 ]);
    //             }


    //             // Trust Score Snapshots
    //             if (Schema::hasTable('trust_score_snapshots')) {

    //                 $count = $user->trustScoreSnapshots()->delete();

    //                 Log::info('Trust score snapshots deleted', [
    //                     'user_id' => $user->id,
    //                     'deleted_count' => $count,
    //                 ]);
    //             }


    //             // User Badges
    //             if (Schema::hasTable('user_badges')) {

    //                 $count = $user->badges()->delete();

    //                 Log::info('User badges deleted', [
    //                     'user_id' => $user->id,
    //                     'deleted_count' => $count,
    //                 ]);
    //             }


    //             // Risk Flags
    //             if (Schema::hasTable('risk_flags')) {

    //                 $count = $user->riskFlags()->delete();

    //                 Log::info('Risk flags deleted', [
    //                     'user_id' => $user->id,
    //                     'deleted_count' => $count,
    //                 ]);
    //             }


    //             // Safe PIN
    //             if (Schema::hasTable('safe_pins')) {

    //                 $count = $user->safePin()->delete();

    //                 Log::info('Safe PIN deleted', [
    //                     'user_id' => $user->id,
    //                     'deleted_count' => $count,
    //                 ]);
    //             }


    //             // Chat Mapping
    //             if (Schema::hasTable('chat_user_mappings')) {

    //                 $count = $user->chatMapping()->delete();

    //                 Log::info('Chat user mapping deleted', [
    //                     'user_id' => $user->id,
    //                     'deleted_count' => $count,
    //                 ]);
    //             }


    //             // Emergency Contacts
    //             if (Schema::hasTable('emergency_contacts')) {

    //                 $count = $user->emergencyContacts()->delete();

    //                 Log::info('Emergency contacts deleted', [
    //                     'user_id' => $user->id,
    //                     'deleted_count' => $count,
    //                 ]);
    //             }


    //             // Subscriptions
    //             if (Schema::hasTable('subscriptions')) {

    //                 $count = $user->subscriptions()->delete();

    //                 Log::info('Subscriptions deleted', [
    //                     'user_id' => $user->id,
    //                     'deleted_count' => $count,
    //                 ]);
    //             }


    //             // Payments
    //             if (Schema::hasTable('payments')) {

    //                 $count = $user->payments()->delete();

    //                 Log::info('Payments deleted', [
    //                     'user_id' => $user->id,
    //                     'deleted_count' => $count,
    //                 ]);
    //             }


    //             // Verification Requests
    //             if (Schema::hasTable('verification_requests')) {

    //                 $count = $user->verificationRequests()->delete();

    //                 Log::info('Verification requests deleted', [
    //                     'user_id' => $user->id,
    //                     'deleted_count' => $count,
    //                 ]);
    //             }


    //             // Search History Relation
    //             if (Schema::hasTable('search_history')) {

    //                 $count = $user->searchHistory()->delete();

    //                 Log::info('User search history relation deleted', [
    //                     'user_id' => $user->id,
    //                     'deleted_count' => $count,
    //                 ]);
    //             }


    //             // Meetings
    //             $count = \App\Models\Meeting::where('host_user_id', $user->id)
    //                 ->orWhere('guest_user_id', $user->id)
    //                 ->delete();

    //             Log::info('Meetings deleted', [
    //                 'user_id' => $user->id,
    //                 'deleted_count' => $count,
    //             ]);


    //             // Meeting Locations
    //             if (Schema::hasTable('meeting_locations')) {

    //                 $count = \App\Models\MeetingLocation::where(
    //                     'user_id',
    //                     $user->id
    //                 )->delete();

    //                 Log::info('Meeting locations deleted', [
    //                     'user_id' => $user->id,
    //                     'deleted_count' => $count,
    //                 ]);
    //             }


    //             // Incidents
    //             if (Schema::hasTable('incidents')) {

    //                 $count = \App\Models\Incident::where(
    //                     'reporter_user_id',
    //                     $user->id
    //                 )->delete();

    //                 Log::info('Incidents deleted', [
    //                     'user_id' => $user->id,
    //                     'deleted_count' => $count,
    //                 ]);
    //             }


    //             // SOS Incidents
    //             if (Schema::hasTable('sos_incidents')) {

    //                 $count = \App\Models\SosIncident::where(
    //                     'triggered_by_user_id',
    //                     $user->id
    //                 )->delete();

    //                 Log::info('SOS incidents deleted', [
    //                     'user_id' => $user->id,
    //                     'deleted_count' => $count,
    //                 ]);
    //             }


    //             // Search History where user is searcher OR found user
    //             if (Schema::hasTable('search_history')) {

    //                 $count = \App\Models\SearchHistory::where(
    //                     'searcher_id',
    //                     $user->id
    //                 )
    //                     ->orWhere('found_user_id', $user->id)
    //                     ->delete();

    //                 Log::info('Search history references deleted', [
    //                     'user_id' => $user->id,
    //                     'deleted_count' => $count,
    //                 ]);
    //             }


    //             Log::info('Deleting main user record', [
    //                 'user_id' => $user->id,
    //             ]);

    //             $user->delete();

    //             Log::info('Main user record deleted', [
    //                 'user_id' => $user->id,
    //             ]);
    //         });


    //         Log::info('Account deletion completed successfully', [
    //             'user_id' => $user->id,
    //         ]);

    //         return response()->json([
    //             'message' => 'Account deleted successfully.',
    //         ]);

    //     } catch (\Throwable $e) {

    //         Log::error('Account deletion failed and transaction rolled back', [
    //             'user_id' => $user->id,
    //             'error' => $e->getMessage(),
    //             'file' => $e->getFile(),
    //             'line' => $e->getLine(),
    //         ]);

    //         throw $e;
    //     }
    // }
    public function deleteAccountByPhone(Request $request): JsonResponse
{

    Log::info('DELETE ACCOUNT API HIT', [
            'phone' => $request->phone,
            'method' => $request->method(),
            'url' => $request->fullUrl(),
        ]);
    /*
    |--------------------------------------------------------------------------
    | Get authenticated user from Bearer token
    |--------------------------------------------------------------------------
    */

    $user = $request->user();

    if (! $user) {
        Log::warning('Account deletion failed: unauthenticated request');

        return response()->json([
            'message' => 'Unauthenticated.',
        ], 401);
    }

    Log::info('Account deletion requested', [
        'user_id' => $user->id,
        'phone' => $user->phone,
    ]);

    try {
        DB::transaction(function () use ($user): void {

            Log::info('Account deletion transaction started', [
                'user_id' => $user->id,
            ]);

            /*
            |--------------------------------------------------------------------------
            | Personal Access Tokens
            |--------------------------------------------------------------------------
            */

            $count = $user->tokens()->delete();

            Log::info('Personal access tokens deleted', [
                'user_id' => $user->id,
                'deleted_count' => $count,
            ]);

            /*
            |--------------------------------------------------------------------------
            | Notifications
            |--------------------------------------------------------------------------
            */

            $count = $user->notifications()->delete();

            Log::info('Notifications deleted', [
                'user_id' => $user->id,
                'deleted_count' => $count,
            ]);

            /*
            |--------------------------------------------------------------------------
            | Notification Preferences
            |--------------------------------------------------------------------------
            */

            $count = $user->notificationPreferences()->delete();

            Log::info('Notification preferences deleted', [
                'user_id' => $user->id,
                'deleted_count' => $count,
            ]);

            /*
            |--------------------------------------------------------------------------
            | User Devices
            |--------------------------------------------------------------------------
            */

            if (Schema::hasTable('user_devices')) {
                $count = $user->devices()->delete();

                Log::info('User devices deleted', [
                    'user_id' => $user->id,
                    'deleted_count' => $count,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Auth Sessions
            |--------------------------------------------------------------------------
            */

            if (Schema::hasTable('auth_sessions')) {
                $count = $user->authSessions()->delete();

                Log::info('Auth sessions deleted', [
                    'user_id' => $user->id,
                    'deleted_count' => $count,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Login Events
            |--------------------------------------------------------------------------
            */

            if (Schema::hasTable('login_events')) {
                $count = $user->loginEvents()->delete();

                Log::info('Login events deleted', [
                    'user_id' => $user->id,
                    'deleted_count' => $count,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | User Roles
            |--------------------------------------------------------------------------
            |
            | Important:
            | Don't use $user->roles()->delete()
            | if roles() is belongsToMany(), otherwise actual role records
            | can get deleted.
            |--------------------------------------------------------------------------
            */

            if (Schema::hasTable('user_roles')) {
                $count = DB::table('user_roles')
                    ->where('user_id', $user->id)
                    ->delete();

                Log::info('User role mappings deleted', [
                    'user_id' => $user->id,
                    'deleted_count' => $count,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Identity Verifications
            |--------------------------------------------------------------------------
            */

            if (Schema::hasTable('identity_verifications')) {
                $count = $user->identityVerifications()->delete();

                Log::info('Identity verifications deleted', [
                    'user_id' => $user->id,
                    'deleted_count' => $count,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | User Verification
            |--------------------------------------------------------------------------
            */

            if (Schema::hasTable('user_verifications')) {
                $count = $user->userVerification()->delete();

                Log::info('User verification deleted', [
                    'user_id' => $user->id,
                    'deleted_count' => $count,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Trust Score Snapshots
            |--------------------------------------------------------------------------
            */

            if (Schema::hasTable('trust_score_snapshots')) {
                $count = $user->trustScoreSnapshots()->delete();

                Log::info('Trust score snapshots deleted', [
                    'user_id' => $user->id,
                    'deleted_count' => $count,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | User Badges
            |--------------------------------------------------------------------------
            |
            | user_badges looks like a mapping/pivot table,
            | so remove mappings instead of deleting badge master records.
            |--------------------------------------------------------------------------
            */

            if (Schema::hasTable('user_badges')) {
                $count = DB::table('user_badges')
                    ->where('user_id', $user->id)
                    ->delete();

                Log::info('User badge mappings deleted', [
                    'user_id' => $user->id,
                    'deleted_count' => $count,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Risk Flags
            |--------------------------------------------------------------------------
            */

            if (Schema::hasTable('risk_flags')) {
                $count = $user->riskFlags()->delete();

                Log::info('Risk flags deleted', [
                    'user_id' => $user->id,
                    'deleted_count' => $count,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Safe PIN
            |--------------------------------------------------------------------------
            */

            if (Schema::hasTable('safe_pins')) {
                $count = $user->safePin()->delete();

                Log::info('Safe PIN deleted', [
                    'user_id' => $user->id,
                    'deleted_count' => $count,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Chat Mapping
            |--------------------------------------------------------------------------
            */

            if (Schema::hasTable('chat_user_mappings')) {
                $count = $user->chatMapping()->delete();

                Log::info('Chat user mapping deleted', [
                    'user_id' => $user->id,
                    'deleted_count' => $count,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Emergency Contacts
            |--------------------------------------------------------------------------
            */

            if (Schema::hasTable('emergency_contacts')) {
                $count = $user->emergencyContacts()->delete();

                Log::info('Emergency contacts deleted', [
                    'user_id' => $user->id,
                    'deleted_count' => $count,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Subscriptions
            |--------------------------------------------------------------------------
            */

            if (Schema::hasTable('subscriptions')) {
                $count = $user->subscriptions()->delete();

                Log::info('Subscriptions deleted', [
                    'user_id' => $user->id,
                    'deleted_count' => $count,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Payments
            |--------------------------------------------------------------------------
            */

            if (Schema::hasTable('payments')) {
                $count = $user->payments()->delete();

                Log::info('Payments deleted', [
                    'user_id' => $user->id,
                    'deleted_count' => $count,
                ]);
            }

            if (Schema::hasTable('verification_requests') && Schema::hasColumn('verification_requests', 'deleted_at')) {
                // No Eloquent model backs this table, so soft-delete manually
                // to stay consistent with the user's own soft-delete.
                $count = DB::table('verification_requests')
                    ->where('user_id', $user->id)
                    ->whereNull('deleted_at')
                    ->update(['deleted_at' => now()]);

                Log::info('Verification requests soft-deleted', [
                    'user_id' => $user->id,
                    'deleted_count' => $count,
                ]);
            }

            if (Schema::hasTable('search_history')) {
                $count = \App\Models\SearchHistory::where(
                    'searcher_id',
                    $user->id
                )
                    ->orWhere('found_user_id', $user->id)
                    ->delete();

                Log::info('Search history deleted', [
                    'user_id' => $user->id,
                    'deleted_count' => $count,
                ]);
            }

            if (Schema::hasTable('meetings')) {
                $count = \App\Models\Meeting::where(
                    'host_user_id',
                    $user->id
                )
                    ->orWhere('guest_user_id', $user->id)
                    ->delete();

                Log::info('Meetings deleted', [
                    'user_id' => $user->id,
                    'deleted_count' => $count,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Meeting Locations
            |--------------------------------------------------------------------------
            */

            if (Schema::hasTable('meeting_locations')) {
                $count = \App\Models\MeetingLocation::where(
                    'user_id',
                    $user->id
                )->delete();

                Log::info('Meeting locations deleted', [
                    'user_id' => $user->id,
                    'deleted_count' => $count,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Incidents
            |--------------------------------------------------------------------------
            */

            if (Schema::hasTable('incidents')) {
                $count = \App\Models\Incident::where(
                    'reporter_user_id',
                    $user->id
                )->delete();

                Log::info('Incidents deleted', [
                    'user_id' => $user->id,
                    'deleted_count' => $count,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | SOS Incidents
            |--------------------------------------------------------------------------
            */

            if (Schema::hasTable('sos_incidents')) {
                $count = \App\Models\SosIncident::where(
                    'triggered_by_user_id',
                    $user->id
                )->delete();

                Log::info('SOS incidents deleted', [
                    'user_id' => $user->id,
                    'deleted_count' => $count,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Finally Delete User
            |--------------------------------------------------------------------------
            */

            Log::info('Deleting main user record', [
                'user_id' => $user->id,
                'phone' => $user->phone,
            ]);

            $userId = $user->id;
            $phone = $user->phone;

            $user->delete();

            Log::info('Main user record deleted', [
                'user_id' => $userId,
                'phone' => $phone,
            ]);
        });

        Log::info('Account deletion completed successfully', [
            'user_id' => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Account deleted successfully.',
        ], 200);

    } catch (\Throwable $e) {

        Log::error('Account deletion failed and transaction rolled back', [
            'user_id' => $user->id,
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Unable to delete account.',
        ], 500);
    }
}


    public function me(Request $request): JsonResponse
    {
        
        return response()->json(['data' => ['user' => $request->user()]]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    private function validatePhoneRequest(Request $request, bool $requireName = false): array
    {
        $request->merge(['phone' => $this->normalizePhone((string) $request->input('phone'))]);

        return $request->validate([
            'phone' => ['required', 'regex:/^\+?[1-9]\d{7,14}$/'],
            'name' => [$requireName ? 'required' : 'nullable', 'string', 'max:255'],
        ]);
    }

    private function issueOtp(string $phone, string $intent, ?string $name = null): JsonResponse
    {
        $phoneNumber = preg_replace('/\D+/', '', $phone);

        if ($phoneNumber === '17322075598') {
            $otp = '123456';
        } else {
            $otp = (string) random_int(100000, 999999);
        }

        Cache::put($this->otpCacheKey($phone), [
            'otp_hash' => Hash::make($otp),
            'intent' => $intent,
            'name' => $name,
            'attempts' => 0,
        ], now()->addMinutes(self::OTP_TTL_MINUTES));

        // Send OTP via Telesign SMS service
        $smsSent = app(TelesignSmsService::class)->sendOtp($phone, $otp);

        $data = [
            'phone' => $phone,
            'flow' => $intent,
            'expires_in' => self::OTP_TTL_MINUTES * 600,
        ];

        // For development: expose OTP if configured
        if (config('app.expose_dev_otp')) {
            $data['dev_otp'] = $otp;
        }

        // If SMS failed and we're not in dev mode, return error
        if (!$smsSent && !config('app.expose_dev_otp')) {
            return response()->json([
                'message' => 'Failed to send OTP. Please try again.',
                'error' => 'SMS delivery failed',
            ], 500);
        }

        return response()->json([
            'message' => 'OTP sent successfully.',
            'data' => $data,
        ]);
    }

    private function syncFirebaseUser(User $user, string $phone, ?string $name = null): array
    {
        $firebaseAuth = app(FirebaseAuth::class);
        $firebaseUid = $user->firebase_uid;
        $firebaseUserFound = false;

        try {
            if ($firebaseUid) {
                $firebaseUser = $firebaseAuth->getUser($firebaseUid);
                $firebaseUid = $firebaseUser->uid;
                $firebaseUserFound = true;
            } else {
                $firebaseUser = $firebaseAuth->getUserByPhoneNumber($phone);
                $firebaseUid = $firebaseUser->uid;
                $firebaseUserFound = true;
            }
        } catch (UserNotFound) {
            $userData = [
                'phoneNumber' => $phone,
                'displayName' => $name ?? $user->name ?? 'SAFEE User',
            ];
            
            // Only include email if it's not null
            if ($user->email) {
                $userData['email'] = $user->email;
            }
            
            $firebaseUser = $firebaseAuth->createUser($userData);
            $firebaseUid = $firebaseUser->uid;
            $firebaseUserFound = false;
        } catch (\Throwable $e) {
            Log::error('Firebase sync failed during phone OTP verify.', [
                'user_id' => $user->id,
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        Log::info('Firebase sync status during phone OTP verify.', [
            'user_id' => $user->id,
            'phone' => $phone,
            'firebase_user_found' => $firebaseUserFound,
            'firebase_uid' => $firebaseUid,
        ]);

        $user->forceFill(['firebase_uid' => $firebaseUid])->save();

        return [
            'firebase_uid' => $firebaseUid,
            'custom_token' => $firebaseAuth->createCustomToken($firebaseUid)->toString(),
        ];
    }

    private function normalizePhone(string $phone): string
    {
        $phone = trim($phone);
        $prefix = str_starts_with($phone, '+') ? '+' : '';

        return $prefix.preg_replace('/\D+/', '', $phone);
    }

    private function otpCacheKey(string $phone): string
    {
        return 'auth:phone-otp:'.hash('sha256', $phone);
    }
}
