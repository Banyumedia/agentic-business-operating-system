<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Contracts\CompanySettingsStore;
use App\Contracts\EntityRepository;
use App\Livewire\Screens\ReportScreen;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * T-54 (D-64, D-62): Laporan Keuangan sebagai pola layar `report`.
 *
 * Angkanya uang, jadi yang dijaga lebih dulu di kelas ini adalah batasnya:
 * laporan menolak melayani company yang berpindah setelah mount, menolak saat
 * kapabilitasnya dicabut, dan tidak pernah menjumlahkan entri kas tetangga.
 */
class ReportScreenTest extends TestCase
{
    private string $jsonPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->jsonPath = storage_path('framework/testing/report-'.bin2hex(random_bytes(5)));
        config(['datasource.json_path' => $this->jsonPath]);

        Storage::fake('company-json');
        foreach (['bengkel-arka', 'salon-ayu'] as $company) {
            Storage::disk('company-json')->put(
                "json/{$company}/settings.json",
                json_encode(['preset' => 'bengkel'], JSON_THROW_ON_ERROR),
            );

            // D-64: penyalaan di preset mengikuti gerbang paket (D-52) dan di
            // luar lingkup T-54, jadi test menyalakannya sebagai override.
            app(CompanySettingsStore::class)->update($company, static function (array $settings): array {
                $settings['features']['finance.accounting'] = true;

                return $settings;
            });
        }

