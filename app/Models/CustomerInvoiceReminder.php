<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris = satu pengingat piutang yang sudah terkirim (D-63).
 */
class CustomerInvoiceReminder extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'customer_invoice_id',
        'stage',
        'sent_at',
    ];

    protected $casts = ['sent_at' => 'datetime'];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(CustomerInvoice::class, 'customer_invoice_id');
    }
}
