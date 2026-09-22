<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Undangan staf (D-65). Kodenya hanya tersimpan sebagai hash.
 */
class CompanyInvitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'invited_by_user_id',
        'wa_number',
        'role',
        'code_hash',
        'expires_at',
        'accepted_at',
        'revoked_at',
    ];

    protected $hidden = ['code_hash'];

    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Undangan hanya dapat dipakai bila belum diterima, belum dicabut, dan
     * belum kedaluwarsa. Ketiganya diperiksa bersama supaya tidak ada jalur
     * yang lolos karena satu kolom saja yang dicek.
     */
    public function isClaimable(): bool
    {
        return $this->accepted_at === null
            && $this->revoked_at === null
            && $this->expires_at !== null
            && $this->expires_at->isFuture();
    }
}
