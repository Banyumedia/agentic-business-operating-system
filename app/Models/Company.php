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
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'slug', 'custom_domain', 'owner_user_id', 'parent_company_id', 'business_preset', 'theme', 'is_active', 'privacy_accepted_at', 'privacy_accepted_by_user_id', 'privacy_policy_version'])]
class Company extends Model
{
    protected static function booted(): void
    {
        static::saving(function ($model) {
            if (session()->has('admin_impersonation_id')) {
                $session = AdminImpersonationSession::where('session_id', session('admin_impersonation_id'))->first();
                if ($session) {
                    $model->changed_by_type = 'admin_impersonation';
                    $model->admin_user_id = $session->admin_user_id;
                }
            } else {
                $model->changed_by_type = null;
                $model->admin_user_id = null;
            }
        });
    }

    /** @use HasFactory<CompanyFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'privacy_accepted_at' => 'datetime',
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
     * The memberships associated with this company.
     */
    /**
     * The memberships associated with this company.
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(CompanyMembership::class);
    }

    /**
     * The invoices associated with this company.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * The default business identity for this company.
     */
    public function defaultIdentity(): HasOne
    {
        return $this->hasOne(BusinessIdentity::class)->where('is_default', true);
    }

    public function settings(): HasMany
    {
        return $this->hasMany(ModuleSetting::class);
    }

    /**
     * The parent company of this branch.
     */
    public function parentCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'parent_company_id');
    }

    /**
     * The branch companies of this parent.
     */
    public function branches(): HasMany
    {
        return $this->hasMany(Company::class, 'parent_company_id');
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
