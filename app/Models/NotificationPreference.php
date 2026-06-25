<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'push_enabled',
        'email_enabled',
        'sms_enabled',
        'meeting_alerts',
        'sos_alerts',
        'chat_notifications',
        'marketing_emails',
        'created_at',
        'updated_at',
    ];
}
