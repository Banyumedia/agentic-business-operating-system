<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModuleSetting extends Model
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

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'module_name',
        'settings_json',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settings_json' => 'array',
        ];
    }

    /**
     * Get the company that owns the module setting.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
