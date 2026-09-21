<?php

namespace Tests\Feature\Admin;

use App\Models\ActivityLog;
use App\Models\AdminImpersonationSession;
use App\Models\Company;
use App\Models\HermesNode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExtendedSuperAdminModulesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $nonAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'is_platform_admin' => true,
        ]);

        $this->nonAdmin = User::factory()->create([
            'is_platform_admin' => false,
        ]);
    }

    public function test_non_admin_cannot_access_extended_admin_routes(): void
    {
        $this->actingAs($this->nonAdmin);

        $this->get(route('admin.hermes-nodes'))->assertForbidden();
        $this->get(route('admin.impersonation-logs'))->assertForbidden();
        $this->get(route('admin.client-logs'))->assertForbidden();
    }

    public function test_admin_can_access_extended_admin_routes(): void
    {
        $this->actingAs($this->admin);

        $this->get(route('admin.hermes-nodes'))->assertOk();
        $this->get(route('admin.impersonation-logs'))->assertOk();
        $this->get(route('admin.client-logs'))->assertOk();
    }

    public function test_hermes_nodes_renders_correctly(): void
    {
        $node = HermesNode::create([
            'name' => 'Cluster-Alpha-1',
            'api_url' => 'http://127.0.0.1:8000',
            'api_secret_reference' => 'secret-1',
            'max_capacity' => 50,
            'active_profiles' => 5,
            'status' => 'active',
        ]);

        $this->actingAs($this->admin);

        $response = $this->get(route('admin.hermes-nodes'));
        $response->assertOk();
        $response->assertSee('Cluster-Alpha-1');
        $response->assertSee('5 / 50');
    }

    public function test_impersonation_logs_renders_correctly(): void
    {
        $company = Company::factory()->create(['name' => 'Bengkel Mobil Maju']);

        AdminImpersonationSession::create([
            'admin_user_id' => $this->admin->id,
            'target_company_id' => $company->id,
            'session_id' => 'test-session-uuid',
            'ip_address' => '192.168.1.100',
        ]);

        $this->actingAs($this->admin);

        $response = $this->get(route('admin.impersonation-logs'));
        $response->assertOk();
        $response->assertSee('Bengkel Mobil Maju');
        $response->assertSee('192.168.1.100');
    }

    public function test_client_logs_renders_correctly(): void
    {
        $company = Company::factory()->create(['name' => 'Klinik Sehat']);

        ActivityLog::create([
            'company_id' => $company->id,
            'subject_type' => 'Contact',
            'subject_id' => 1,
            'type' => 'created',
            'content' => 'Pendaftaran pasien baru',
            'user_id' => $this->admin->id,
        ]);

        $this->actingAs($this->admin);

        $response = $this->get(route('admin.client-logs'));
        $response->assertOk();
        $response->assertSee('Klinik Sehat');
        $response->assertSee('Pendaftaran pasien baru');
    }
}
