<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Models\Company;
use App\Models\ModuleSetting;
use App\Models\User;
use App\Providers\DataSourceServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminImpersonationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['datasource.driver' => 'eloquent']);
        (new DataSourceServiceProvider($this->app))->register();
        $this->artisan('db:seed', ['--class' => 'BusinessPresetSeeder']);
    }

    public function test_non_platform_admin_cannot_access_admin_routes()
    {
        $user = User::factory()->create(['is_platform_admin' => false]);

        $response = $this->actingAs($user)->get('/admin');
        $response->assertStatus(403);
    }

    public function test_platform_admin_can_access_admin_dashboard()
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);

        $response = $this->actingAs($admin)->get('/admin');
        $response->assertStatus(200);
        $response->assertSee('Panel Super Admin');
    }

    public function test_admin_impersonation_creates_session_record_and_banner_appears()
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $owner = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $owner->id]);

        $response = $this->actingAs($admin)->post("/admin/impersonate/{$company->id}");
        $response->assertRedirect('/app/dashboard');

        $this->assertDatabaseHas('admin_impersonation_sessions', [
            'admin_user_id' => $admin->id,
            'target_company_id' => $company->id,
        ]);

        $this->assertTrue(session()->has('admin_impersonation_id'));
        $sessionId = session('admin_impersonation_id');
        $this->assertEquals($company->id, $admin->fresh()->current_company_id);

        $sessionId = session('admin_impersonation_id');
        $this->actingAs($admin);
        app(CompanyContext::class)->setCurrent($company->id);
        $response = $this->withSession(['admin_impersonation_id' => $sessionId, 'active_company' => $company->id])->get('/app/dashboard');
        $response->assertSee('Sesi Bantuan Impersonasi');

        // Banner layout app (Lobby) tidak lagi memanggil currentCompany()
        // yang tidak ada di kontrak - nama company dirender aman.
        $lobby = $this->withSession(['admin_impersonation_id' => $sessionId, 'active_company' => $company->id])->get('/app/lobby');
        $lobby->assertOk();
        $lobby->assertSee('mode Bantuan Admin');
        $lobby->assertSee($company->name);
    }

    public function test_impersonated_writes_to_company_are_logged_with_admin_user_id()
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $company = Company::factory()->create();

        $this->actingAs($admin)->post("/admin/impersonate/{$company->id}");

        $company->theme = 'b';
        $company->save();

        $this->assertEquals('admin_impersonation', $company->fresh()->changed_by_type);
        $this->assertEquals($admin->id, $company->fresh()->admin_user_id);

        $setting = ModuleSetting::create([
            'company_id' => $company->id,
            'module_name' => 'test',
            'settings_json' => [],
        ]);

        $this->assertEquals('admin_impersonation', $setting->fresh()->changed_by_type);
        $this->assertEquals($admin->id, $setting->fresh()->admin_user_id);
    }

    public function test_impersonated_admin_cannot_export_client_data()
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $company = Company::factory()->create();

        $this->actingAs($admin)->post("/admin/impersonate/{$company->id}");

        $response = $this->get('/app/settings/export/download');
        $response->assertStatus(403);
    }

    public function test_stopping_impersonation_returns_to_admin_panel_and_deletes_session()
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $company = Company::factory()->create();

        $this->actingAs($admin)->post("/admin/impersonate/{$company->id}");
        $this->assertTrue(session()->has('admin_impersonation_id'));
        $sessionId = session('admin_impersonation_id');

        $response = $this->post('/admin/impersonate/stop');
        $response->assertRedirect('/admin');

        $this->assertFalse(session()->has('admin_impersonation_id'));
        $this->assertDatabaseMissing('admin_impersonation_sessions', ['session_id' => $sessionId]);
        $this->assertNull($admin->fresh()->current_company_id);
    }
}