        app(CompanyContext::class)->setCurrent('bengkel-arka');
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->jsonPath);
        parent::tearDown();
    }

    public function test_report_shows_real_cash_figures_per_period(): void
    {
        $this->cash('in', 7500000, '2026-01-05');
        $this->cash('in', 2500000, '2026-01-20');
        $this->cash('out', 4000000, '2026-01-28');

        $component = $this->screen();
        $rows = $component->viewData('rows');

        $this->assertCount(1, $rows);
        $this->assertSame('2026-01', $rows[0]['period']);
        $this->assertSame('Januari 2026', $rows[0]['label']);
        $this->assertSame(10000000.0, $rows[0]['income']);
        $this->assertSame(4000000.0, $rows[0]['expense']);
        $this->assertSame(6000000.0, $rows[0]['net']);
        $this->assertSame(6000000.0, $rows[0]['balance']);

        $this->assertSame(10000000.0, $component->viewData('totalIncome'));
        $this->assertSame(4000000.0, $component->viewData('totalExpense'));
        $this->assertSame(6000000.0, $component->viewData('balance'));

        // Angka benar-benar sampai ke halaman, bukan hanya ke view data.
        $component->assertSee('10.000.000,00')->assertSee('6.000.000,00');
    }

    public function test_running_balance_accumulates_across_periods(): void
    {
        $this->cash('in', 5000000, '2026-01-10');
        $this->cash('out', 1000000, '2026-02-10');
        $this->cash('out', 9000000, '2026-03-10');

        $rows = $this->screen()->viewData('rows');

        $this->assertSame([5000000.0, 4000000.0, -5000000.0], array_column($rows, 'balance'));
        $this->assertSame(['2026-01', '2026-02', '2026-03'], array_column($rows, 'period'));
    }

    public function test_period_without_transactions_reports_zero_instead_of_an_error(): void
    {
        $this->cash('in', 3000000, '2026-01-15');
        $this->cash('in', 1000000, '2026-03-15');

        $rows = $this->screen()->assertOk()->viewData('rows');

        // Bulan kosong di tengah tetap muncul: bulan yang hilang dari tabel
        // terbaca sebagai bulan yang belum ditutup, padahal tidak ada transaksi.
        $this->assertCount(3, $rows);
        $this->assertSame('Februari 2026', $rows[1]['label']);
        $this->assertSame(0.0, $rows[1]['income']);
        $this->assertSame(0.0, $rows[1]['expense']);
        $this->assertSame(0.0, $rows[1]['net']);
        $this->assertSame(3000000.0, $rows[1]['balance']);
    }

    public function test_company_without_any_cash_entry_reports_zero_instead_of_an_error(): void
    {
        $component = $this->screen()->assertOk();

        $this->assertSame([], $component->viewData('rows'));
        $this->assertSame(0.0, $component->viewData('totalIncome'));
        $this->assertSame(0.0, $component->viewData('totalExpense'));
        $this->assertSame(0.0, $component->viewData('balance'));
    }

    public function test_periods_are_reported_in_chronological_order_across_years(): void
    {
        // Entri ditulis acak; laporan tetap urut supaya saldo berjalannya punya
        // arti. Tanpa urutan ini saldo bisa menampilkan angka yang tidak pernah
        // benar-benar terjadi.
        $this->cash('in', 1000000, '2027-01-09');
        $this->cash('in', 2000000, '2026-12-09');

        $rows = $this->screen()->viewData('rows');

        $this->assertSame(['2026-12', '2027-01'], array_column($rows, 'period'));
        $this->assertSame(['Desember 2026', 'Januari 2027'], array_column($rows, 'label'));
        $this->assertSame([2000000.0, 3000000.0], array_column($rows, 'balance'));
    }

    public function test_large_amounts_keep_two_decimal_precision(): void
    {
        $this->cash('in', 12345678901.45, '2026-01-05');
        $this->cash('out', 1.05, '2026-01-06');

        $rows = $this->screen()->viewData('rows');

        $this->assertSame(12345678901.45, $rows[0]['income']);
        $this->assertSame(12345678900.40, $rows[0]['net']);
    }

    public function test_figures_are_isolated_per_tenant(): void
    {
        $this->cash('in', 5000000, '2026-01-05');

        app(CompanyContext::class)->setCurrent('salon-ayu');
        app(EntityRepository::class)->for('salon-ayu', 'cash_entries')->save([
            'entry_date' => '2026-01-05',
            'direction' => 'in',
            'amount' => 111.0,
        ]);

        $component = $this->screen();

        // Entri company lain tidak bocor ke laporan ini.
        $this->assertSame(111.0, $component->viewData('totalIncome'));
        $this->assertSame(111.0, $component->viewData('balance'));
    }

    public function test_screen_fails_closed_when_the_company_changes_after_mount(): void
    {
        $component = $this->screen()->assertOk();

        app(CompanyContext::class)->setCurrent('salon-ayu');
        $component->call('$refresh')->assertForbidden();
    }

    public function test_screen_is_closed_when_the_accounting_capability_is_revoked(): void
    {
        $component = $this->screen()->assertOk();

        app(CompanySettingsStore::class)->update('bengkel-arka', static function (array $settings): array {
            $settings['features']['finance.accounting'] = false;

            return $settings;
        });

        $component->call('$refresh')->assertForbidden();
    }

    public function test_report_sources_have_no_industry_branch_or_direct_database_access(): void
    {
        $source = implode("\n", [
            file_get_contents(app_path('Livewire/Screens/ReportScreen.php')),
            file_get_contents(resource_path('views/livewire/screens/report.blade.php')),
        ]);

        $this->assertDoesNotMatchRegularExpression('/\b(?:bengkel|klinik|salon|laundry|apotek|kontraktor|agency|restoran|katering)\b/i', $source);
        $this->assertStringNotContainsString('DB::', $source);

        // Layar wajib menyebut basisnya secara eksplisit (D-62).
        $this->assertStringContainsString('basis kas', $source);
    }

    private function screen(): Testable
    {
        return Livewire::test(ReportScreen::class, ['module' => 'accounting', 'submodule' => 'reports']);
    }

    private function cash(string $direction, float $amount, string $date): void
    {
        app(EntityRepository::class)->for(app(CompanyContext::class)->current(), 'cash_entries')->save([
            'entry_date' => $date,
            'direction' => $direction,
            'amount' => $amount,
        ]);
    }
}
