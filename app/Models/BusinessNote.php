<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Basis pengetahuan usaha per tenant (T-107): SOP, catatan pelanggan,
 * kesepakatan harga, cara kerja - hal yang membuat bot terdengar mengenal
 * usaha itu tanpa hidup di memori yang bisa dipangkas.
 *
 * `author_type` menegakkan T-107(b): bot hanya boleh MENAMBAH (baris baru
 * atau tambahan di bawah baris yang ada); menimpa isi tulisan manusia adalah
 * owner-only lewat web. Penegakannya di `KnowledgeController`/`BusinessNoteWriteService`,
 * bukan di sini - model ini tidak tahu siapa pemanggilnya.
 */
class BusinessNote extends Model
{
    protected $fillable = [
        'company_id',
        'title',
        'content',
        'author_type',
        'created_by_user_id',
        'sensitive',
    ];

    protected $casts = [
        'sensitive' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
