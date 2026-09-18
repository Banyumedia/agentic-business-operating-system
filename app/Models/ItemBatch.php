<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemBatch extends Model
{
    protected $fillable = [
        'company_id',
        'item_id',
        'batch_no',
        'expires_on',
        'qty_on_hand',
    ];

    protected $casts = [
        'expires_on' => 'date',
        'qty_on_hand' => 'decimal:3',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
