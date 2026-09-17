<?php

namespace App\Services\Eloquent;

use App\Contracts\CompanySettingsStore;
use App\Models\Company;
use App\Models\ModuleSetting;
use App\Services\CompanyPresetResolver;
use Closure;

class EloquentCompanySettingsStore implements CompanySettingsStore
{
    /** @return array<string, mixed> */
    public function read(string $company): array
    {
        $companyModel = Company::find($company);
        if (! $companyModel) {
            return [];
        }

        $features = ModuleSetting::where('company_id', $company)
            ->where('module_name', 'features')
            ->first();

        $terminology = ModuleSetting::where('company_id', $company)
            ->where('module_name', 'terminology')
            ->first();

        $settings = [];

        if ($companyModel->business_preset) {
            $settings['preset'] = $companyModel->business_preset;
        }

        if ($features) {
            $settings['features'] = $features->settings_json;
        }

        if ($terminology) {
            $settings['terminology'] = $terminology->settings_json;
        }

        return $settings;
    }

    /**
     * @param  Closure(array<string, mixed>): array<string, mixed>  $update
     * @return array<string, mixed>
     */
    public function update(string $company, Closure $update): array
    {
        $settings = $this->read($company);
        $settings = $update($settings);

        if (array_key_exists('preset', $settings)) {
            Company::where('id', $company)->update(['business_preset' => $settings['preset']]);
        }

        if (array_key_exists('features', $settings)) {
            ModuleSetting::updateOrCreate(
                ['company_id' => $company, 'module_name' => 'features'],
                ['settings_json' => $settings['features']]
            );
        }

        if (array_key_exists('terminology', $settings)) {
            ModuleSetting::updateOrCreate(
                ['company_id' => $company, 'module_name' => 'terminology'],
                ['settings_json' => $settings['terminology']]
            );
        }

        if (app()->resolved(CompanyPresetResolver::class)) {
            app(CompanyPresetResolver::class)->flushCache();
        }

        return $settings;
    }
}
