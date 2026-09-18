<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Contracts\CompanySettingsStore;
use App\Contracts\PresetSource;
use App\Models\Company;
use App\Models\HermesProfile;
use App\Models\MembershipPlan;
use App\Models\ModuleSetting;
use App\Models\User;
use App\Services\Eloquent\EloquentCompanyContext;
use App\Services\Eloquent\EloquentCompanySettingsStore;
use App\Services\FeatureResolver;
use App\Services\Preset\EloquentPresetSource;
use Database\Seeders\BusinessPresetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HermesProfilePlatformWaNumberTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['datasource.driver' => 'eloquent']);
        $this->app->scoped(CompanyContext::class, EloquentCompanyContext::class);
        $this->app->scoped(CompanySettingsStore::class, EloquentCompanySettingsStore::class);
        $this->app->bind(PresetSource::class, EloquentPresetSource::class);

        $this->artisan('db:seed', ['--class' => BusinessPresetSeeder::class]);
    }

    public function test_profile_can_be_marked_as_platform_provided(): void
    {
        $owner = User::factory()->create();

        $profile = HermesProfile::factory()->create([
            'owner_user_id' => $owner->id,
            'is_platform_provided' => true,
        ]);

        $this->assertTrue($profile->fresh()->is_platform_provided);
    }

    public function test_addon_platform_wa_number_capability_can_be_enabled(): void
    {
        $owner = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $owner->id]);

        $plan = MembershipPlan::factory()->create(['features' => ['addon.platform_wa_number']]);
        $company->memberships()->create([
            'plan_id' => $plan->id,
            'starts_at' => now(),
            'expires_at' => now()->addMonth(),
            'status' => 'active',
        ]);

        ModuleSetting::create([
            'company_id' => $company->id,
            'module_name' => 'features',
            'settings_json' => ['addon.platform_wa_number' => true],
        ]);

        app(CompanyContext::class)->setCurrent((string) $company->id);

        $resolver = app(FeatureResolver::class);
        $this->assertTrue($resolver->enabled('addon.platform_wa_number'));
    }

    public function test_addon_platform_wa_number_capability_disabled_without_setting(): void
    {
        $owner = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $owner->id]);

        $plan = MembershipPlan::factory()->create(['features' => ['addon.platform_wa_number']]);
        $company->memberships()->create([
            'plan_id' => $plan->id,
            'starts_at' => now(),
            'expires_at' => now()->addMonth(),
            'status' => 'active',
        ]);

        app(CompanyContext::class)->setCurrent((string) $company->id);

        $resolver = app(FeatureResolver::class);
        $this->assertFalse($resolver->enabled('addon.platform_wa_number'));
    }
}
