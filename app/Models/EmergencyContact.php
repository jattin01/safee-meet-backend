<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class EmergencyContact extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'full_name',
        'relationship',
        'phone_number',

    ];

    protected static function booted(): void
    {
        // On a fresh install this table's id is a bigint auto-increment,
        // but on deployments where it already existed under the app's
        // older schema it's a char(26) ULID with no default — generate
        // one ourselves only when the column actually needs it.
        static::creating(function (self $contact) {
            $key = $contact->getKeyName();
            if (empty($contact->{$key}) && static::usesUlidKey()) {
                $contact->{$key} = (string) Str::ulid();
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
        return in_array(Schema::getColumnType('emergency_contacts', 'id'), ['char', 'string'], true);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
