<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompanyMembership extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id', 'plan_id', 'status', 'starts_at', 'expires_at', 'trial_ends_at',
        'max_wa_groups', 'max_users', 'monthly_token_quota', 'emergency_token_quota', 'emergency_balance', 'current_token_balance',
        'metadata',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'max_wa_groups' => 'integer',
        'max_users' => 'integer',
        'monthly_token_quota' => 'integer',
        'emergency_token_quota' => 'integer',
        'emergency_balance' => 'integer',
        'current_token_balance' => 'integer',
        'metadata' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class, 'plan_id');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(TokenLedgerEntry::class);
    }
}
