<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Contracts\EntityRepository;
use App\Livewire\Dashboard;
use App\Services\Dashboard\DashboardComposer;
use App\Services\Dashboard\WidgetRegistry;
use App\Services\Workflow\ArrayWorkflowRecord;
use App\Services\Workflow\Effects\ApprovalRequest;
use App\Services\Workflow\JsonWorkflowLog;
use App\Services\Workflow\WorkflowEngine;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    public function test_dashboard_is_composed_from_the_active_company_preset_and_json_data(): void
    {
        $cases = [
            'bengkel-arka' => ['upcoming_schedule', 'low_stock', 'kpi_cashflow', 'pending_approvals'],
            'klinik-sehat' => ['upcoming_schedule', 'deals_pipeline', 'kpi_cashflow'],
            'salon-ayu' => ['upcoming_schedule', 'low_stock', 'kpi_cashflow'],
        ];

        foreach ($cases as $company => $expectedWidgets) {
            $response = $this->get('/app/dashboard?company='.$company);
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

    public function test_dashboard_shows_a_recoverable_error_when_a_json_source_cannot_be_loaded(): void
    {
        session(['active_company' => 'bengkel-arka']);
        $this->mock(DashboardComposer::class, function (MockInterface $mock): void {
            $mock->shouldReceive('compose')->once()->andThrow(new RuntimeException('broken source'));
        });

        Livewire::test(Dashboard::class)
            ->assertSee('Dashboard belum dapat dimuat')
            ->assertSee('Coba lagi');
    }

    public function test_schedule_widget_uses_the_recorded_offset_and_hides_past_agenda(): void
    {
        $jsonPath = storage_path('framework/testing/dash-'.bin2hex(random_bytes(5)));
        config(['datasource.json_path' => $jsonPath]);
        $this->travelTo('2026-09-17T06:00:00+07:00');

        app(CompanyContext::class)->setCurrent('salon-ayu');
        $repository = app(EntityRepository::class)->for('salon-ayu', 'bookings');
        $repository->save(['id' => 1, 'resource_id' => 1, 'starts_at' => '2026-09-16T09:00:00+07:00', 'ends_at' => '2026-09-16T10:00:00+07:00']);
        $repository->save(['id' => 2, 'resource_id' => 1, 'starts_at' => '2026-09-17T08:00:00+07:00', 'ends_at' => '2026-09-17T09:00:00+07:00']);

        $widget = app(WidgetRegistry::class)->compose('upcoming_schedule');

        // Agenda kemarin tidak ikut dihitung pada kartu "mendatang".
        $this->assertSame('1', $widget['value']);
        $this->assertCount(1, $widget['items']);

        // 08:00+07:00 harus terbaca 08:00, bukan 01:00 (timezone server UTC).
        $this->assertStringContainsString('08:00', $widget['items'][0]['secondary']);
        $this->assertStringNotContainsString('01:00', $widget['items'][0]['secondary']);

        (new Filesystem)->deleteDirectory($jsonPath);
    }

    public function test_pending_approvals_widget_reads_only_active_unexpired_tickets(): void
    {
        $jsonPath = storage_path('framework/testing/approvals-'.bin2hex(random_bytes(5)));
        config(['datasource.json_path' => $jsonPath]);
        $this->travelTo('2026-09-17T06:00:00+07:00');

        app(CompanyContext::class)->setCurrent('bengkel-arka');
        $repository = app(EntityRepository::class)->for('bengkel-arka', 'approval_tickets');
        $repository->save(['id' => 1, 'code' => '111111', 'action_type' => 'payment.release', 'payload' => [], 'status' => 'pending', 'expires_at' => now()->addHour()->toIso8601String()]);
        $repository->save(['id' => 2, 'code' => '222222', 'action_type' => 'payment.release', 'payload' => [], 'status' => 'consumed', 'expires_at' => now()->addHour()->toIso8601String()]);
        $repository->save(['id' => 3, 'code' => '333333', 'action_type' => 'payment.release', 'payload' => [], 'status' => 'pending', 'expires_at' => now()->subMinute()->toIso8601String()]);

        $widget = app(WidgetRegistry::class)->compose('pending_approvals');

        $this->assertSame('1', $widget['value']);
        $this->assertCount(1, $widget['items']);
        $this->assertSame('payment.release', $widget['items'][0]['primary']);

        (new Filesystem)->deleteDirectory($jsonPath);
    }

    public function test_json_workflow_approval_is_persisted_and_visible_in_widget(): void
    {
        $jsonPath = storage_path('framework/testing/workflow-approval-'.bin2hex(random_bytes(5)));
        config(['datasource.json_path' => $jsonPath]);
        Storage::fake('company-json');
        Storage::disk('company-json')->put('json/bengkel-arka/settings.json', json_encode(['preset' => 'bengkel'], JSON_THROW_ON_ERROR));
        app(CompanyContext::class)->setCurrent('bengkel-arka');

        $record = new ArrayWorkflowRecord('bengkel-arka', 'orders', ['id' => 15, 'stage' => 'pengerjaan']);
        $result = app(WorkflowEngine::class)->transition($record, 'dibatalkan', 'owner');
        $widget = app(WidgetRegistry::class)->compose('pending_approvals');

        $this->assertSame('pending_approval', $result['status']);
        $this->assertNotEmpty($result['effects'][0]['ticket_id']);
        $this->assertSame('1', $widget['value']);
        $this->assertSame('workflow.transition', $widget['items'][0]['primary']);

        (new Filesystem)->deleteDirectory($jsonPath);
    }

    public function test_json_approval_retains_invisible_prepare_when_log_is_corrupt_and_retry_reuses_it(): void
    {
        $jsonPath = storage_path('framework/testing/dashboard-approval-rollback-'.bin2hex(random_bytes(4)));
        config()->set('datasource.json_path', $jsonPath);
        Storage::fake('company-json');
        Storage::disk('company-json')->put('json/bengkel-arka/settings.json', json_encode(['preset' => 'bengkel'], JSON_THROW_ON_ERROR));
        Storage::disk('company-json')->put('json/bengkel-arka/workflow_log.json', '{corrupt');
        app(CompanyContext::class)->setCurrent('bengkel-arka');

        $record = new ArrayWorkflowRecord('bengkel-arka', 'orders', [
            'id' => 702,
            'stage' => 'pengerjaan',
        ]);
        $engine = app(WorkflowEngine::class);

        try {
            $engine->transition($record, 'dibatalkan', 'owner', 'Pelanggan membatalkan');
            $this->fail('Workflow dengan audit log korup seharusnya ditolak.');
        } catch (\JsonException) {
            $prepared = app(EntityRepository::class)->for('bengkel-arka', 'approval_tickets')->all();
            $this->assertCount(1, $prepared);
            $this->assertSame('prepared', $prepared[0]['status']);
            $this->assertSame('0', app(WidgetRegistry::class)->compose('pending_approvals')['value']);
        }

        Storage::disk('company-json')->put('json/bengkel-arka/workflow_log.json', '[]');
        $result = $engine->transition($record, 'dibatalkan', 'owner', 'Pelanggan membatalkan');
        $tickets = app(EntityRepository::class)->for('bengkel-arka', 'approval_tickets')->all();
        $log = json_decode(
            Storage::disk('company-json')->get('json/bengkel-arka/workflow_log.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertSame('pending_approval', $result['status']);
        $this->assertCount(1, $tickets);
        $this->assertCount(1, $log);
        $this->assertSame((string) $tickets[0]['id'], $log[0]['effects'][0]['ticket_id']);

        (new Filesystem)->deleteDirectory($jsonPath);
    }

    public function test_json_approval_pre_log_retry_does_not_reuse_different_audit_metadata(): void
    {
        $jsonPath = storage_path('framework/testing/dashboard-approval-attribution-'.bin2hex(random_bytes(4)));
        config()->set('datasource.json_path', $jsonPath);
        app(CompanyContext::class)->setCurrent('bengkel-arka');

        $context = [
            'company' => 'bengkel-arka',
            'preset' => 'bengkel',
            'entity' => 'orders',
            'record_id' => 704,
            'from' => 'pengerjaan',
            'to' => 'dibatalkan',
            'actor_role' => 'owner',
            'note' => 'Permintaan pertama',
        ];
        $effect = app(ApprovalRequest::class);
        $first = $effect->execute($context);
        $second = $effect->execute([
            ...$context,
            'actor_role' => 'staff',
            'note' => 'Permintaan kedua',
        ]);
        $tickets = app(EntityRepository::class)->for('bengkel-arka', 'approval_tickets')->all();

        $payloads = array_column($tickets, 'payload');

        $this->assertNotSame($first['operation_id'], $second['operation_id']);
        $this->assertNotSame($first['ticket_id'], $second['ticket_id']);
        $this->assertCount(2, $tickets);
        $this->assertSame(['owner', 'staff'], array_column($payloads, 'actor_role'));
        $this->assertSame(['Permintaan pertama', 'Permintaan kedua'], array_column($payloads, 'note'));

        (new Filesystem)->deleteDirectory($jsonPath);
    }

    public function test_json_approval_retry_recovers_prepare_and_post_log_crash_windows_idempotently(): void
    {
        $jsonPath = storage_path('framework/testing/dashboard-approval-idempotency-'.bin2hex(random_bytes(4)));
        config()->set('datasource.json_path', $jsonPath);
        Storage::fake('company-json');
        Storage::disk('company-json')->put('json/bengkel-arka/settings.json', json_encode(['preset' => 'bengkel'], JSON_THROW_ON_ERROR));
        app(CompanyContext::class)->setCurrent('bengkel-arka');

        $context = [
            'company' => 'bengkel-arka',
            'preset' => 'bengkel',
            'entity' => 'orders',
            'record_id' => 703,
            'from' => 'pengerjaan',
            'to' => 'dibatalkan',
            'actor_role' => 'owner',
            'note' => null,
        ];
        $effect = app(ApprovalRequest::class)->execute($context);
        $repository = app(EntityRepository::class)->for('bengkel-arka', 'approval_tickets');

        $this->assertSame('prepared', $repository->all()[0]['status']);
        $this->assertSame('0', app(WidgetRegistry::class)->compose('pending_approvals')['value']);

        app(JsonWorkflowLog::class)->append('bengkel-arka', [
            'event' => 'approval_requested',
            ...$context,
            'effects' => [$effect],
            'occurred_at' => now()->subSecond()->toIso8601String(),
        ]);

        try {
            app(JsonWorkflowLog::class)->append('bengkel-arka', [
                'event' => 'approval_requested',
                ...$context,
                'actor_role' => 'staff',
                'effects' => [$effect],
                'occurred_at' => now()->toIso8601String(),
            ]);
            $this->fail('Operation ID dengan metadata audit berbeda seharusnya ditolak.');
        } catch (\LogicException) {
            $this->assertTrue(true);
        }

        $record = new ArrayWorkflowRecord('bengkel-arka', 'orders', ['id' => 703, 'stage' => 'pengerjaan']);
        app(WorkflowEngine::class)->transition($record, 'dibatalkan', 'owner');

        $tickets = $repository->all();
        $log = json_decode(Storage::disk('company-json')->get('json/bengkel-arka/workflow_log.json'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertCount(1, $tickets);
        $this->assertSame('pending', $tickets[0]['status']);
        $this->assertCount(1, $log);
        $this->assertSame((string) $tickets[0]['id'], $log[0]['effects'][0]['ticket_id']);
        $this->assertSame('1', app(WidgetRegistry::class)->compose('pending_approvals')['value']);

        $repository->save([...$tickets[0], 'status' => 'rejected']);
        $approvalRequest = app(ApprovalRequest::class);
        $nextAttempt = $approvalRequest->execute($context);
        $this->assertNotSame($effect['operation_id'], $nextAttempt['operation_id']);
        $this->assertCount(2, $repository->all());
        $this->assertSame('prepared', $repository->find((int) $nextAttempt['ticket_id'])['status']);

        $this->travel(25)->hours();
        $afterPreparedExpiry = $approvalRequest->execute($context);
        $this->assertSame('expired', $repository->find((int) $nextAttempt['ticket_id'])['status']);
        $this->assertNotSame($nextAttempt['operation_id'], $afterPreparedExpiry['operation_id']);

        $approvalRequest->commit($context, $afterPreparedExpiry);
        $this->travel(25)->hours();
        $afterPendingExpiry = $approvalRequest->execute($context);
        $this->assertSame('expired', $repository->find((int) $afterPreparedExpiry['ticket_id'])['status']);
        $this->assertNotSame($afterPreparedExpiry['operation_id'], $afterPendingExpiry['operation_id']);
        $this->assertSame('prepared', $repository->find((int) $afterPendingExpiry['ticket_id'])['status']);

        (new Filesystem)->deleteDirectory($jsonPath);
    }

    public function test_dashboard_source_has_no_industry_named_branch_or_static_business_numbers(): void
    {
        $source = implode("\n", [
            file_get_contents(app_path('Services/Dashboard/DashboardComposer.php')),
            file_get_contents(app_path('Services/Dashboard/WidgetRegistry.php')),
            file_get_contents(resource_path('views/livewire/dashboard.blade.php')),
        ]);

        $this->assertDoesNotMatchRegularExpression('/\b(?:klinik|salon|bengkel|laundry|kontraktor)\b/i', $source);
        $this->assertDoesNotMatchRegularExpression('/>\s*(?:1[,.]204|45|12)\s*</', $source);
        $this->assertStringNotContainsString('DB::', $source);
    }
}
