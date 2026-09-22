<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'legal_name', 'npwp', 'address', 'price_includes_tax', 'tax_mode', 'tax_rate', 'is_default', 'fiscal_locked_at'])]
class BusinessIdentity extends Model
{
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_includes_tax' => 'boolean',
            'tax_mode' => 'string',
            'tax_rate' => 'decimal:2',
            'is_default' => 'boolean',
            'fiscal_locked_at' => 'datetime',
        ];
    }

    /**
     * The company this identity belongs to.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
