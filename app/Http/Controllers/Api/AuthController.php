<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
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
    public function verifyOtp(Request $request): JsonResponse
    {
       
        $request->merge(['phone' => $this->normalizePhone((string) $request->input('phone'))]);

        $validated = $request->validate([
            'phone' => ['required', 'regex:/^\+?[1-9]\d{7,14}$/'],
            'otp' => ['required', 'digits:6'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $cacheKey = $this->otpCacheKey($validated['phone']);
        $challenge = Cache::get($cacheKey);
       

        if (! is_array($challenge)) {
            return response()->json([
                'message' => 'The OTP is invalid or has expired. Please request a new OTP.',
            ], 422);
        }

        if (($challenge['attempts'] ?? 0) >= self::OTP_MAX_ATTEMPTS) {
            Cache::forget($cacheKey);

            return response()->json([
                'message' => 'Too many incorrect attempts. Please request a new OTP.',
            ], 429);
        }

        if (! Hash::check($validated['otp'], $challenge['otp_hash'])) {
            $challenge['attempts'] = ($challenge['attempts'] ?? 0) + 1;
            Cache::put($cacheKey, $challenge, now()->addMinutes(self::OTP_TTL_MINUTES));

            return response()->json([
                'message' => 'The OTP is incorrect.',
                'attempts_remaining' => self::OTP_MAX_ATTEMPTS - $challenge['attempts'],
            ], 422);
        }

        $user = DB::transaction(function () use ($validated, $challenge): User {
            $user = User::where('phone', $validated['phone'])->lockForUpdate()->first();

            if (($challenge['intent'] ?? null) === 'login' && ! $user) {
                throw ValidationException::withMessages([
                    'phone' => ['The account no longer exists. Please register again.'],
                ]);
            }

            if (! $user) {
                $user = User::create([
                    'name' => $challenge['name'],
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
            } elseif (! $user->phone_verified_at) {
                $user->forceFill(['phone_verified_at' => now()])->save();
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

        $token = $user->createToken($validated['device_name'] ?? 'safee-meet-app')->plainTextToken;
        $firebaseData = $this->syncFirebaseUser($user, $validated['phone'], $challenge['name'] ?? null);

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
    public function deleteAccountByPhone(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'regex:/^\+?[1-9]\d{7,14}$/'],
        ]);

        $phone = $this->normalizePhone((string) $validated['phone']);
        $user = User::where('phone', $phone)->first();

        if (! $user) {
            return response()->json([
                'message' => 'No account exists for this mobile number.',
            ], 404);
        }

        DB::transaction(function () use ($user): void {
            $user->tokens()->delete();
            $user->currentAccessToken()?->delete();

            $user->notifications()->delete();
            $user->notificationPreferences()->delete();

            if (Schema::hasTable('user_devices')) {
                $user->devices()->delete();
            }

            if (Schema::hasTable('auth_sessions')) {
                $user->authSessions()->delete();
            }

            if (Schema::hasTable('login_events')) {
                $user->loginEvents()->delete();
            }

            if (Schema::hasTable('user_roles')) {
                $user->roles()->delete();
            }

            if (Schema::hasTable('identity_verifications')) {
                $user->identityVerifications()->delete();
            }

            if (Schema::hasTable('user_verifications')) {
                $user->userVerification()->delete();
            }

            if (Schema::hasTable('trust_score_snapshots')) {
                $user->trustScoreSnapshots()->delete();
            }

            if (Schema::hasTable('user_badges')) {
                $user->badges()->delete();
            }

            if (Schema::hasTable('risk_flags')) {
                $user->riskFlags()->delete();
            }

            if (Schema::hasTable('safe_pins')) {
                $user->safePin()->delete();
            }

            if (Schema::hasTable('chat_user_mappings')) {
                $user->chatMapping()->delete();
            }

            if (Schema::hasTable('emergency_contacts')) {
                $user->emergencyContacts()->delete();
            }

            if (Schema::hasTable('subscriptions')) {
                $user->subscriptions()->delete();
            }

            if (Schema::hasTable('payments')) {
                $user->payments()->delete();
            }

            if (Schema::hasTable('verification_requests')) {
                $user->verificationRequests()->delete();
            }

            if (Schema::hasTable('search_history')) {
                $user->searchHistory()->delete();
            }

            \App\Models\Meeting::where('host_user_id', $user->id)
                ->orWhere('guest_user_id', $user->id)
                ->delete();

            if (Schema::hasTable('meeting_locations')) {
                \App\Models\MeetingLocation::where('user_id', $user->id)->delete();
            }

            if (Schema::hasTable('incidents')) {
                \App\Models\Incident::where('reporter_user_id', $user->id)->delete();
            }

            if (Schema::hasTable('sos_incidents')) {
                \App\Models\SosIncident::where('triggered_by_user_id', $user->id)->delete();
            }

            if (Schema::hasTable('search_history')) {
                \App\Models\SearchHistory::where('searcher_id', $user->id)
                    ->orWhere('found_user_id', $user->id)
                    ->delete();
            }

            $user->delete();
        });

        return response()->json([
            'message' => 'Account deleted successfully.',
        ]);
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
        $otp = (string) random_int(100000, 999999);

        Cache::put($this->otpCacheKey($phone), [
            'otp_hash' => Hash::make($otp),
            'intent' => $intent,
            'name' => $name,
            'attempts' => 0,
        ], now()->addMinutes(self::OTP_TTL_MINUTES));

        // Send OTP via MSG91 SMS service
        $smsSent = false;
        $smsError = null;

        try {
            // Remove '+' prefix from phone number for MSG91
            $phoneNumber = ltrim($phone, '+');

            $response = Http::timeout(15)->get(
                'https://control.msg91.com/api/v5/otp',
                [
                    'otp'         => $otp,
                    'mobile'      => $phoneNumber,
                    'authkey'     => config('services.msg91.auth_key'),
                    'otp_length'  => config('services.msg91.otp_length', 6),
                    'template_id' => config('services.msg91.template_id'),
                ]
            );

            if ($response->successful()) {
                $smsSent = true;
                Log::info('OTP SMS sent successfully via MSG91', [
                    'phone' => $phone,
                    'intent' => $intent,
                    'response' => $response->json(),
                ]);
            } else {
                $smsError = $response->body();
                Log::error('Failed to send OTP SMS via MSG91', [
                    'phone' => $phone,
                    'status' => $response->status(),
                    'error' => $smsError,
                ]);
            }
        } catch (\Throwable $e) {
            $smsError = $e->getMessage();
            Log::error('Exception while sending OTP SMS via MSG91', [
                'phone' => $phone,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

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
