<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Contracts\CompanySettingsStore;
use App\Contracts\PresetSource;
use App\Models\BusinessPreset;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\MembershipPlan;
use App\Models\ModuleSetting;
use App\Models\User;
use App\Services\Eloquent\EloquentCompanyContext;
use App\Services\Eloquent\EloquentCompanySettingsStore;
use App\Services\FeatureResolver;
use App\Services\Preset\EloquentPresetSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class EloquentFeatureResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('datasource.driver', 'eloquent');
        $this->app->scoped(CompanyContext::class, EloquentCompanyContext::class);
        $this->app->scoped(PresetSource::class, EloquentPresetSource::class);
        $this->app->scoped(CompanySettingsStore::class, EloquentCompanySettingsStore::class);

        BusinessPreset::create([
            'key' => 'preset_a',
            'name' => 'Preset A',
            'tier' => 'A',
            'definition' => [
                'capabilities' => ['contacts' => true, 'projects' => false],
            ],
        ]);

        BusinessPreset::create([
            'key' => 'preset_b',
            'name' => 'Preset B',
            'tier' => 'A',
            'definition' => [
                'capabilities' => ['contacts' => false, 'projects' => true, 'bookings' => true],
            ],
        ]);

        BusinessPreset::create([
            'key' => 'preset_sensitive',
            'name' => 'Preset Sensitive',
            'tier' => 'B',
            'definition' => [
                'tier' => 'B',
                'capabilities' => [
                    'contacts' => true,
                    'pos' => true,
                    'inventory.batch_expiry' => true,
                    'pharmacy.prescription' => true,
                ],
            ],
        ]);
    }

    public function test_it_resolves_capabilities_from_preset()
    {
        $user = User::factory()->create();
        $company = Company::factory()->create([
            'owner_user_id' => $user->id,
            'business_preset' => 'preset_a',
        ]);

        $this->actingAs($user);
        app(CompanyContext::class)->setCurrent((string) $company->id);

        $resolver = app(FeatureResolver::class);

        $this->assertTrue($resolver->enabled('contacts'));
        $this->assertFalse($resolver->enabled('projects'));
        $this->assertFalse($resolver->enabled('bookings'));
    }

    public function test_company_switch_does_not_leak_capabilities()
    {
        $user = User::factory()->create();
        $companyA = Company::factory()->create([
            'owner_user_id' => $user->id,
            'business_preset' => 'preset_a',
        ]);

        $companyB = Company::factory()->create([
            'owner_user_id' => $user->id,
            'business_preset' => 'preset_b',
        ]);

        $this->actingAs($user);

        app(CompanyContext::class)->setCurrent((string) $companyA->id);
        $resolver = app(FeatureResolver::class);

        $this->assertTrue($resolver->enabled('contacts'));
        $this->assertFalse($resolver->enabled('projects'));

        app(CompanyContext::class)->setCurrent((string) $companyB->id);
        $resolver->flushCache();

        $this->assertFalse($resolver->enabled('contacts'));
        $this->assertTrue($resolver->enabled('projects'));
    }

    public function test_module_settings_override_preset()
    {
        $user = User::factory()->create();
        $company = Company::factory()->create([
            'owner_user_id' => $user->id,
            'business_preset' => 'preset_a',
        ]);

        ModuleSetting::create([
            'company_id' => $company->id,
            'module_name' => 'features',
            'settings_json' => ['contacts' => false, 'projects' => true, 'pos' => true],
        ]);

        $this->actingAs($user);
        app(CompanyContext::class)->setCurrent((string) $company->id);

        $resolver = app(FeatureResolver::class);

        $this->assertFalse($resolver->enabled('contacts'));
        $this->assertTrue($resolver->enabled('projects'));
        $this->assertTrue($resolver->enabled('pos'));
    }

    public function test_unknown_key_is_always_false()
    {
        $user = User::factory()->create();
        $company = Company::factory()->create([
            'owner_user_id' => $user->id,
            'business_preset' => 'preset_a',
        ]);

        $this->actingAs($user);
        app(CompanyContext::class)->setCurrent((string) $company->id);

        $resolver = app(FeatureResolver::class);

        $this->assertFalse($resolver->enabled('unknown_feature'));
    }

    public function test_changing_company_preset_resolves_new_capabilities_immediately()
    {
        $user = User::factory()->create();
        $company = Company::factory()->create([
            'owner_user_id' => $user->id,
            'business_preset' => 'preset_a',
        ]);

        $this->actingAs($user);
        app(CompanyContext::class)->setCurrent((string) $company->id);

        $resolver = app(FeatureResolver::class);

        $this->assertTrue($resolver->enabled('contacts'));
        $this->assertFalse($resolver->enabled('bookings'));

        $company->update(['business_preset' => 'preset_b']);

        // Ganti cache company, ini butuh reload ulang model. setCurrent re-fetches
        app(CompanyContext::class)->setCurrent((string) $company->id);
        $resolver->flushCache();

        $this->assertFalse($resolver->enabled('contacts'));
        $this->assertTrue($resolver->enabled('bookings'));
    }

    public function test_capabilities_are_gated_by_membership_plan()
    {
        $user = User::factory()->create();
        $company = Company::factory()->create([
            'owner_user_id' => $user->id,
            'business_preset' => 'preset_a', // wants contacts
        ]);

        ModuleSetting::create([
            'company_id' => $company->id,
            'module_name' => 'features',
            'settings_json' => ['pos' => true], // wants pos
        ]);

        $plan = MembershipPlan::factory()->create([
            'features' => ['contacts', 'projects'], // does not allow pos
        ]);

        CompanyMembership::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);

        $this->actingAs($user);
        app(CompanyContext::class)->setCurrent((string) $company->id);

        $resolver = app(FeatureResolver::class);

        // Allowed by both preset and plan
        $this->assertTrue($resolver->enabled('contacts'));

        // Wanted by override but disallowed by plan
        $this->assertFalse($resolver->enabled('pos'));

        // Allowed by plan but not wanted by preset/override
        $this->assertFalse($resolver->enabled('projects'));
    }

    public function test_sensitive_capability_is_disabled_without_privacy_consent(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create([
            'owner_user_id' => $user->id,
            'business_preset' => 'preset_sensitive',
            'privacy_accepted_at' => null,
            'privacy_accepted_by_user_id' => null,
            'privacy_policy_version' => null,
        ]);

        $this->actingAs($user);
        app(CompanyContext::class)->setCurrent((string) $company->id);

        $resolver = app(FeatureResolver::class);

        $this->assertFalse($resolver->enabled('pharmacy.prescription'));
        $this->assertTrue($resolver->enabled('contacts'));
    }

    public function test_sensitive_capability_is_enabled_after_privacy_consent_is_recorded(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create([
            'owner_user_id' => $user->id,
            'business_preset' => 'preset_sensitive',
            'privacy_accepted_at' => now(),
            'privacy_accepted_by_user_id' => $user->id,
            'privacy_policy_version' => '2026-09-18',
        ]);

        $this->actingAs($user);
        app(CompanyContext::class)->setCurrent((string) $company->id);

        $resolver = app(FeatureResolver::class);

        $this->assertTrue($resolver->enabled('pharmacy.prescription'));
    }

    public function test_manufacturing_capability_fails_closed_when_any_locked_dependency_is_missing(): void
    {
        $user = User::factory()->create();
        $resolver = app(FeatureResolver::class);

        foreach (['inventory.bom', 'inventory.batch_expiry', 'finance.accounting'] as $index => $missing) {
            $capabilities = [
                'inventory' => true,
                'inventory.bom' => true,
                'inventory.batch_expiry' => true,
                'finance.accounting' => true,
                'manufacturing.production_order' => true,
            ];
            unset($capabilities[$missing]);

            $presetKey = 'manufacturing_missing_'.$index;
            BusinessPreset::create([
                'key' => $presetKey,
                'name' => 'Preset Tier B '.$index,
                'tier' => 'B',
                'definition' => ['tier' => 'B', 'capabilities' => $capabilities],
            ]);
            $company = Company::factory()->create([
                'owner_user_id' => $user->id,
                'business_preset' => $presetKey,
            ]);

            app(CompanyContext::class)->setCurrent((string) $company->id);
            $resolver->flushCache();

            try {
                $resolver->enabled('manufacturing.production_order');
                $this->fail("Capability harus fail-closed tanpa {$missing}");
            } catch (\InvalidArgumentException $exception) {
                $this->assertStringContainsString("manufacturing.production_order membutuhkan {$missing}", $exception->getMessage());
            }
        }
    }
}
