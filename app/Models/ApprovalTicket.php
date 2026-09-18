<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalTicket extends Model
{
    protected $fillable = [
        'company_id',
        'code',
        'action_type',
        'subject_type',
        'subject_id',
        'payload',
        'amount',
        'requested_by_user_id',
        'approver_user_id',
        'status',
        'channel',
        'expires_at',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'amount' => 'decimal:2',
            'expires_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function markConsumed(): void
    {
        $this->update(['status' => 'consumed', 'responded_at' => now()]);
    }

    public function markExpired(): void
    {
        $this->update(['status' => 'expired']);
    }
}
