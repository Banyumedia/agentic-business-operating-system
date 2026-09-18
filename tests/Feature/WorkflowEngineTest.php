<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Contracts\HasWorkflow;
use App\Models\Company;
use App\Models\User;
use App\Services\HermesNodeClient;
use App\Services\Workflow\ArrayWorkflowRecord;
use App\Services\Workflow\JsonWorkflowLog;
use App\Services\Workflow\WorkflowEngine;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use LogicException;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class WorkflowEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['datasource.driver' => 'eloquent']);

        Storage::fake('company-json');
        Storage::disk('company-json')->put('json/bengkel-arka/settings.json', json_encode([
            'preset' => 'bengkel',
        ], JSON_THROW_ON_ERROR));
        app(CompanyContext::class)->setCurrent('bengkel-arka');

        $user = User::factory()->create([
            'wa_number' => '628123456789',
            'wa_is_verified' => true,
        ]);
        // Allow arbitrary strings for 'id' to be consistent with demo configs
        Company::factory()->create([
            'id' => '9999',
            'slug' => 'bengkel-arka',
            'owner_user_id' => $user->id,
        ]);

        // Setup context to use the slug for compatibility
        $mockContext = Mockery::mock(CompanyContext::class);
        $mockContext->shouldReceive('current')->andReturn('9999');
        $mockContext->shouldReceive('preset')->andReturn('bengkel');
        $this->app->instance(CompanyContext::class, $mockContext);
    }

    public function test_declared_forward_and_jump_transitions_change_in_memory_stage_and_are_logged(): void
    {
        $engine = app(WorkflowEngine::class);

        $forward = new WorkflowRecord('9999', 'orders', 10, 'masuk');
        $forwardResult = $engine->transition($forward, 'pemeriksaan', 'staff');
        $this->assertSame('pemeriksaan', $forward->workflowStage());
        $this->assertSame('transitioned', $forwardResult['status']);

        $jump = new WorkflowRecord('9999', 'orders', 11, 'masuk');
        $jumpResult = $engine->transition($jump, 'pengerjaan', 'staff');
        $this->assertSame('pengerjaan', $jump->workflowStage());
        $this->assertSame('transitioned', $jumpResult['status']);

        $log = $this->workflowLog();
        $this->assertCount(2, $log);
        $this->assertSame(['pemeriksaan', 'pengerjaan'], array_column($log, 'to'));
    }

    public function test_undefined_transition_is_rejected_without_state_or_log_change(): void
    {
        $record = new WorkflowRecord('9999', 'orders', 12, 'masuk');

        try {
            app(WorkflowEngine::class)->transition($record, 'selesai', 'owner');
            $this->fail('Transisi yang tidak dideklarasikan harus ditolak.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('tidak dideklarasikan', $exception->getMessage());
        }

        $this->assertSame('masuk', $record->workflowStage());
        $this->assertSame([], $this->workflowLog());
    }

    public function test_role_outside_transition_is_forbidden_without_state_or_log_change(): void
    {
        $record = new WorkflowRecord('9999', 'orders', 13, 'masuk');

        try {
            app(WorkflowEngine::class)->transition($record, 'pemeriksaan', 'system');
            $this->fail('Role yang tidak diizinkan harus ditolak.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }

        $this->assertSame('masuk', $record->workflowStage());
        $this->assertSame([], $this->workflowLog());
    }

    public function test_backward_transition_requires_a_non_empty_note_and_records_it(): void
    {
        $record = new WorkflowRecord('9999', 'orders', 14, 'qc');

        foreach ([null, '   '] as $note) {
            try {
                app(WorkflowEngine::class)->transition($record, 'pengerjaan', 'staff', $note);
                $this->fail('Transisi mundur tanpa alasan harus ditolak.');
            } catch (InvalidArgumentException $exception) {
                $this->assertStringContainsString('catatan alasan', $exception->getMessage());
            }
        }

        $result = app(WorkflowEngine::class)->transition($record, 'pengerjaan', 'staff', 'Ulangi pemeriksaan');

        $this->assertSame('transitioned', $result['status']);
        $this->assertSame('pengerjaan', $record->workflowStage());
        $this->assertSame('Ulangi pemeriksaan', $this->workflowLog()[0]['note']);
    }

    public function test_approval_transition_is_held_and_creates_only_an_approval_request(): void
    {
        $record = new WorkflowRecord('9999', 'orders', 15, 'pengerjaan');

        $result = app(WorkflowEngine::class)->transition($record, 'dibatalkan', 'owner');

        $this->assertSame('pending_approval', $result['status']);
        $this->assertSame('pengerjaan', $record->workflowStage());
        $this->assertSame('approval.request', $result['effects'][0]['effect']);
        $this->assertNotEmpty($result['effects'][0]['ticket_id']);
        $this->assertSame('approval_requested', $this->workflowLog()[0]['event']);
        $this->assertDatabaseHas('approval_tickets', [
            'action_type' => 'workflow.transition',
            'subject_id' => '15',
            'status' => 'pending',
            'company_id' => '9999',
        ]);
    }

    public function test_notify_owner_wa_effect_delivers_successfully_and_is_logged(): void
    {
        $mockClient = Mockery::mock(HermesNodeClient::class);
        $mockClient->shouldReceive('sendWhatsAppMessage')
            ->once()
            ->with('9999', '628123456789', Mockery::pattern('/Transisi workflow: Entity orders.*selesai/'));
        $this->app->instance(HermesNodeClient::class, $mockClient);

        $record = new WorkflowRecord('9999', 'orders', 16, 'siap_diambil');

        $result = app(WorkflowEngine::class)->transition($record, 'selesai', 'staff');

        $this->assertSame('selesai', $record->workflowStage());
        $this->assertSame([
            'effect' => 'notify.owner_wa',
            'status' => 'sent',
            'wa_number' => '628123456789',
        ], $result['effects'][0]);
        $this->assertSame($result['effects'], $this->workflowLog()[0]['effects']);
    }

    public function test_notify_owner_wa_effect_fails_closed_when_owner_not_verified(): void
    {
        User::first()->update(['wa_is_verified' => false]);

        $record = new WorkflowRecord('9999', 'orders', 16, 'siap_diambil');

        try {
            app(WorkflowEngine::class)->transition($record, 'selesai', 'staff');
            $this->fail('Transition should fail when WA is not verified');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Owner WA number is not verified', $e->getMessage());
        }

        $this->assertSame('siap_diambil', $record->workflowStage(), 'In-memory state should be rolled back');
        $this->assertCount(0, $this->workflowLog(), 'Log should not be appended on failure');
        $this->assertDatabaseMissing('workflow_transitions_log', [
            'entity_id' => '16',
        ]);
    }

    public function test_notify_owner_wa_effect_fails_closed_when_delivery_fails(): void
    {
        $mockClient = Mockery::mock(HermesNodeClient::class);
        $mockClient->shouldReceive('sendWhatsAppMessage')
            ->once()
            ->andThrow(new RuntimeException('Hermes API Down'));
        $this->app->instance(HermesNodeClient::class, $mockClient);

        $record = new WorkflowRecord('9999', 'orders', 16, 'siap_diambil');

        try {
            app(WorkflowEngine::class)->transition($record, 'selesai', 'staff');
            $this->fail('Transition should fail when WA delivery fails');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('WA Delivery failed', $e->getMessage());
        }

        $this->assertSame('siap_diambil', $record->workflowStage(), 'In-memory state should be rolled back');
        $this->assertCount(0, $this->workflowLog(), 'Log should not be appended on failure');
        $this->assertDatabaseMissing('workflow_transitions_log', [
            'entity_id' => '16',
        ]);
    }

    public function test_cross_company_record_and_unknown_entity_fail_closed(): void
    {
        $engine = app(WorkflowEngine::class);

        foreach ([
            new WorkflowRecord('8888', 'orders', 17, 'masuk'),
            new WorkflowRecord('9999', 'unknown', 18, 'masuk'),
        ] as $record) {
            try {
                $engine->transition($record, 'pemeriksaan', 'owner');
                $this->fail('Record di luar scope workflow harus ditolak.');
            } catch (InvalidArgumentException|LogicException) {
                $this->assertSame('masuk', $record->workflowStage());
            }
        }

        $this->assertSame([], $this->workflowLog());
    }

    public function test_available_transitions_and_stages_are_driven_by_the_active_preset(): void
    {
        $engine = app(WorkflowEngine::class);
        $record = new WorkflowRecord('9999', 'orders', 19, 'masuk');

        $this->assertSame(
            ['masuk', 'pemeriksaan', 'pengerjaan', 'qc', 'siap_diambil', 'selesai', 'dibatalkan'],
            array_column($engine->stages('orders'), 'code'),
        );
        $this->assertSame(
            ['pemeriksaan', 'pengerjaan'],
            array_column($engine->availableTransitions($record, 'staff'), 'to'),
        );
        $this->assertSame(
            ['pemeriksaan', 'pengerjaan', 'dibatalkan'],
            array_column($engine->availableTransitions($record, 'owner'), 'to'),
        );
        $this->assertSame([], $engine->availableTransitions(
            new WorkflowRecord('9999', 'orders', 20, 'selesai'),
            'owner',
        ));
    }

    public function test_array_record_bridges_repository_rows_without_losing_other_fields(): void
    {
        $record = new ArrayWorkflowRecord('9999', 'orders', [
            'id' => 21,
            'stage' => 'masuk',
            'order_no' => 'WO-21',
        ]);

        app(WorkflowEngine::class)->transition($record, 'pengerjaan', 'staff');

        $this->assertSame([
            'id' => 21,
            'stage' => 'pengerjaan',
            'order_no' => 'WO-21',
        ], $record->toArray());
    }

    public function test_malformed_log_fails_closed_and_rolls_back_in_memory_stage(): void
    {
        Storage::disk('company-json')->put('json/9999/workflow_log.json', '{corrupt');
        $record = new WorkflowRecord('9999', 'orders', 22, 'masuk');

        try {
            app(WorkflowEngine::class)->transition($record, 'pemeriksaan', 'staff');
            $this->fail('Log rusak harus menahan transisi.');
        } catch (\JsonException) {
            $this->assertSame('masuk', $record->workflowStage());
            $this->assertSame('{corrupt', Storage::disk('company-json')->get('json/9999/workflow_log.json'));
        }
    }

    public function test_log_rejects_list_entries_and_tenant_label_mismatch(): void
    {
        $log = app(JsonWorkflowLog::class);

        foreach ([[], ['company' => '8888']] as $entry) {
            try {
                $log->append('9999', $entry);
                $this->fail('Entry audit yang tidak sah harus ditolak.');
            } catch (InvalidArgumentException) {
                $this->assertFalse(Storage::disk('company-json')->exists('json/9999/workflow_log.json'));
            }
        }
    }

    /** @return list<array<string, mixed>> */
    private function workflowLog(): array
    {
        if (! Storage::disk('company-json')->exists('json/9999/workflow_log.json')) {
            return [];
        }

        return json_decode(
            Storage::disk('company-json')->get('json/9999/workflow_log.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }
}

class WorkflowRecord implements HasWorkflow
{
    public function __construct(
        private readonly string $company,
        private readonly string $entity,
        private readonly string|int $id,
        private string $stage,
    ) {}

    public function workflowCompany(): string
    {
        return $this->company;
    }

    public function workflowEntity(): string
    {
        return $this->entity;
    }

    public function workflowIdentifier(): string|int
    {
        return $this->id;
    }

    public function workflowStage(): string
    {
        return $this->stage;
    }

    public function setWorkflowStage(string $stage): void
    {
        $this->stage = $stage;
    }
}
