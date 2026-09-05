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
