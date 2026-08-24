<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Incident extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'reporter_user_id', 'meeting_id', 'type', 'description',
        'latitude', 'longitude', 'emergency_contacts_notified',
        'status', 'resolved_by', 'resolved_at', 'resolution_notes',
    ];

    protected function casts(): array
    {
        return [
            'emergency_contacts_notified' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_user_id');
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeSos($query)
    {
        return $query->where('type', 'sos');
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'sos' => 'SOS Emergency',
            'fake_user' => 'Fake User Report',
            'fraud' => 'Fraud Report',
            'harassment' => 'Harassment Report',
            'general_incident' => 'General Incident',
            default => ucfirst(str_replace('_', ' ', (string) $this->type)),
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'open' => 'Active',
            'investigating' => 'Review',
            'resolved' => 'Resolved',
            default => ucfirst((string) $this->status),
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'open' => '#ef4444',
            'investigating' => '#f59e0b',
            'resolved' => '#22c55e',
            default => '#6b7280',
        };
    }
}
