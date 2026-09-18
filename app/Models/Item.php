<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    protected $fillable = [
        'company_id',
        'sku',
        'name',
        'type',
        'unit',
        'price',
        'cost',
        'min_stock',
        'track_batches',
        'attributes',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'cost' => 'decimal:2',
        'min_stock' => 'decimal:3',
        'track_batches' => 'boolean',
        'is_active' => 'boolean',
        'attributes' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(ItemBatch::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function bomComponents(): HasMany
    {
        return $this->hasMany(BomLine::class, 'product_item_id');
    }
}
