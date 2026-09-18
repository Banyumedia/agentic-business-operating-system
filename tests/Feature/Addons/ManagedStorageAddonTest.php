<?php

namespace Tests\Feature\Addons;

use App\Contracts\CompanyContext;
use App\Contracts\CompanySettingsStore;
use App\Contracts\PresetSource;
use App\Models\Company;
use App\Models\MembershipPlan;
use App\Models\ModuleSetting;
use App\Models\User;
use App\Services\Addons\Storage\CompanyDiskResolver;
use App\Services\Eloquent\EloquentCompanyContext;
use App\Services\Eloquent\EloquentCompanySettingsStore;
use App\Services\Preset\EloquentPresetSource;
use Database\Seeders\BusinessPresetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ManagedStorageAddonTest extends TestCase
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

    public function test_default_company_uses_byos_disk_and_quota(): void
    {
        $owner = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $owner->id]);

        app(CompanyContext::class)->setCurrent((string) $company->id);

        $resolver = app(CompanyDiskResolver::class);

        $this->assertEquals('google-drive', $resolver->getDiskName());
        $this->assertEquals(100 * 1024 * 1024, $resolver->getQuotaBytes());

        // Under quota
        $resolver->authorizeUpload(50 * 1024 * 1024, 40 * 1024 * 1024);

        // Over quota
        $this->expectException(RuntimeException::class);
        $resolver->authorizeUpload(50 * 1024 * 1024, 60 * 1024 * 1024);
    }

    public function test_managed_storage_capability_switches_disk_and_increases_quota(): void
    {
        $owner = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $owner->id]);

        $plan = MembershipPlan::factory()->create(['features' => ['addon.managed_storage']]);
        $company->memberships()->create([
            'plan_id' => $plan->id,
            'starts_at' => now(),
            'expires_at' => now()->addMonth(),
            'status' => 'active',
        ]);

        ModuleSetting::create([
            'company_id' => $company->id,
            'module_name' => 'features',
            'settings_json' => ['addon.managed_storage' => true],
        ]);

        app(CompanyContext::class)->setCurrent((string) $company->id);

        $resolver = app(CompanyDiskResolver::class);

        $this->assertEquals('s3-managed', $resolver->getDiskName());
        $this->assertEquals(5 * 1024 * 1024 * 1024, $resolver->getQuotaBytes());

        // Large upload allowed by managed storage but would fail BYOS
        $resolver->authorizeUpload(200 * 1024 * 1024, 100 * 1024 * 1024);

        // Sanity assertion
        $this->assertTrue(true);
    }
}
