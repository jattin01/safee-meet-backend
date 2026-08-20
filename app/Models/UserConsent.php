<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserConsent extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    public const CRIMINAL_BACKGROUND_CHECK = 'criminal_background_check';

    protected $fillable = [
        'user_id', 'consent_type', 'version', 'accepted', 'accepted_at',
        'revoked_at', 'ip_address', 'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'accepted' => 'boolean',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function scopeActiveBackgroundCheck(Builder $query): Builder
    {
        return $query
            ->where('consent_type', self::CRIMINAL_BACKGROUND_CHECK)
            ->where('accepted', true)
            ->whereNull('revoked_at');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
