<?php

namespace Tests\Feature\Models;

use App\Contracts\CompanyContext;
use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Services\CompanyPresetResolver;
use App\Services\TerminologyResolver;
use App\Services\Workflow\JsonWorkflowLog;
use App\Services\Workflow\WorkflowEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class DealAndContactTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['datasource.driver' => 'eloquent']);
    }

    public function test_tenant_isolation_deals_and_contacts()
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $contactA = Contact::factory()->create(['company_id' => $companyA->id]);
        $dealA = Deal::factory()->create([
            'company_id' => $companyA->id,
            'contact_id' => $contactA->id,
        ]);

        $contactB = Contact::factory()->create(['company_id' => $companyB->id]);
        $dealB = Deal::factory()->create([
            'company_id' => $companyB->id,
            'contact_id' => $contactB->id,
        ]);

        $companyADeals = Deal::where('company_id', $companyA->id)->get();
        $this->assertCount(1, $companyADeals);
        $this->assertEquals($dealA->id, $companyADeals->first()->id);

        $companyBContacts = Contact::where('company_id', $companyB->id)->get();
        $this->assertCount(1, $companyBContacts);
        $this->assertEquals($contactB->id, $companyBContacts->first()->id);
    }

    public function test_different_presets_use_same_deals_table_different_terminology()
    {
        // Prove D-31: we use one deals table for completely different presets
        $klinikCompany = Company::factory()->create(['business_preset' => 'klinik']);
        $agencyCompany = Company::factory()->create(['business_preset' => 'agency']);

        // Bukti utama D-31: term() menghasilkan label BERBEDA untuk preset
        // berbeda walau keduanya memakai tabel `deals`/`contacts` yang sama.
        $mockContextKlinik = Mockery::mock(CompanyContext::class);
        $mockContextKlinik->shouldReceive('current')->andReturn((string) $klinikCompany->id);
        $mockContextKlinik->shouldReceive('preset')->andReturn('klinik');
        $this->app->instance(CompanyContext::class, $mockContextKlinik);
        $this->app->forgetInstance(CompanyPresetResolver::class);
        $this->app->forgetInstance(TerminologyResolver::class);
        $klinikContactTerm = term('contact');
        $klinikDealTerm = term('deal');

        $mockContextAgency = Mockery::mock(CompanyContext::class);
        $mockContextAgency->shouldReceive('current')->andReturn((string) $agencyCompany->id);
        $mockContextAgency->shouldReceive('preset')->andReturn('agency');
        $this->app->instance(CompanyContext::class, $mockContextAgency);
        $this->app->forgetInstance(CompanyPresetResolver::class);
        $this->app->forgetInstance(TerminologyResolver::class);
        $agencyContactTerm = term('contact');
        $agencyDealTerm = term('deal');

        $this->assertNotEquals($klinikContactTerm, $agencyContactTerm, 'term(contact) harus berbeda per preset (D-31).');
        $this->assertNotEquals($klinikDealTerm, $agencyDealTerm, 'term(deal) harus berbeda per preset (D-31).');
        $this->assertSame('Pasien', $klinikContactTerm);
        $this->assertSame('Kunjungan', $klinikDealTerm);

        $patient = Contact::factory()->create([
            'company_id' => $klinikCompany->id,
            'type' => 'patient', // Specific to klinik
            'attributes' => ['allergies' => ['peanut']],
        ]);

        $kunjungan = Deal::factory()->create([
            'company_id' => $klinikCompany->id,
            'contact_id' => $patient->id,
            'title' => 'Pemeriksaan Rutin',
            'stage' => 'new',
        ]);

        $client = Contact::factory()->create([
            'company_id' => $agencyCompany->id,
            'type' => 'client', // Specific to agency
            'attributes' => ['industry' => 'Tech'],
        ]);

        $proyekDeal = Deal::factory()->create([
            'company_id' => $agencyCompany->id,
            'contact_id' => $client->id,
            'title' => 'Website Redesign',
            'stage' => 'qualified',
        ]);

        $this->assertDatabaseHas('contacts', [
            'id' => $patient->id,
            'type' => 'patient',
        ]);

        $this->assertDatabaseHas('contacts', [
            'id' => $client->id,
            'type' => 'client',
        ]);

        $this->assertDatabaseHas('deals', [
            'id' => $kunjungan->id,
            'title' => 'Pemeriksaan Rutin',
        ]);

        $this->assertDatabaseHas('deals', [
            'id' => $proyekDeal->id,
            'title' => 'Website Redesign',
        ]);
    }

    public function test_workflow_engine_can_transition_deal()
    {
        $user = User::factory()->create();
        $company = Company::factory()->create([
            'owner_user_id' => $user->id,
            'business_preset' => 'klinik',
        ]);

        $user->update(['current_company_id' => $company->id]);
        $this->actingAs($user);

        // Mock the CompanyContext exactly like WorkflowEngineTest does to satisfy WorkflowEngine checks
        $mockContext = Mockery::mock(CompanyContext::class);
        $mockContext->shouldReceive('current')->andReturn((string) $company->id);
        $mockContext->shouldReceive('preset')->andReturn('klinik');
        $this->app->instance(CompanyContext::class, $mockContext);

        // Define workflow for deals directly in the database
        WorkflowDefinition::create([
            'company_id' => $company->id,
            'entity' => 'deals',
            'definition' => [
                'stages' => [
                    ['code' => 'new', 'label' => 'Baru'],
                    ['code' => 'qualified', 'label' => 'Terkualifikasi'],
                ],
                'transitions' => [
                    ['from' => 'new', 'to' => 'qualified', 'roles' => ['owner', 'staff']],
                ],
                'terminal' => [],
            ],
            'is_active' => true,
        ]);

        $contact = Contact::factory()->create(['company_id' => $company->id]);
        $deal = Deal::factory()->create([
            'company_id' => $company->id,
            'contact_id' => $contact->id,
            'stage' => 'new',
        ]);

        // Mock DB log so we don't need all dependencies
        app()->bind(JsonWorkflowLog::class, function () {
            return new class extends JsonWorkflowLog
            {
                public function __construct() {}

                public function append(string $companyId, array $entry): void {}

                public function entries(string $companyId, string $entity, string|int $recordId): array
                {
                    return [];
                }
            };
        });

        $engine = app(WorkflowEngine::class);

        // Success transition
        $result = $engine->transition($deal, 'qualified', 'owner');

        $this->assertEquals('transitioned', $result['status']);
        $this->assertEquals('new', $result['from']);
        $this->assertEquals('qualified', $result['to']);

        // Assert state was updated in DB
        $deal->refresh();
        $this->assertEquals('qualified', $deal->stage);

        // Invalid transition should fail
        $this->expectException(\InvalidArgumentException::class);
        $engine->transition($deal, 'invalid_stage', 'owner');
    }

    public function test_activity_log_polymorphic_relations()
    {
        $company = Company::factory()->create();
        $contact = Contact::factory()->create(['company_id' => $company->id]);
        $deal = Deal::factory()->create([
            'company_id' => $company->id,
            'contact_id' => $contact->id,
        ]);

        $contactLog = ActivityLog::factory()->create([
            'company_id' => $company->id,
            'subject_type' => Contact::class,
            'subject_id' => $contact->id,
            'type' => 'call',
        ]);

        $dealLog = ActivityLog::factory()->create([
            'company_id' => $company->id,
            'subject_type' => Deal::class,
            'subject_id' => $deal->id,
            'type' => 'note',
        ]);

        $this->assertInstanceOf(Contact::class, $contactLog->subject);
        $this->assertEquals($contact->id, $contactLog->subject->id);

        $this->assertInstanceOf(Deal::class, $dealLog->subject);
        $this->assertEquals($deal->id, $dealLog->subject->id);
    }
}
