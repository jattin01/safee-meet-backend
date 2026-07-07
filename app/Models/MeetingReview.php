<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingReview extends Model
{
    protected $fillable = [
        'meeting_id', 'reviewer_id', 'reviewee_id', 'rating', 'comment',
        'punctual', 'trustworthy', 'responsive', 'helpful_count',
    ];

    protected function casts(): array
    {
        return [
            'punctual' => 'boolean',
            'trustworthy' => 'boolean',
            'responsive' => 'boolean',
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function reviewee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewee_id');
    }
}
