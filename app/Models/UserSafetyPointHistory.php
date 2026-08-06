<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class UserSafetyPointHistory extends Model
{
    protected $table = 'user_safety_point_histories';

    protected $fillable = [
        'user_id',
        'event_key',
        'points',
        'reference_type',
        'reference_id',
        'description',
    ];

    /**
     * User relationship
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}