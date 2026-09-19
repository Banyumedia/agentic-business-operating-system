<?php

namespace App\Services;

use App\Contracts\CompanyContext;
use App\Models\Company;
use InvalidArgumentException;

class FeatureResolver
{
    /** @var list<string> */
    public const CAPABILITIES = [
        'contacts', 'deals', 'projects', 'projects.progress_billing',
        'scheduling', 'bookings', 'bookings.deposit', 'inventory',
        'inventory.batch_expiry', 'inventory.bom', 'pos', 'pos.tables',
        'quotations', 'milestone_billing', 'approval_flow', 'timesheet',
        'finance.cashbook', 'finance.accounting', 'hr.employees', 'hr.payroll',
        'system.ai_agent', 'pharmacy.prescription', 'construction.retention',
        'manufacturing.production_order',
        'addon.branches', 'addon.platform_wa_number', 'addon.payroll_advanced',
        'addon.custom_domain', 'addon.efaktur', 'addon.marketplace_sync',
        'addon.managed_storage', 'addon.loyalty',
    ];

    /** @var list<string> */
    public const SENSITIVE_CAPABILITIES = [
        'pharmacy.prescription',
        'addon.payroll_advanced',
    ];

    /** @var array<string, list<string>> */
    private const DEPENDENCIES = [
        'system.ai_agent' => ['approval_flow'],
        'pharmacy.prescription' => ['inventory.batch_expiry', 'pos', 'contacts'],
        'construction.retention' => ['projects.progress_billing', 'milestone_billing'],
        'manufacturing.production_order' => ['inventory.bom', 'inventory.batch_expiry', 'finance.accounting'],
    ];

    /** @var list<string> */
    private const TIER_B = ['pharmacy.prescription', 'construction.retention', 'manufacturing.production_order'];

    public function __construct(
        private readonly CompanyPresetResolver $companyPreset,
        private readonly PlanCapabilityGate $planGate,
        private readonly CompanyContext $companyContext,
    ) {}

    public function enabled(string $key): bool
    {
        if (! in_array($key, self::CAPABILITIES, true)) {
            return false;
        }

        return $this->effectiveCapabilities()[$key];
    }

    /** @param list<string> $keys */
    public function hasAny(array $keys): bool
    {
        foreach ($keys as $key) {
            if ($this->enabled($key)) {
                return true;
            }
        }

        return false;
    }

    public function flushCache(): void
    {
        $this->companyPreset->flushCache();
    }

    /** @param array<string, mixed> $settings
     * @return array<string, bool>
     */
    private function overrides(array $settings): array
    {
        $overrides = $settings['features'] ?? [];
        if (! is_array($overrides) || ($overrides !== [] && array_is_list($overrides))) {
            throw new InvalidArgumentException('Override fitur harus object.');
        }

        foreach ($overrides as $key => $enabled) {
            if (! is_string($key) || ! in_array($key, self::CAPABILITIES, true) || ! is_bool($enabled)) {
                throw new InvalidArgumentException('Override fitur tidak valid: '.(is_string($key) ? $key : gettype($key)));
            }
        }

        return $overrides;
    }

    /** @return array<string, bool> */
    private function effectiveCapabilities(): array
    {
        ['settings' => $settings, 'preset' => $preset] = $this->companyPreset->current();

        $planAllowed = $this->planGate->allowedCapabilities();
        $isPlanActive = count($planAllowed) > 0;

        $effective = array_fill_keys(self::CAPABILITIES, false);

        foreach ($preset['capabilities'] as $key => $enabled) {
            // Apply plan capability gate intersection: preset ∩ module_settings ∩ plan.features
            $effective[$key] = $enabled && (! $isPlanActive || in_array($key, $planAllowed, true));
        }

        foreach ($this->overrides($settings) as $key => $enabled) {
            // Override also constrained by plan
            $effective[$key] = $enabled && (! $isPlanActive || in_array($key, $planAllowed, true));
        }

        foreach (self::SENSITIVE_CAPABILITIES as $capability) {
            if ($effective[$capability] && ! $this->hasPrivacyConsent()) {
                $effective[$capability] = false;
            }
        }

        foreach (self::TIER_B as $capability) {
            if ($effective[$capability] && $preset['tier'] !== 'B') {
                throw new InvalidArgumentException("Capability Tier B membutuhkan preset tier B: {$capability}");
            }
        }
        foreach (self::DEPENDENCIES as $capability => $dependencies) {
            if (! $effective[$capability]) {
                continue;
            }
            foreach ($dependencies as $dependency) {
                if (! $effective[$dependency]) {
                    throw new InvalidArgumentException("Dependensi capability tidak aktif: {$capability} membutuhkan {$dependency}");
                }
            }
        }

        return $effective;
    }

    private function hasPrivacyConsent(): bool
    {
        if (config('datasource.driver') !== 'eloquent') {
            return true;
        }

        $companyId = $this->companyContext->current();
        if (! ctype_digit($companyId)) {
            return false;
        }

        $company = Company::find((int) $companyId);
        if (! $company) {
            return false;
        }

        return $company->privacy_accepted_at !== null
            && $company->privacy_accepted_by_user_id !== null
            && is_string($company->privacy_policy_version)
            && $company->privacy_policy_version !== '';
    }
}
