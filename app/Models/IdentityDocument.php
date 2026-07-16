<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class IdentityDocument extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'identity_documents';

    protected $fillable = [
        'identity_verification_id',
        'user_id',
        'document_type',
        'issuing_country_code',
        'document_number_hash',
        'front_file_url',
        'back_file_url',
        'extracted_name_encrypted',
        'extracted_dob_encrypted',
        'status',
        'rejection_reason',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    public function verification()
    {
        return $this->belongsTo(IdentityVerification::class, 'identity_verification_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
