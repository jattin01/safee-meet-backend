<?php

namespace App\Models;
use App\Models\Admin;


use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'status',
    ];

    public function admins()
    {
        return $this->hasMany(Admin::class);
    }
}
