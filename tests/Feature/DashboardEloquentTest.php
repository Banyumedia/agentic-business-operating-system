<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Contracts\EntityRepository;
use App\Models\ApprovalTicket;
use App\Models\AssistantReport;
use App\Models\CashEntry;
use App\Models\Company;
use App\Models\User;
use App\Models\WorkflowTransitionLog;
use App\Providers\DataSourceServiceProvider;
use App\Services\Dashboard\DashboardComposer;
use App\Services\Dashboard\WidgetRegistry;
use App\Services\Workflow\ArrayWorkflowRecord;
use App\Services\Workflow\WorkflowEngine;
use Database\Seeders\BusinessPresetSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardEloquentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['datasource.driver' => 'eloquent']);
        (new DataSourceServiceProvider($this->app))->register();

        $this->seed(BusinessPresetSeeder::class);
    }

    public function test_dashboard_is_composed_from_the_active_company_preset_and_eloquent_data(): void
    {
        $user = User::factory()->create();

        $companies = [
            'bengkel-arka' => Company::create(['name' => 'Bengkel', 'slug' => 'bengkel-arka', 'owner_user_id' => $user->id, 'business_preset' => 'bengkel', 'module_settings' => []]),
            'klinik-sehat' => Company::create(['name' => 'Klinik', 'slug' => 'klinik-sehat', 'owner_user_id' => $user->id, 'business_preset' => 'klinik', 'module_settings' => []]),
            'salon-ayu' => Company::create(['name' => 'Salon', 'slug' => 'salon-ayu', 'owner_user_id' => $user->id, 'business_preset' => 'salon', 'module_settings' => []]),
        ];

        foreach ($companies as $c) {
            AssistantReport::create([
                'company_id' => $c->id,
                'generated_at' => now(),
                'period' => 'Hari Ini',
                'summary' => 'Ini adalah laporan AI hari ini.',
                'highlights' => ['Penjualan naik'],
                'recommended_actions' => ['Cek stok'],
            ]);
        }

        $cases = [
            'bengkel-arka' => ['upcoming_schedule', 'low_stock', 'kpi_cashflow', 'pending_approvals'],
            'klinik-sehat' => ['upcoming_schedule', 'deals_pipeline', 'kpi_cashflow'],
            'salon-ayu' => ['upcoming_schedule', 'low_stock', 'kpi_cashflow'],
        ];

        foreach ($cases as $companySlug => $expectedWidgets) {
            $company = $companies[$companySlug];
            app(CompanyContext::class)->setCurrent($company->id);
            $response = $this->actingAs($user)->withSession(['active_company' => $company->id])->get('/app/dashboard');

            $dashboard = app(DashboardComposer::class)->compose();

            $this->assertCount(3, $dashboard['kpis']);
            $this->assertSame($expectedWidgets, array_column($dashboard['widgets'], 'key'));
            $this->assertNotEmpty($dashboard['assistant_report']['summary']);
            $this->assertNotEmpty($dashboard['assistant_report']['generated_at']);

            $response
                ->assertOk()
                ->assertSee('Ringkasan hari ini')
                ->assertSee('Laporan AI')
                ->assertSee($dashboard['assistant_report']['summary']);
        }
    }

    public function test_cashflow_kpi_and_widget_exclude_another_companys_money(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create([
            'owner_user_id' => $user->id,
            'business_preset' => 'bengkel',
        ]);
        $otherCompany = Company::factory()->create([
            'owner_user_id' => $user->id,
            'business_preset' => 'bengkel',
        ]);

        CashEntry::factory()->create(['company_id' => $company->id, 'direction' => 'in', 'amount' => 100000]);
        CashEntry::factory()->create(['company_id' => $company->id, 'direction' => 'out', 'amount' => 30000]);
        CashEntry::factory()->create(['company_id' => $otherCompany->id, 'direction' => 'in', 'amount' => 999999]);

        app(CompanyContext::class)->setCurrent($company->id);

        $dashboard = app(DashboardComposer::class)->compose();
        $widget = app(WidgetRegistry::class)->compose('kpi_cashflow');

        $this->assertSame('Rp 70.000', $dashboard['kpis'][0]['value']);
        $this->assertSame('Rp 70.000', $widget['value']);
        $this->assertStringNotContainsString('999.999', $widget['meta']);
    }

    public function test_schedule_widget_uses_the_recorded_offset_and_hides_past_agenda_with_eloquent(): void
    {
        $user = User::factory()->create();
        $company = Company::create([
            'name' => 'Salon',
            'slug' => 'salon-ayu',
            'owner_user_id' => $user->id,
            'business_preset' => 'salon',
            'module_settings' => [],
        ]);

        $this->travelTo('2026-09-17T06:00:00+07:00');

        app(CompanyContext::class)->setCurrent($company->id);

        $repository = app(EntityRepository::class)->for($company->id, 'bookings');
        // Because there is a foreign key on resources, we should create a resource first if the test fails.
        // Wait, EloquentEntityRepository::save will try to create a Booking which might need resource_id to exist
        // Let's create a resource first.
        $resourceRepo = app(EntityRepository::class)->for($company->id, 'resources');
        $resource = $resourceRepo->save(['name' => 'Kapster', 'type' => 'staff']);

        $repository->save(['resource_id' => $resource['id'], 'starts_at' => '2026-09-16T09:00:00+07:00', 'ends_at' => '2026-09-16T10:00:00+07:00', 'status' => 'confirmed']);
        $repository->save(['resource_id' => $resource['id'], 'starts_at' => '2026-09-17T08:00:00+07:00', 'ends_at' => '2026-09-17T09:00:00+07:00', 'status' => 'confirmed']);

        $widget = app(WidgetRegistry::class)->compose('upcoming_schedule');

        $this->assertSame('1', $widget['value']);
        $this->assertCount(1, $widget['items']);

        $this->assertStringContainsString('08:00', $widget['items'][0]['secondary']);
        $this->assertStringNotContainsString('01:00', $widget['items'][0]['secondary']);
    }

    public function test_pending_approvals_widget_is_tenant_scoped_and_fail_closed(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create(['owner_user_id' => $user->id, 'business_preset' => 'bengkel']);
        $otherCompany = Company::factory()->create(['owner_user_id' => $user->id, 'business_preset' => 'bengkel']);
        app(CompanyContext::class)->setCurrent($company->id);

        ApprovalTicket::create(['company_id' => $company->id, 'code' => '111111', 'action_type' => 'payment.release', 'payload' => [], 'status' => 'pending', 'expires_at' => now()->addHour()]);
        ApprovalTicket::create(['company_id' => $company->id, 'code' => '222222', 'action_type' => 'payment.release', 'payload' => [], 'status' => 'consumed', 'expires_at' => now()->addHour()]);
        ApprovalTicket::create(['company_id' => $company->id, 'code' => '333333', 'action_type' => 'payment.release', 'payload' => [], 'status' => 'pending', 'expires_at' => now()->subMinute()]);
        ApprovalTicket::create(['company_id' => $otherCompany->id, 'code' => '444444', 'action_type' => 'payment.release', 'payload' => [], 'status' => 'pending', 'expires_at' => now()->addHour()]);

        $widget = app(WidgetRegistry::class)->compose('pending_approvals');

        $this->assertSame('1', $widget['value']);
        $this->assertCount(1, $widget['items']);
    }

    public function test_eloquent_workflow_approval_is_atomic_idempotent_and_expires(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create([
            'owner_user_id' => $user->id,
            'business_preset' => 'bengkel',
        ]);
        $this->actingAs($user);
        app(CompanyContext::class)->setCurrent($company->id);

        $record = new ArrayWorkflowRecord((string) $company->id, 'orders', [
            'id' => 88,
            'stage' => 'pengerjaan',
        ]);
        $engine = app(WorkflowEngine::class);
        $first = $engine->transition($record, 'dibatalkan', 'owner', 'Dibatalkan owner');
        $retry = $engine->transition($record, 'dibatalkan', 'owner', 'Dibatalkan owner');

        $this->assertSame($first['effects'][0]['operation_id'], $retry['effects'][0]['operation_id']);
        $this->assertSame($first['effects'][0]['ticket_id'], $retry['effects'][0]['ticket_id']);
        $this->assertDatabaseCount('approval_tickets', 1);
        $this->assertDatabaseCount('workflow_transitions_log', 1);
        $this->assertSame('pending', ApprovalTicket::sole()->status);
        $this->assertSame(
            ApprovalTicket::sole()->id,
            WorkflowTransitionLog::sole()->approval_ticket_id,
        );

        ApprovalTicket::sole()->update(['expires_at' => now()->subMinute()]);
        $nextAttempt = $engine->transition($record, 'dibatalkan', 'owner', 'Dibatalkan owner');

        $this->assertNotSame($first['effects'][0]['operation_id'], $nextAttempt['effects'][0]['operation_id']);
        $this->assertDatabaseCount('approval_tickets', 2);
        $this->assertDatabaseCount('workflow_transitions_log', 2);
        $this->assertSame(['expired', 'pending'], ApprovalTicket::query()->orderBy('id')->pluck('status')->all());
    }

    public function test_eloquent_approval_rolls_back_ticket_when_audit_insert_fails(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create([
            'owner_user_id' => $user->id,
            'business_preset' => 'bengkel',
        ]);
        $this->actingAs($user);
        app(CompanyContext::class)->setCurrent($company->id);
        DB::statement("CREATE TRIGGER reject_workflow_audit BEFORE INSERT ON workflow_transitions_log BEGIN SELECT RAISE(FAIL, 'forced audit failure'); END");

        try {
            app(WorkflowEngine::class)->transition(
                new ArrayWorkflowRecord((string) $company->id, 'orders', ['id' => 89, 'stage' => 'pengerjaan']),
                'dibatalkan',
                'owner',
            );
            $this->fail('Kegagalan audit harus menggagalkan approval Eloquent.');
        } catch (QueryException) {
            $this->assertDatabaseCount('approval_tickets', 0);
            $this->assertDatabaseCount('workflow_transitions_log', 0);
        } finally {
            DB::statement('DROP TRIGGER IF EXISTS reject_workflow_audit');
        }
    }
}
