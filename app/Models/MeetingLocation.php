<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class MeetingLocation extends Model
{
    // This table has no updated_at column.
    public $timestamps = false;

    protected $fillable = [
        'meeting_id', 'user_id', 'latitude', 'longitude', 'recorded_at',
    ];

    protected function casts(): array
    {
        return ['recorded_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $location) {
            $key = $location->getKeyName();
            if (empty($location->{$key}) && static::usesUlidKey()) {
                $location->{$key} = (string) Str::ulid();
            }
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
        return in_array(Schema::getColumnType('meeting_locations', 'id'), ['char', 'string'], true);
    }

    public function meeting()
    {
        return $this->belongsTo(Meeting::class);
    }
}
