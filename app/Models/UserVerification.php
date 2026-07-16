<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class UserVerification extends Model
{
    protected $fillable = [
        'user_id',
        'face_id_image',
        'national_id_front_image',
        'national_id_back_image',
        'national_id_number',
        'national_id_country',
        'verification_level',
        'status',
        'reviewed_by',
        'reviewed_by_admin_id',
        'rejection_reason',
        'submitted_at',
        'reviewed_at',
        'approved_at',
        'rejected_at',
    ];

    protected $casts = [
        'verification_level' => 'integer',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function reviewedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by_admin_id');
    }
    public function verification(): HasOne
    {
        return $this->hasOne(UserVerification::class);
    }
}