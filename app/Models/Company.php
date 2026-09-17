<?php

namespace App\Models;

use App\Contracts\CompanyContext;
use App\Services\FeatureResolver;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['name', 'slug', 'owner_user_id', 'business_preset', 'theme', 'is_active'])]
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * The owner of this company.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * The business identities associated with this company.
     */
    public function identities(): HasMany
    {
        return $this->hasMany(BusinessIdentity::class);
    }

    /**
     * The default business identity for this company.
     */
    public function defaultIdentity(): HasOne
    {
        return $this->hasOne(BusinessIdentity::class)->where('is_default', true);
    }

    /**
     * Check if a feature capability is enabled for this company.
     */
    public function feature(string $key): bool
    {
        $context = app(CompanyContext::class);
        $context->setCurrent((string) $this->id);

        return app(FeatureResolver::class)->enabled($key);
    }
}
