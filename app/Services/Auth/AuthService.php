<?php

namespace App\Services\Auth;

use App\Contracts\Auth\AuthVerificationProvider;
use App\Exceptions\Auth\AuthException;
use App\Models\LoginEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Core auth business logic.
 * Depends ONLY on AuthVerificationProvider interface — no Firebase imports here.
 */
class AuthService
{
    public function __construct(
        private readonly AuthVerificationProvider $verificationProvider,
    ) {}

    // ── Registration ─────────────────────────────────────────────────────────

    public function register(array $payload): array
    {
        $provider      = $payload['provider'];
        $providerToken = $payload['providerToken'];

        // 1. Verify identity via current provider (Firebase today, anything tomorrow)
        $identity = $this->verificationProvider->validateToken($provider, $providerToken);

        // 2. Check duplicate by email / phone / providerUid
        $this->assertUserDoesNotExist($identity->email, $identity->phone, $identity->providerUid);

        // 3. Create user inside a transaction
        $user = DB::transaction(function () use ($payload, $identity) {
            $user = User::create([
                // 'id' is set by User::booted()'s creating hook, not here —
                // it isn't mass-assignable so passing it in this array is a no-op.
                'firebase_uid'    => $identity->providerUid,   // provider-UID stored here
                'safee_id'        => $this->generateSafeeId(),
                'account_type'    => $payload['accountType'] ?? 'normal',
                'auth_provider'   => $identity->provider,
                'display_name'    => $payload['name'] ?? $identity->name ?? 'SAFEE User',
                'avatar_url'      => $identity->avatarUrl,
                // Plain columns going forward — email_encrypted/email_hash/
                // phone_encrypted/phone_hash are left in place (unused) for
                // existing rows; no encryption-at-rest at this stage.
                'email'           => ($payload['email'] ?? $identity->email) ? strtolower(trim($payload['email'] ?? $identity->email)) : null,
                'phone'           => $identity->phone,
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
            ]);

            // Create default notification preferences
            $user->notificationPreferences()->create([
                'id'         => (string) Str::ulid(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $user;
        });

        $this->auditLog($user->id, 'registration', 'success');

        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'user'         => $user,
            'accessToken'  => $token,
            'refreshToken' => null,
        ];
    }

    // ── Login ─────────────────────────────────────────────────────────────────

    public function login(array $payload): array
    {
        $provider      = $payload['provider'];
        $providerToken = $payload['providerToken'];
        $loginType     = $payload['loginType'] ?? 'social';

        // 1. Verify the provider token
        $identity = $this->verificationProvider->validateToken($provider, $providerToken);

        // 2. Find existing user — login NEVER creates users
        $user = $this->findUser($identity->email, $identity->phone, $identity->providerUid);

        if (!$user) {
            $this->auditLog(null, 'login_failed', 'USER_NOT_REGISTERED', $identity->email ?? $identity->phone);
            throw AuthException::userNotRegistered();
        }

        // 3. Account status checks
        $this->assertAccountIsActive($user);

        // 4. Update last login
        $user->update(['last_login_at' => now(), 'last_seen_at' => now()]);

        $this->auditLog($user->id, 'login', 'success');

        // 5. Issue Sanctum token
        $user->tokens()->delete(); // single active token per user
        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'user'         => $user,
            'accessToken'  => $token,
            'refreshToken' => null,
        ];
    }

    // ── Logout ────────────────────────────────────────────────────────────────

    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
        $this->auditLog($user->id, 'logout', 'success');
    }

    // ── Check User Exists ─────────────────────────────────────────────────────

    public function checkUserExists(?string $email, ?string $phone, ?string $providerUid = null): bool
    {
        return (bool) $this->findUser($email, $phone, $providerUid);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function findUser(?string $email, ?string $phone, ?string $providerUid = null): ?User
    {
        if ($providerUid) {
            $user = User::where('firebase_uid', $providerUid)->first();
            if ($user) return $user;
        }

        if ($email) {
            $user = User::where('email', strtolower(trim($email)))->first();
            if ($user) return $user;
        }

        if ($phone) {
            $user = User::where('phone', $phone)->first();
            if ($user) return $user;
        }

        return null;
    }

    private function assertUserDoesNotExist(?string $email, ?string $phone, ?string $providerUid): void
    {
        if ($this->findUser($email, $phone, $providerUid)) {
            throw AuthException::userAlreadyExists();
        }
    }

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

    private function generateSafeeId(): string
    {
        // safee_id is an alias of the safee_pin column (see User::safeeId());
        // uniqueness must be checked against safee_pin at the query level.
        do {
            $id = 'SM' . strtoupper(Str::random(8));
        } while (User::where('safee_pin', $id)->exists());

        return $id;
    }

    private function auditLog(
        ?string $userId,
        string $action,
        string $status,
        ?string $identifier = null
    ): void {
        try {
            LoginEvent::create([
                'id'             => (string) Str::ulid(),
                'user_id'        => $userId,
                'firebase_uid'   => null,
                'provider'       => 'backend',
                'ip_address'     => request()->ip(),
                'user_agent'     => request()->userAgent(),
                'status'         => $status === 'success' ? 'success' : 'failed',
                'failure_reason' => $status !== 'success' ? $status : null,
                'occurred_at'    => now(),
                'created_at'     => now(),
            ]);
        } catch (\Throwable) {
            // Never let audit logging break the main flow
        }
    }
}
