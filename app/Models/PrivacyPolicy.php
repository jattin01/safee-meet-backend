<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrivacyPolicy extends Model
{
    protected $table = 'privacy_policies';

    protected $fillable = [
        'content', 'updated_by',
    ];
}
