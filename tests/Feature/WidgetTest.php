<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Livewire\Widgets\DashboardWidget;
use App\Services\Dashboard\WidgetRegistry;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class WidgetTest extends TestCase
{
    private string $jsonPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->jsonPath = storage_path('framework/testing/widget-'.bin2hex(random_bytes(5)));
        config(['datasource.json_path' => $this->jsonPath]);

        Storage::fake('company-json');
        Storage::disk('company-json')->put(
            'json/bengkel-arka/business_identity.json',
            json_encode(['id' => 1, 'preset' => 'bengkel', 'tax_mode' => 'non_taxable'], JSON_THROW_ON_ERROR),
        );
        Storage::disk('company-json')->put(
            'json/bengkel-arka/settings.json',
            json_encode(['preset' => 'bengkel'], JSON_THROW_ON_ERROR),
        );
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->jsonPath);
        parent::tearDown();
    }

    public function test_widget_empty_state_is_contextual_per_widget_key(): void
    {
        app(CompanyContext::class)->setCurrent('bengkel-arka');

        $emptyTitles = [
            'upcoming_schedule' => 'Tidak ada agenda mendatang',
            'low_stock' => 'Semua stok aman',
            'pending_approvals' => 'Tidak ada yang menunggu persetujuan',
        ];

        foreach ($emptyTitles as $key => $expectedTitle) {
            $widget = app(WidgetRegistry::class)->compose($key);

            $this->assertSame('0', $widget['value'], "Widget {$key} tanpa data harus bernilai 0.");

            Livewire::test(DashboardWidget::class, ['widget' => $widget])
                ->assertSee($expectedTitle)
                ->assertDontSee('Tidak ada data');
        }
    }

    public function test_cashflow_widget_has_no_list_placeholder(): void
    {
        app(CompanyContext::class)->setCurrent('bengkel-arka');
        $widget = app(WidgetRegistry::class)->compose('kpi_cashflow');

        Livewire::test(DashboardWidget::class, ['widget' => $widget])
            ->assertDontSee('Tidak ada data')
            ->assertSee('Arus kas');
    }

    public function test_widget_with_items_renders_them_without_a_placeholder(): void
    {
        app(CompanyContext::class)->setCurrent('bengkel-arka');
        app(\App\Contracts\EntityRepository::class)->for('bengkel-arka', 'items')->save(['id' => 1, 'name' => 'Kanvas Unik', 'min_stock' => 5]);
        app(\App\Contracts\EntityRepository::class)->for('bengkel-arka', 'item_batches')->save(['id' => 1, 'item_id' => 1, 'batch_no' => 'B-001', 'qty_on_hand' => 1]);

        $widget = app(WidgetRegistry::class)->compose('low_stock');

        $this->assertSame('1', $widget['value']);
        Livewire::test(DashboardWidget::class, ['widget' => $widget])
            ->assertSee('Kanvas Unik')
            ->assertDontSee('Tidak ada data');
    }

    public function test_widget_view_has_no_static_dummy_numbers(): void
    {
        $source = file_get_contents(resource_path('views/livewire/widgets/dashboard-widget.blade.php'));

        // Widget hanya boleh menampilkan angka dari data, bukan literal statis.
        $this->assertDoesNotMatchRegularExpression('/>\s*(?:1[,.]204|45|12|99)\s*</', $source);
        $this->assertStringNotContainsString('DB::', $source);
    }
}
