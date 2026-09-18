<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderEFaktur extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'order_id',
        'nomor_seri',
        'npwp_lawan_transaksi',
        'ppn',
        'status',
        'error_message',
    ];

    protected $casts = [
        'ppn' => 'decimal:2',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
