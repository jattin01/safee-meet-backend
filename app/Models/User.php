<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use App\Models\EmergencyContact;


class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    // users.id was historically a char(26) ULID (Firebase-based accounts) on
    // some deployments, and is being migrated to a bigint auto-increment.
    // Detect the live column type at runtime — same pattern as
    // Meeting::usesUlidKey()/EmergencyContact::usesUlidKey() — so this model
    // works correctly before, during, and after that migration.
    public function getIncrementing()
    {
        return !static::usesUlidKey();
    }

    public function getKeyType()
    {
        return static::usesUlidKey() ? 'string' : 'int';
    }

    private static function usesUlidKey(): bool
    {
        return in_array(Schema::getColumnType('users', 'id'), ['char', 'string'], true);
    }

    protected static function booted(): void
    {
        // 'id' isn't mass-assignable (see $fillable below), so a plain
        // User::create(['id' => ...]) silently drops it and MySQL rejects
        // the insert (char(26) PK has no default). Set it directly here,
        // bypassing mass assignment, same pattern as Meeting::booted().
        // Once the column is bigint auto-increment, usesUlidKey() is false
        // and this is skipped — MySQL assigns the id instead.
        static::creating(function (User $user): void {
            if (empty($user->id) && static::usesUlidKey()) {
                $user->id = (string) Str::ulid();
            }
        });
    }

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'phone_verified_at',
        'role',
        'verification_level',
        'badge',
        'subscription_plan',
        'subscription_status',
        'safee_pin',
        'dob',
        'address',
        'id_number',
        'trust_score',
        'rating',
        'account_type',
        'company_name',
        'employer_code',
        'job_title',
        'firebase_uid',
        'safee_id',
        'auth_provider',
        'display_name',
        'avatar_url',
        'email_encrypted',
        'email_hash',
        'phone_encrypted',
        'phone_hash',
        'status',
        'onboarding_status',
        'kyc_status',
        'trust_score',
        'trust_tier',
        'is_chat_enabled',
        'is_meeting_enabled',
        'is_sos_enabled',
        'email_verified_at',
        'phone_verified_at',
        'last_login_at',
        'last_seen_at',
        'suspended_reason',
        'deleted_reason',
        'fcm_token',
        'fcm_token_updated_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'otp_code',
        'email_encrypted',
        'phone_encrypted',
    ];

    protected function casts(): array
    {
        return [
            // Always expose id as a string in API responses, regardless of
            // the underlying column type (char ULID today, bigint after the
            // migration) — keeps the client contract stable either way.
            'id' => 'string',
            'is_chat_enabled' => 'boolean',
            'is_meeting_enabled' => 'boolean',
            'is_sos_enabled' => 'boolean',
            'trust_score' => 'integer',
            'dob' => 'date',
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    // ── Relationships ──────────────────────────────────────

    public function profile()
    {
        return $this->hasOne(UserProfile::class);
    }

    public function devices()
    {
        return $this->hasMany(UserDevice::class);
    }

    public function authSessions()
    {
        return $this->hasMany(AuthSession::class);
    }

    public function loginEvents()
    {
        return $this->hasMany(LoginEvent::class);
    }

    public function roles()
    {
        return $this->hasMany(UserRole::class);
    }

    public function identityVerifications()
    {
        return $this->hasMany(IdentityVerification::class);
    }

    public function trustScoreSnapshots()
    {
        return $this->hasMany(TrustScoreSnapshot::class);
    }

    public function badges()
    {
        return $this->hasMany(UserBadge::class);
    }

    public function riskFlags()
    {
        return $this->hasMany(RiskFlag::class);
    }

    public function safePin()
    {
        return $this->hasOne(SafePin::class);
    }

    public function chatMapping()
    {
        return $this->hasOne(ChatUserMapping::class);
    }

    public function meetings()
    {
        return $this->hasMany(Meeting::class, 'host_user_id');
    }

    /** Deduped rows of unique people who have searched this user's Safee PIN/QR. */
    public function pinSearches()
    {
        return $this->hasMany(MemberSearchCount::class, 'member_id');
    }

    /** Number of unique members who have searched this user's Safee PIN/QR. */
    public function pinSearchCount(): int
    {
        return $this->pinSearches()->count();
    }

    // public function emergencyContacts()
    // {
    //     return $this->hasMany(EmergencyContact::class);
    // }
    public function emergencyContacts()
    {
        return $this->hasMany(EmergencyContact::class, 'user_id');
    }

    public function sosIncidents()
    {
        return $this->hasMany(SosIncident::class, 'triggered_by_user_id');
    }

    public function incidentReports()
    {
        return $this->hasMany(IncidentReport::class, 'reporter_id');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function notificationPreferences()
    {
        return $this->hasOne(NotificationPreference::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(UserSubscription::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function supportTickets()
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function blockedUsers()
    {
        return $this->hasMany(BlockedUser::class, 'blocker_id');
    }

    public static function generateSafeePin(): string
    {
        do {
            $pin = 'SM-'.random_int(100000, 999999);
        } while (static::where('safee_pin', $pin)->exists());

        return $pin;
    }
}
