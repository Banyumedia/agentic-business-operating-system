<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Contracts\EntityRepository;
use App\Livewire\Dashboard;
use App\Services\Dashboard\DashboardComposer;
use App\Services\Dashboard\WidgetRegistry;
use Illuminate\Filesystem\Filesystem;
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
