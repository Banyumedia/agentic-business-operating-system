<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Contracts\EntityRepository;
use App\Livewire\Screens\LedgerScreen;
use App\Services\CompanySettingsStore;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class LedgerScreenTest extends TestCase
{
    private string $jsonPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->jsonPath = storage_path('framework/testing/ledger-'.bin2hex(random_bytes(5)));
        config(['datasource.json_path' => $this->jsonPath]);

        Storage::fake('company-json');
        foreach (['bengkel-arka' => 'bengkel', 'salon-ayu' => 'salon'] as $company => $preset) {
            Storage::disk('company-json')->put(
                "json/{$company}/settings.json",
                json_encode(['preset' => $preset], JSON_THROW_ON_ERROR),
            );
        }
        app(CompanyContext::class)->setCurrent('bengkel-arka');
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->jsonPath);
        parent::tearDown();
    }

    public function test_running_balance_follows_the_declared_direction_in_date_order(): void
    {
        $cash = app(EntityRepository::class)->for('bengkel-arka', 'cash_entries');
        // Sengaja tidak berurutan untuk membuktikan pengurutan layar.
        $cash->save(['id' => 2, 'entry_date' => '2026-09-16', 'direction' => 'out', 'amount' => 400000, 'description' => 'Beli sparepart']);
        $cash->save(['id' => 1, 'entry_date' => '2026-09-15', 'direction' => 'in', 'amount' => 1000000, 'description' => 'Setoran kasir']);
        $cash->save(['id' => 3, 'entry_date' => '2026-09-17', 'direction' => 'in', 'amount' => 250000, 'description' => 'Penjualan eceran']);

        $component = Livewire::test(LedgerScreen::class, ['module' => 'accounting'])->assertOk();

        $this->assertTrue($component->viewData('hasDirection'));
        $this->assertEquals(1250000, $component->viewData('incoming'));
        $this->assertEquals(400000, $component->viewData('outgoing'));
        $this->assertEquals(850000, $component->viewData('balance'));

        // Riwayat ditampilkan terbaru lebih dulu, saldo dihitung menaik.
        $entries = $component->viewData('entries');
        $this->assertSame(['2026-09-17', '2026-09-16', '2026-09-15'], array_column($entries, 'date'));
        $this->assertSame([850000.0, 600000.0, 1000000.0], array_column($entries, 'balance'));
        $this->assertSame([false, true, false], array_column($entries, 'outgoing'));
    }

    public function test_negative_balance_is_reported_not_clamped(): void
    {
        $cash = app(EntityRepository::class)->for('bengkel-arka', 'cash_entries');
        $cash->save(['id' => 1, 'entry_date' => '2026-09-15', 'direction' => 'in', 'amount' => 100000]);
        $cash->save(['id' => 2, 'entry_date' => '2026-09-16', 'direction' => 'out', 'amount' => 450000]);

        $component = Livewire::test(LedgerScreen::class, ['module' => 'accounting']);

        $this->assertEquals(-350000, $component->viewData('balance'));
        $component->assertSee('-Rp 450.000');
    }

    public function test_entity_without_direction_accumulates_as_a_plain_total(): void
    {
        $invoices = app(EntityRepository::class)->for('bengkel-arka', 'invoices');
        $invoices->save(['id' => 1, 'type' => 'subscription', 'order_id' => 'INV-1', 'amount' => 750000, 'period_start' => '2026-09-01']);
        $invoices->save(['id' => 2, 'type' => 'topup', 'order_id' => 'INV-2', 'amount' => 250000, 'period_start' => '2026-09-05']);

        $component = Livewire::test(LedgerScreen::class, ['module' => 'accounting', 'submodule' => 'invoices'])->assertOk();

        $this->assertFalse($component->viewData('hasDirection'));
        $this->assertEquals(1000000, $component->viewData('balance'));
        $component->assertDontSee('Masuk')->assertDontSee('Keluar');
    }

    public function test_empty_ledger_uses_company_terminology(): void
    {
        Livewire::test(LedgerScreen::class, ['module' => 'accounting', 'submodule' => 'invoices'])
            ->assertSee('Belum ada Tagihan yang tercatat.');
    }

    public function test_ledger_refuses_to_act_after_the_active_company_changes(): void
    {
        $component = Livewire::test(LedgerScreen::class, ['module' => 'accounting']);

        app(CompanyContext::class)->setCurrent('salon-ayu');
        $component->call('$refresh')->assertForbidden();
    }

    public function test_ledger_is_closed_when_the_finance_capability_is_revoked(): void
    {
        $component = Livewire::test(LedgerScreen::class, ['module' => 'accounting'])->assertOk();

        app(CompanySettingsStore::class)->update('bengkel-arka', static function (array $settings): array {
            $settings['features']['finance.cashbook'] = false;
            $settings['features']['finance.accounting'] = false;

            return $settings;
        });

        $component->call('$refresh')->assertForbidden();
    }

    public function test_ledger_sources_have_no_industry_branch_or_direct_database_access(): void
    {
        $source = implode("\n", [
            file_get_contents(app_path('Livewire/Screens/LedgerScreen.php')),
            file_get_contents(resource_path('views/livewire/screens/ledger.blade.php')),
        ]);

        $this->assertDoesNotMatchRegularExpression('/\b(?:bengkel|klinik|salon|laundry|apotek|kontraktor|agency)\b/i', $source);
        $this->assertStringNotContainsString('DB::', $source);
    }
}
