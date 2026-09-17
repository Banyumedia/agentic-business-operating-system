<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Contracts\CompanySettingsStore;
use App\Contracts\PresetSource;
use App\Models\BusinessPreset;
use App\Models\Company;
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
}
