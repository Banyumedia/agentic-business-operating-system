<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Models\AdminImpersonationSession;
use App\Models\Company;
use App\Models\User;
use App\Providers\DataSourceServiceProvider;
use Database\Seeders\BusinessPresetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * MQ-01C6 regression tests for independent QA findings F1-F5.
 */
class ImpersonationExpiryFailClosedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['datasource.driver' => 'eloquent']);
        (new DataSourceServiceProvider($this->app))->register();

        $this->seed(BusinessPresetSeeder::class);
    }

    public function test_expired_impersonation_session_is_rejected_by_company_context(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $owner = User::factory()->create();
        $company = Company::create([
            'name' => 'Kedai Expired',
            'slug' => 'kedai-expired',
            'business_preset' => 'rental',
            'owner_user_id' => $owner->id,
        ]);

        $session = AdminImpersonationSession::create([
            'admin_user_id' => $admin->id,
            'target_company_id' => $company->id,
            'session_id' => 'expired-session-id',
            'expires_at' => now()->subHour(),
        ]);

        $this->actingAs($admin);
        session(['admin_impersonation_id' => 'expired-session-id']);

        // F2: expired session must fail closed - admin no longer gets access.
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Akses lintas company ditolak');
        app(CompanyContext::class)->setCurrent((string) $company->id);
    }

    public function test_valid_impersonation_session_still_grants_access(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $owner = User::factory()->create();
        $company = Company::create([
            'name' => 'Kedai Aktif',
            'slug' => 'kedai-aktif',
            'business_preset' => 'rental',
            'owner_user_id' => $owner->id,
        ]);

        AdminImpersonationSession::create([
            'admin_user_id' => $admin->id,
            'target_company_id' => $company->id,
            'session_id' => 'live-session-id',
            'expires_at' => now()->addHour(),
        ]);

        $this->actingAs($admin);
        session(['admin_impersonation_id' => 'live-session-id']);

        $context = app(CompanyContext::class);
        $context->setCurrent((string) $company->id);

        $this->assertSame((string) $company->id, $context->current());
    }

    public function test_expired_impersonation_session_is_rejected_by_http_middleware(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $owner = User::factory()->create();
        $company = Company::create([
            'name' => 'Warung Middleware',
            'slug' => 'warung-middleware',
            'business_preset' => 'rental',
            'owner_user_id' => $owner->id,
        ]);

        AdminImpersonationSession::create([
            'admin_user_id' => $admin->id,
            'target_company_id' => $company->id,
            'session_id' => 'expired-mw-session',
            'expires_at' => now()->subHour(),
        ]);

        $response = $this->actingAs($admin)
            ->withSession([
                'admin_impersonation_id' => 'expired-mw-session',
                'active_company' => $company->id,
            ])
            ->get('/app/dashboard');

        $response->assertStatus(403);
    }
}
