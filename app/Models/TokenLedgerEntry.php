<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TokenLedgerEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id', 'company_membership_id', 'direction', 'amount', 'balance_after',
        'source', 'idempotency_key', 'reference_type', 'reference_id', 'provider', 'model', 'metadata',
    ];

    protected $casts = [
        'amount' => 'integer',
        'balance_after' => 'integer',
        'metadata' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(CompanyMembership::class, 'company_membership_id');
    }
}
