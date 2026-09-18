<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MembershipPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'monthly_price', 'annual_price', 'max_wa_groups',
        'monthly_token_quota', 'emergency_token_quota', 'trial_token_quota',
        'features', 'is_active',
    ];

    protected $casts = [
        'monthly_price' => 'decimal:2',
        'annual_price' => 'decimal:2',
        'max_wa_groups' => 'integer',
        'monthly_token_quota' => 'integer',
        'emergency_token_quota' => 'integer',
        'trial_token_quota' => 'integer',
        'features' => 'array',
        'is_active' => 'boolean',
    ];

    public function companyMemberships(): HasMany
    {
        return $this->hasMany(CompanyMembership::class, 'plan_id');
    }
}
