<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Contracts\CompanySettingsStore;
use App\Contracts\PresetSource;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Contact;
use App\Models\MembershipPlan;
use App\Models\ModuleSetting;
use App\Models\User;
use App\Services\CompanyGroup\GroupReportService;
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

    public function test_group_report_excludes_unrelated_companies_in_owner_scoped_aggregate()
    {
        // Owner-only + scoping grup (root+branch) dijaga service; regresi ini
        // membuktikan lewat render controller: company tak terkait tidak
        // menambah angka meski dibuat bersamaan.
        $owner = User::factory()->create();
        $root = Company::factory()->create(['owner_user_id' => $owner->id]);
        $branch = Company::factory()->create(['owner_user_id' => $owner->id, 'parent_company_id' => $root->id]);
        $unrelated = Company::factory()->create(['owner_user_id' => $owner->id, 'name' => 'Usaha Lain']);

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

        Contact::factory()->count(2)->create(['company_id' => $root->id]);
        Contact::factory()->count(3)->create(['company_id' => $unrelated->id]);

        $owner->update(['current_company_id' => $root->id]);

        $response = $this->actingAs($owner)->get('/app/group-report');
        $response->assertOk();
        $response->assertSee('Total Kontak');
        $response->assertDontSee($unrelated->name);

        // Angka agregat hanya root (+branch kosong) = 2, bukan 5.
        $aggregate = (new GroupReportService)->getAggregate($root->fresh());
        $this->assertSame(2, $aggregate['total_contacts']);
    }

    public function test_group_report_view_uses_semantic_dl_and_named_dashboard_route()
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

        $html = $this->actingAs($owner)->get('/app/group-report')->assertOk()->getContent();

        // Representasi responsif & semantik: <dl>/<dt>/<dd>, bukan kartu berisi heading kosong makna.
        $this->assertStringContainsString('<dl', $html);
        $this->assertStringContainsString('<dt', $html);
        $this->assertStringContainsString('<dd', $html);
        // Tautan kembali ke rute bernama, bukan literal /app.
        $this->assertStringContainsString(route('app.dashboard'), $html);
        $this->assertStringNotContainsString('href="/app"', $html);
    }
}
