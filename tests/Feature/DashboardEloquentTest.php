<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Contracts\EntityRepository;
use App\Models\AssistantReport;
use App\Models\Company;
use App\Models\User;
use App\Providers\DataSourceServiceProvider;
use App\Services\Dashboard\DashboardComposer;
use App\Services\Dashboard\WidgetRegistry;
use Database\Seeders\BusinessPresetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
