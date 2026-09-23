<?php

namespace Tests\Feature;

use App\Models\CashEntry;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\Retention;
use App\Services\Domain\RetentionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class RetentionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_compute_and_hold_creates_retention_if_pct_greater_than_zero(): void
    {
        $company = Company::factory()->create();
        $project = Project::factory()->create(['company_id' => $company->id]);
        $milestone = new ProjectMilestone([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'name' => 'Opname',
            'amount' => 100000,
            'retention_pct' => 5.0,
            'trigger_type' => 'manual',
            'trigger_value' => 0,
        ]);
        $milestone->save();

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'amount' => 100000,
        ]);

        $service = new RetentionService;
        $retention = $service->computeAndHold($milestone, $invoice);

        $this->assertNotNull($retention);
        $this->assertEquals(5000, $retention->amount);
        $this->assertEquals('held', $retention->status);
        $this->assertEquals($company->id, $retention->company_id);
    }

    public function test_rejects_invoicing_before_release_date(): void
    {
        $company = Company::factory()->create();
        $project = Project::factory()->create(['company_id' => $company->id]);

        $retention = Retention::factory()->create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'status' => 'held',
            'release_on' => Carbon::tomorrow(),
        ]);

        $service = new RetentionService;

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Retensi belum mencapai tanggal rilis');

        $service->ensureCanBeInvoiced($retention);
    }

    public function test_allows_invoicing_after_release_date(): void
    {
        $company = Company::factory()->create();
        $project = Project::factory()->create(['company_id' => $company->id]);

        $retention = Retention::factory()->create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'status' => 'held',
            'release_on' => Carbon::yesterday(),
        ]);

        $service = new RetentionService;
        $service->ensureCanBeInvoiced($retention);

        $this->assertTrue(true);
    }

    public function test_disburse_writes_a_cash_entry_and_releases_the_retention(): void
    {
        $company = Company::factory()->create();
        $project = Project::factory()->create(['company_id' => $company->id]);

        $retention = Retention::factory()->create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'amount' => 5000,
            'status' => 'held',
            'release_on' => Carbon::yesterday(),
        ]);

        $service = new RetentionService;
        $entry = $service->disburse($retention);

        $this->assertSame('out', $entry->direction);
        $this->assertEquals(5000, $entry->amount);
        $this->assertSame($company->id, $entry->company_id);
        $this->assertSame($project->id, $entry->project_id);
        $this->assertSame('retention', $entry->source_type);
        $this->assertSame($retention->id, $entry->source_id);

        $this->assertSame('released', $retention->status);
        $this->assertNotNull($retention->released_at);
        $this->assertDatabaseHas('cash_entries', [
            'source_type' => 'retention',
            'source_id' => $retention->id,
            'direction' => 'out',
        ]);
    }

    public function test_negative_disburse_rejects_a_retention_that_is_not_held(): void
    {
        $company = Company::factory()->create();
        $project = Project::factory()->create(['company_id' => $company->id]);

        $retention = Retention::factory()->create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'status' => 'released',
            'released_at' => Carbon::yesterday(),
        ]);

        $service = new RetentionService;

        $this->expectException(LogicException::class);

        try {
            $service->disburse($retention);
        } finally {
            $this->assertSame(0, CashEntry::where('source_type', 'retention')->count());
        }
    }

    public function test_negative_disburse_rejects_a_retention_before_its_release_date(): void
    {
        $company = Company::factory()->create();
        $project = Project::factory()->create(['company_id' => $company->id]);

        $retention = Retention::factory()->create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'status' => 'held',
            'release_on' => Carbon::tomorrow(),
        ]);

        $service = new RetentionService;

        $this->expectException(LogicException::class);

        try {
            $service->disburse($retention);
        } finally {
            $this->assertSame(0, CashEntry::where('source_type', 'retention')->count());
            $this->assertSame('held', $retention->fresh()->status);
        }
    }
}
