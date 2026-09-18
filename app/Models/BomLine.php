<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BomLine extends Model
{
    protected $fillable = [
        'company_id',
        'product_item_id',
        'component_item_id',
        'qty',
    ];

    protected $casts = [
        'qty' => 'decimal:3',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'product_item_id');
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'component_item_id');
    }
}
