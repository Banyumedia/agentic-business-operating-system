<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'entry_date',
        'direction',
        'amount',
        'category',
        'description',
        'contact_id',
        'project_id',
        'source_type',
        'source_id',
        'journal_id',
        'created_by_user_id',
        'external_reference',
        'notes',
        'custom',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'amount' => 'decimal:2',
        'custom' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
