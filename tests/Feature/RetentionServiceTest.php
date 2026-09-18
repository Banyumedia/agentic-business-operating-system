<?php

namespace Tests\Feature;

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
}
