<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class Meeting extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference', 'host_user_id', 'guest_user_id',
        'title', 'scheduled_start_at', 'planned_address', 'planned_latitude', 'planned_longitude',
        'meeting_date', 'meeting_time', 'location', 'latitude', 'longitude',
        'purpose', 'item_or_service', 'type', 'status',
        'trust_score_snapshot', 'arrived_at',
    ];

    protected function casts(): array
    {
        return [
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
}
