<?php

namespace App\Models;

use Database\Factories\LoyaltyRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyRule extends Model
{
    /** @use HasFactory<LoyaltyRuleFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'nominal_per_point',
        'item_point_rates',
        'expiry_months',
        'is_active',
    ];

    protected $casts = [
        'item_point_rates' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
