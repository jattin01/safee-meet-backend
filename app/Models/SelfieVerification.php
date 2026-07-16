<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SelfieVerification extends Model
{
    use HasFactory;

    protected $table = 'selfie_verifications';

    protected $fillable = [
        'identity_verification_id',
        'user_id',
        'selfie_file_url',
        'liveness_score',
        'face_match_score',
        'status',
        'failure_reason',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'liveness_score' => 'decimal:2',
            'face_match_score' => 'decimal:2',
        ];
    }

    public function verification()
    {
        return $this->belongsTo(IdentityVerification::class, 'identity_verification_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
