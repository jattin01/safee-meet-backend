<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminAuditLog extends Model
{
    use HasUlids;

    // Append-only: rows are never updated, so there is no updated_at.
    const UPDATED_AT = null;

    protected $fillable = [
        'admin_id', 'action', 'target_type', 'target_id',
        'old_values', 'new_values', 'ip_address', 'user_agent', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}
