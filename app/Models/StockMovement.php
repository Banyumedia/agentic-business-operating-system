<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockMovement extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'company_id',
        'item_id',
        'batch_id',
        'direction',
        'qty',
        'reason',
        'reference_type',
        'reference_id',
    ];

    protected $casts = [
        'qty' => 'decimal:3',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ItemBatch::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo('reference');
    }
}
