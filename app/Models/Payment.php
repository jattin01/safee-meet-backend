<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use SoftDeletes;

    /**
     * Payments whose money (or part of it) was kept. A partially refunded
     * payment still counts, minus what was returned — see NET_AMOUNT_SQL.
     */
    public const COLLECTED_STATUSES = ['succeeded', 'partially_refunded'];

    /** Collected minor units for one row: amount paid minus amount refunded. */
    public const NET_AMOUNT_SQL = '(payments.amount - payments.amount_refunded)';

    protected $fillable = [
        'user_id', 'subscription_id', 'stripe_invoice_id', 'stripe_payment_intent_id',
        'amount', 'amount_refunded', 'currency', 'status', 'paid_at', 'refunded_at',
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
            'amount' => 'integer',
            'amount_refunded' => 'integer',
        ];
    }

    public function scopeCollected(Builder $query): Builder
    {
        return $query->whereIn('payments.status', self::COLLECTED_STATUSES);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
