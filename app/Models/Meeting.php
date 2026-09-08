<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class Meeting extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * Statuses that are still "in flight" — i.e. eligible to auto-expire if
     * nobody accepts/rejects/completes/cancels the meeting in time. A meeting
     * in a terminal status (completed, cancelled, declined, expired,
     * emergency, incident_reported) is never overwritten by the expiry rule.
     */
    public const EXPIRABLE_STATUSES = [
        'draft', 'pending_approval', 'scheduled', 'approved', 'active', 'live',
    ];

    /**
     * Hours after scheduled_start_at at which an un-actioned meeting expires
     * automatically, regardless of manual host/guest action.
     */
    public const EXPIRY_HOURS = 6;

    protected $fillable = [
        'reference', 'host_user_id', 'guest_user_id',
        'user_subscription_id',
        'title', 'scheduled_start_at', 'planned_address', 'planned_latitude', 'planned_longitude',
        'meeting_date', 'meeting_time', 'location', 'latitude', 'longitude',
        'purpose', 'item_or_service', 'type', 'status',
        'trust_score_snapshot', 'arrived_at',
    ];

    protected function casts(): array
    {
        return [
            // Keep these string-shaped in API responses regardless of the
            // underlying users.id column type (char ULID or bigint) — the
            // mobile client expects string ids everywhere.
            'id' => 'string',
            'host_user_id' => 'string',
            'guest_user_id' => 'string',
            'meeting_date' => 'date',
            'scheduled_start_at' => 'datetime',
            'arrived_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Meeting $meeting) {
            $key = $meeting->getKeyName();
            if (empty($meeting->{$key}) && static::usesUlidKey()) {
                $meeting->{$key} = (string) Str::ulid();
            }

            if ($meeting->reference) {
                return;
            }

            do {
                $reference = 'SM-'.random_int(1000, 9999);
            } while (static::where('reference', $reference)->exists());

            $meeting->reference = $reference;
        });
    }

    public function getIncrementing()
    {
        return !static::usesUlidKey();
    }

    public function getKeyType()
    {
        return static::usesUlidKey() ? 'string' : 'int';
    }

    public function userSubscription(): BelongsTo
    {
        return $this->belongsTo(UserSubscription::class);
    }

    private static function usesUlidKey(): bool
    {
        return in_array(Schema::getColumnType('meetings', 'id'), ['char', 'string'], true);
    }

    /**
     * True once scheduled_start_at + EXPIRY_HOURS has passed and the meeting
     * is still sitting in an expirable status (i.e. nobody accepted,
     * rejected, cancelled, or completed it in time).
     */
    public function isExpired(): bool
    {
        // Read the raw column directly (not via getAttribute()) so this
        // works both before and after the model's original state is synced,
        // and never recurses back into the status accessor below.
        return in_array($this->getAttributeFromArray('status'), self::EXPIRABLE_STATUSES, true)
            && $this->scheduled_start_at
            && now()->greaterThanOrEqualTo(
                $this->scheduled_start_at->copy()->addHours(self::EXPIRY_HOURS)
            );
    }

    /**
     * Reflects auto-expiry instantly everywhere the model's status is read
     * (API responses, blade views, admin lists) even for the brief window
     * before the scheduled command persists it to the database.
     */
    public function getStatusAttribute(?string $value): ?string
    {
        return $this->isExpired() ? 'expired' : $value;
    }

    /**
     * Meetings still eligible to show up as "Upcoming" — excludes anything
     * already in a terminal status and anything past its expiry boundary
     * (scheduled_start_at + EXPIRY_HOURS), as a query-level safety net on top
     * of the scheduled expiry command.
     */
    public function scopeUpcoming($query)
    {
        return $query->whereIn('status', self::EXPIRABLE_STATUSES)
            ->where('scheduled_start_at', '>', now()->subHours(self::EXPIRY_HOURS));
    }

    /**
     * Excludes meetings that are past their expiry boundary, regardless of
     * their stored status — a defensive filter for any "active"/"upcoming"
     * listing that shouldn't wait on the scheduled command.
     */
    public function scopeNotExpired($query)
    {
        return $query->where(function ($query) {
            $query->whereNotIn('status', self::EXPIRABLE_STATUSES)
                ->orWhere('scheduled_start_at', '>', now()->subHours(self::EXPIRY_HOURS));
        });
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_user_id');
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guest_user_id');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(MeetingLocation::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(MeetingReview::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'completed' => 'Completed',
            'scheduled' => 'Upcoming',
            'active', 'live' => 'In Progress',
            'cancelled' => 'Cancelled',
            'expired' => 'Expired',
            'emergency', 'incident_reported' => 'Incident',
            'draft' => 'Draft',
            default => ucfirst((string) $this->status),
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'completed' => '#22c55e',
            'scheduled' => '#f59e0b',
            'active', 'live' => '#3b82f6',
            'cancelled', 'emergency', 'incident_reported' => '#ef4444',
            default => '#6b7280',
        };
    }
}
