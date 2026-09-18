<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Resource extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'type',
        'name',
        'category',
        'status',
        'capacity',
        'rate_amount',
        'rate_unit',
        'condition_notes',
        'attributes',
    ];

    protected $casts = [
        'rate_amount' => 'decimal:2',
        'attributes' => 'array',
    ];

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
