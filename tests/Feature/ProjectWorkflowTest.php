<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\ProjectVendor;
use App\Models\TimesheetEntry;
use App\Models\User;
use App\Services\Workflow\WorkflowEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Company $companyA;

    private Company $companyB;

    private WorkflowEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();

        config(['datasource.driver' => 'eloquent']); // Use Eloquent for tests to mock easily

        $this->companyA = Company::factory()->create([
            'name' => 'Agency A',
            'business_preset' => 'agency', // Using agency preset which has projects workflow
        ]);

        $this->companyB = Company::factory()->create([
            'name' => 'Agency B',
            'business_preset' => 'agency',
        ]);

        $this->engine = $this->app->make(WorkflowEngine::class);
    }

    public function test_tenant_isolation(): void
    {
        $projectA = Project::create([
            'company_id' => $this->companyA->id,
            'name' => 'Project A',
            'stage' => 'briefing',
        ]);
        $projectA->milestones()->create([
            'company_id' => $this->companyA->id,
            'name' => 'M1',
            'trigger_type' => 'manual',
            'amount' => 1000,
        ]);
        $projectA->assignments()->create([
            'company_id' => $this->companyA->id,
            'employee_id' => 1,
        ]);
        $projectA->vendors()->create([
            'company_id' => $this->companyA->id,
            'vendor_name' => 'Vendor A',
        ]);
        $projectA->timesheets()->create([
            'company_id' => $this->companyA->id,
            'employee_id' => 1,
            'work_date' => '2026-09-18',
            'hours' => 5,
        ]);

        $projectB = Project::create([
            'company_id' => $this->companyB->id,
            'name' => 'Project B',
            'stage' => 'briefing',
        ]);

        $this->assertEquals(1, Project::where('company_id', $this->companyA->id)->count());
        $this->assertEquals(1, ProjectMilestone::where('company_id', $this->companyA->id)->count());
        $this->assertEquals(1, ProjectVendor::where('company_id', $this->companyA->id)->count());
        $this->assertEquals(1, TimesheetEntry::where('company_id', $this->companyA->id)->count());
        $this->assertEquals('Project A', Project::where('company_id', $this->companyA->id)->first()->name);
    }

    public function test_project_type_is_just_data(): void
    {
        $project = Project::create([
            'company_id' => $this->companyA->id,
            'name' => 'Event Project',
            'type' => 'event',
            'stage' => 'briefing',
        ]);

        $this->assertEquals('event', $project->type);
        // The fact that it saves and loads fine without any specific schema validations
        // proves it's just data.
    }

    public function test_milestone_trigger_updates_status(): void
    {
        $project = Project::create([
            'company_id' => $this->companyA->id,
            'name' => 'Milestone Proj',
            'stage' => 'briefing',
            'progress_pct' => 0,
        ]);

        $milestone = $project->milestones()->create([
            'company_id' => $this->companyA->id,
            'name' => 'Halfway',
            'trigger_type' => 'progress_pct',
            'trigger_value' => 50,
            'amount' => 5000,
            'status' => 'pending',
        ]);

        $project->updateProgress(40);
        $this->assertEquals('pending', $milestone->fresh()->status);

        // This should trigger the invoice.create_* effect
        $project->updateProgress(50);

        $this->assertEquals('invoiced', $milestone->fresh()->status);
        $this->assertNotNull($milestone->fresh()->achieved_at);
    }

    public function test_workflow_transition_works(): void
    {
        $this->actingAs(User::factory()->create());
        // Re-bind CompanyContext since setting config in setUp might be too late
        // to swap out the service provider's singleton
        $mockContext = \Mockery::mock(CompanyContext::class);
        $mockContext->shouldReceive('current')->andReturn((string) $this->companyA->id);
        $mockContext->shouldReceive('preset')->andReturn('agency');
        $this->app->instance(CompanyContext::class, $mockContext);

        $this->engine = $this->app->make(WorkflowEngine::class);

        $project = Project::create([
            'company_id' => $this->companyA->id,
            'name' => 'WF Proj',
            'stage' => 'briefing', // Starting stage per agency.json
        ]);

        // Transition from briefing to produksi
        $result = $this->engine->transition($project, 'produksi', 'staff');

        $this->assertEquals('transitioned', $result['status']);
        $this->assertEquals('produksi', $project->fresh()->stage);

        // Cancel requires approval
        $result = $this->engine->transition($project, 'dibatalkan', 'owner');

        $this->assertEquals('pending_approval', $result['status']);
        $this->assertEquals('produksi', $project->fresh()->stage); // Still produksi
    }

    public function test_foreign_keys_and_nullables(): void
    {
        $contact = Contact::create([
            'company_id' => $this->companyA->id,
            'name' => 'Contact A',
        ]);

        $project = Project::create([
            'company_id' => $this->companyA->id,
            'name' => 'Proj',
            'stage' => 'briefing',
        ]);

        $vendor = $project->vendors()->create([
            'company_id' => $this->companyA->id,
            'vendor_name' => 'Vendor',
            'vendor_contact_id' => $contact->id,
        ]);

        $this->assertEquals($contact->id, $vendor->vendor_contact_id);

        $timesheet = TimesheetEntry::create([
            'company_id' => $this->companyA->id,
            'project_id' => null, // Allowed!
            'employee_id' => 99, // Employees table doesn't exist, so this is just data
            'work_date' => '2026-09-18',
            'hours' => 2,
        ]);

        $this->assertNull($timesheet->project_id);
        $this->assertEquals(99, $timesheet->employee_id);
    }
}
