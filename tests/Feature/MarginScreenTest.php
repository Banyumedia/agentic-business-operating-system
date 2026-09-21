<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Contracts\CompanySettingsStore;
use App\Contracts\EntityRepository;
use App\Livewire\Screens\MarginScreen;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * T-46 (D-62): laba-rugi proyek BASIS KAS.
 *
 * Test inti di kelas ini adalah yang membuktikan tagihan terbit tapi belum
 * dibayar TIDAK menaikkan pendapatan. Kalau test itu jatuh, angka laba sudah
 * berubah arti dan pemilik usaha bisa salah menetapkan harga.
 */
class MarginScreenTest extends TestCase
{
    private string $jsonPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->jsonPath = storage_path('framework/testing/margin-'.bin2hex(random_bytes(5)));
        config(['datasource.json_path' => $this->jsonPath]);

        Storage::fake('company-json');
        foreach (['bengkel-arka', 'salon-ayu'] as $company) {
            Storage::disk('company-json')->put(
                "json/{$company}/settings.json",
                json_encode(['preset' => 'bengkel'], JSON_THROW_ON_ERROR),
            );
        }

        app(CompanyContext::class)->setCurrent('bengkel-arka');
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->jsonPath);
        parent::tearDown();
    }

    public function test_profit_is_cash_in_minus_cash_out_per_project(): void
    {
        $this->project(1, 'Renovasi Ruko');
        $this->cash('in', 4000000, 1);
        $this->cash('out', 1500000, 1);
        $this->cash('out', 500000, 1);

        $rows = $this->screen()->viewData('rows');

        $this->assertSame('Renovasi Ruko', $rows[0]['name']);
        $this->assertSame(4000000.0, $rows[0]['income']);
        $this->assertSame(2000000.0, $rows[0]['expense']);
        $this->assertSame(2000000.0, $rows[0]['profit']);
    }

    public function test_issued_but_unpaid_invoice_does_not_raise_income(): void
    {
        $this->project(1, 'Renovasi Ruko');
        $this->cash('out', 2000000, 1);
        $this->invoice(1, 'issued', 10000000, 0);

        $component = $this->screen();
        $rows = $component->viewData('rows');

        // Basis kas: pendapatan nol karena belum ada uang masuk.
        $this->assertSame(0.0, $rows[0]['income']);
        $this->assertSame(-2000000.0, $rows[0]['profit']);

        // Piutangnya tetap terlihat, tapi di luar laba.
        $this->assertSame(10000000.0, $rows[0]['receivable']);
        $this->assertSame(0.0, $component->viewData('totalIncome'));
        $this->assertSame(10000000.0, $component->viewData('totalReceivable'));
    }

    public function test_draft_invoice_is_not_counted_as_receivable(): void
    {
        $this->project(1, 'Renovasi Ruko');
        $this->invoice(1, 'draft', 5000000, 0);

        // Draf belum menagih siapa pun.
        $this->assertSame(0.0, $this->screen()->viewData('totalReceivable'));
    }

    public function test_partially_paid_invoice_counts_only_the_cash_received(): void
    {
        $this->project(1, 'Renovasi Ruko');
        $this->invoice(1, 'partial', 10000000, 4000000);
        $this->cash('in', 4000000, 1);

        $rows = $this->screen()->viewData('rows');

        $this->assertSame(4000000.0, $rows[0]['income']);
        $this->assertSame(6000000.0, $rows[0]['receivable']);
    }

    public function test_project_without_transactions_shows_zero_not_an_error(): void
    {
        $this->project(1, 'Proyek Baru');

        $rows = $this->screen()->assertOk()->viewData('rows');

        $this->assertSame(0.0, $rows[0]['income']);
        $this->assertSame(0.0, $rows[0]['expense']);
        $this->assertSame(0.0, $rows[0]['profit']);
    }

    public function test_entries_without_a_project_do_not_leak_into_any_project(): void
    {
        $this->project(1, 'Renovasi Ruko');
        $this->cash('in', 1000000, 1);
        $this->cash('in', 9999999, null);
        $this->cash('out', 777777, null);

        $component = $this->screen();
        $rows = $component->viewData('rows');

        $this->assertSame(1000000.0, $rows[0]['income']);
        $this->assertSame(1000000.0, $component->viewData('totalIncome'));

        // Tidak disembunyikan: dilaporkan terpisah sebagai belum terbebani.
        $this->assertSame(9999999.0, $component->viewData('unassigned')['income']);
        $this->assertSame(777777.0, $component->viewData('unassigned')['expense']);
    }

    public function test_large_amounts_keep_two_decimal_precision(): void
    {
        $this->project(1, 'Proyek Besar');
        $this->cash('in', 12345678901.45, 1);
        $this->cash('out', 1.05, 1);

        $rows = $this->screen()->viewData('rows');

        $this->assertSame(12345678901.45, $rows[0]['income']);
        $this->assertSame(12345678900.40, $rows[0]['profit']);
    }

    public function test_figures_are_isolated_per_tenant(): void
    {
        $this->project(1, 'Proyek Kita');
        $this->cash('in', 5000000, 1);

        app(CompanyContext::class)->setCurrent('salon-ayu');
        app(EntityRepository::class)->for('salon-ayu', 'projects')
            ->save(['id' => 1, 'name' => 'Proyek Usaha Lain', 'stage' => 'survei']);

        $rows = $this->screen()->viewData('rows');

        // Proyek dengan id sama di company lain tidak mewarisi uang tetangganya.
        $this->assertSame('Proyek Usaha Lain', $rows[0]['name']);
        $this->assertSame(0.0, $rows[0]['income']);
    }

    public function test_screen_fails_closed_when_the_company_changes_after_mount(): void
    {
        $component = $this->screen()->assertOk();

        app(CompanyContext::class)->setCurrent('salon-ayu');
        $component->call('$refresh')->assertForbidden();
    }

    public function test_screen_is_closed_when_the_cashbook_capability_is_revoked(): void
    {
        $component = $this->screen()->assertOk();

        app(CompanySettingsStore::class)->update('bengkel-arka', static function (array $settings): array {
            $settings['features']['finance.cashbook'] = false;

            return $settings;
        });

        $component->call('$refresh')->assertForbidden();
    }

    public function test_margin_sources_have_no_industry_branch_or_direct_database_access(): void
    {
        $source = implode("\n", [
            file_get_contents(app_path('Livewire/Screens/MarginScreen.php')),
            file_get_contents(resource_path('views/livewire/screens/margin.blade.php')),
        ]);

        $this->assertDoesNotMatchRegularExpression('/\b(?:bengkel|klinik|salon|laundry|apotek|kontraktor|agency)\b/i', $source);
        $this->assertStringNotContainsString('DB::', $source);

        // Layar wajib menyebut basisnya secara eksplisit (D-62).
        $this->assertStringContainsString('basis kas', $source);
    }

    private function screen(): Testable
    {
        return Livewire::test(MarginScreen::class, ['module' => 'projects', 'submodule' => 'margin']);
    }

    private function project(int $id, string $name): void
    {
        app(EntityRepository::class)->for(app(CompanyContext::class)->current(), 'projects')
            ->save(['id' => $id, 'name' => $name, 'stage' => 'survei']);
    }

    private function cash(string $direction, float $amount, ?int $projectId): void
    {
        app(EntityRepository::class)->for(app(CompanyContext::class)->current(), 'cash_entries')->save([
            'entry_date' => '2026-09-22',
            'direction' => $direction,
            'amount' => $amount,
            'project_id' => $projectId,
        ]);
    }

    private function invoice(int $projectId, string $status, float $total, float $paid): void
    {
        app(EntityRepository::class)->for(app(CompanyContext::class)->current(), 'customer_invoices')->save([
            'number' => 'INV-'.$status.'-'.$total,
            'title' => 'Tagihan uji',
            'status' => $status,
            'issue_date' => '2026-09-22',
            'project_id' => $projectId,
            'grand_total' => $total,
            'paid_amount' => $paid,
        ]);
    }
}
