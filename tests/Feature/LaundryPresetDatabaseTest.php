<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Contracts\CompanySettingsStore;
use App\Contracts\HasWorkflow;
use App\Models\Company;
use App\Models\User;
use App\Services\Eloquent\EloquentCompanyContext;
use App\Services\Eloquent\EloquentCompanySettingsStore;
use App\Services\Workflow\WorkflowEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaundryPresetDatabaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['datasource.driver' => 'eloquent']);
        $this->app->scoped(CompanyContext::class, EloquentCompanyContext::class);
        $this->app->scoped(CompanySettingsStore::class, EloquentCompanySettingsStore::class);
        $this->artisan('db:seed', ['--class' => 'BusinessPresetSeeder']);
    }

    public function test_laundry_preset_composes_correctly_in_database_and_shows_no_hardcoding()
    {
        $user = User::factory()->create();
        $company = Company::factory()->create([
            'owner_user_id' => $user->id,
            'business_preset' => 'laundry',
        ]);

        app(CompanyContext::class)->setCurrent($company->id);

        $response = $this->actingAs($user)->get('/app/dashboard');
        $response->assertStatus(200);

        // Sidebar / lobby check - 'Kontak' is 'Pelanggan'
        $response = $this->actingAs($user)->get('/app/contacts');
        $response->assertStatus(200);
        $response->assertSee('Pelanggan');

        // Check EnsureFeatureEnabled blocks off modules
        // According to laundry.json, they don't have deals
        $this->actingAs($user)->get('/app/contacts/deals')->assertStatus(403);

        // Check workflow engine for orders
        $workflow = app(WorkflowEngine::class);
        $order = new class($company) implements HasWorkflow
        {
            public string $currentStage = 'terima';

            public function __construct(private $company) {}

            public function workflowCompany(): string
            {
                return (string) $this->company->id;
            }

            public function workflowEntity(): string
            {
                return 'orders';
            }

            public function workflowIdentifier(): string|int
            {
                return 1;
            }

            public function workflowStage(): string
            {
                return $this->currentStage;
            }

            public function setWorkflowStage(string $stage): void
            {
                $this->currentStage = $stage;
            }
        };

        // Transition should succeed
        $workflow->transition($order, 'proses', 'owner');
        $this->assertEquals('proses', $order->workflowStage());
    }
}
