<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tagihan pelanggan tenant (D-62). Bukan `Invoice`, yang merupakan tagihan
 * langganan platform (D-23).
 */
class CustomerInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'number',
        'contact_id',
        'project_id',
        'quotation_id',
        'title',
        'status',
        'issue_date',
        'due_date',
        'subtotal',
        'discount_amount',
        'dpp',
        'tax',
        'grand_total',
        'paid_amount',
        'notes',
        'attributes',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'dpp' => 'decimal:2',
        'tax' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'attributes' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CustomerInvoiceLine::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
