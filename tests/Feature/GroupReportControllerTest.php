<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Contracts\CompanySettingsStore;
use App\Contracts\PresetSource;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\MembershipPlan;
use App\Models\ModuleSetting;
use App\Models\User;
use App\Services\Eloquent\EloquentCompanyContext;
use App\Services\Eloquent\EloquentCompanySettingsStore;
use App\Services\Preset\EloquentPresetSource;
use Database\Seeders\BusinessPresetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupReportControllerTest extends TestCase
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

    public function test_owner_can_access_group_report_if_feature_enabled()
    {
        $owner = User::factory()->create();
        $root = Company::factory()->create(['owner_user_id' => $owner->id]);

        ModuleSetting::create([
            'company_id' => $root->id,
            'module_name' => 'features',
            'settings_json' => ['addon.branches' => true],
        ]);

        $plan = MembershipPlan::factory()->create(['features' => ['addon.branches']]);
        CompanyMembership::create([
            'company_id' => $root->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addDays(30),
            'current_token_balance' => 1000,
            'monthly_token_quota' => 1000,
            'emergency_token_quota' => 0,
            'emergency_balance' => 0,
            'max_wa_groups' => 1,
        ]);
        $owner->update(['current_company_id' => $root->id]);

        $response = $this->actingAs($owner)->get('/app/group-report');
        $response->assertStatus(200);
        $response->assertSee('Laporan Gabungan Grup Cabang');
    }

    public function test_non_owner_is_rejected_with_403()
    {
        $owner = User::factory()->create();
        $staff = User::factory()->create();
        $root = Company::factory()->create(['owner_user_id' => $owner->id]);

        ModuleSetting::create([
            'company_id' => $root->id,
            'module_name' => 'features',
            'settings_json' => ['addon.branches' => true],
        ]);

        $staff->update(['current_company_id' => $root->id]);

        $response = $this->actingAs($staff)->get('/app/group-report');
        $response->assertStatus(403);
    }
}
