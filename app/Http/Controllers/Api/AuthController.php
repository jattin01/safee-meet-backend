<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\JsonResponse;


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
                    'id' => (string) Str::ulid(),
                    // This deployment's users table has no plain "name"
                    // column — display_name is the real one.
                    'display_name' => $challenge['name'],
                    'phone' => $validated['phone'],
                    'phone_verified_at' => now(),
                    'safee_pin' => User::generateSafeePin(),
                    'subscription_status' => 'trial',
                    'status' => 'active',
                    'auth_provider' => 'phone',
                ]);
            } elseif (! $user->phone_verified_at) {
                $user->forceFill(['phone_verified_at' => now()])->save();
            }

            return $user;
        });

        Cache::forget($cacheKey);

        $token = $user->createToken($validated['device_name'] ?? 'safee-meet-app')->plainTextToken;

        return response()->json([
            'message' => ($challenge['intent'] ?? null) === 'register'
                ? 'Registration successful.'
                : 'Login successful.',
            'data' => [
                'token_type' => 'Bearer',
                'access_token' => $token,
                'is_new_user' => ($challenge['intent'] ?? null) === 'register',
                'user' => $user,
            ],
        ], ($challenge['intent'] ?? null) === 'register' ? 201 : 200);
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

        // Integrate the SMS provider here. Never return the OTP in production.
        $data = [
            'phone' => $phone,
            'flow' => $intent,
            'expires_in' => self::OTP_TTL_MINUTES * 60,
        ];

        if (app()->environment(['local', 'testing'])) {
            $data['dev_otp'] = $otp;
        }

        return response()->json([
            'message' => 'OTP sent successfully.',
            'data' => $data,
        ]);
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
