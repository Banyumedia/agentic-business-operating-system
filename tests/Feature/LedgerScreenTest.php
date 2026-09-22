<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Contracts\CompanySettingsStore;
use App\Contracts\EntityRepository;
use App\Livewire\Screens\LedgerScreen;
use App\Livewire\Screens\ReportScreen;
use App\Services\DynamicMenuRegistry;
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

    public function test_saas_invoices_are_not_exposed_as_an_operational_ledger(): void
    {
        $invoices = app(EntityRepository::class)->for('bengkel-arka', 'invoices');
        $invoices->save(['id' => 1, 'type' => 'subscription', 'order_id' => 'INV-1', 'amount' => 750000, 'period_start' => '2026-09-01']);
        $invoices->save(['id' => 2, 'type' => 'topup', 'order_id' => 'INV-2', 'amount' => 250000, 'period_start' => '2026-09-05']);

        $invoiceRoute = app(DynamicMenuRegistry::class)->routeDefinition('accounting', 'invoices');
        $this->assertSame('contract', $invoiceRoute['screen']);
    }

    public function test_empty_ledger_uses_company_terminology(): void
    {
        Livewire::test(LedgerScreen::class, ['module' => 'accounting'])->assertSee('Belum ada');
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

    /**
     * Diuji di seam `EntityRepository` (lihat `bindRawRows`), bukan lewat
     * driver: pada driver JSON `SchemaValidator` menolak arah di luar `in|out`
     * saat menulis **dan** saat membaca, jadi baris seperti ini tidak dapat
     * lolos dari sana. Jaminan yang dikunci test ini adalah milik layar, bukan
     * milik driver.
     *
     * Cabang lama hanya memeriksa `=== 'out'`, sehingga apa pun selain itu
     * **menambah saldo**. Itu juga membuat Buku Kas dan Laporan Keuangan
     * berselisih untuk data yang sama: `ReportScreen` sudah melewatkan baris
     * berarah tidak dikenal.
     */
    public function test_unknown_direction_is_excluded_from_totals_instead_of_counted_as_income(): void
    {
        $this->bindRawRows('cash_entries', [
            ['id' => 1, 'entry_date' => '2026-09-15', 'direction' => 'in', 'amount' => 100000, 'description' => 'Setoran'],
            ['id' => 2, 'entry_date' => '2026-09-16', 'direction' => 'transfer', 'amount' => 900000, 'description' => 'Arah tidak dikenal'],
        ]);

        $component = Livewire::test(LedgerScreen::class, ['module' => 'accounting'])->assertOk();

        $this->assertEquals(100000, $component->viewData('incoming'));
        $this->assertEquals(0, $component->viewData('outgoing'));
        $this->assertEquals(100000, $component->viewData('balance'));
        $this->assertSame(1, $component->viewData('rejected'));

        // Tetap terlihat: baris bermasalah yang disembunyikan tidak akan pernah
        // diperbaiki siapa pun.
        $entries = $component->viewData('entries');
        $this->assertSame([true, false], array_column($entries, 'rejected'));
        $component->assertSee('Arah tidak dikenal')->assertSee('Perlu diperbaiki');
    }

    public function test_invalid_amount_is_excluded_from_totals(): void
    {
        $this->bindRawRows('cash_entries', [
            ['id' => 1, 'entry_date' => '2026-09-15', 'direction' => 'in', 'amount' => 250000],
            // Nominal negatif pada arah `in` dulu mengurangi saldo tanpa penanda.
            ['id' => 2, 'entry_date' => '2026-09-16', 'direction' => 'in', 'amount' => -400000],
            ['id' => 3, 'entry_date' => '2026-09-17', 'direction' => 'out', 'amount' => 'bukan angka'],
        ]);

        $component = Livewire::test(LedgerScreen::class, ['module' => 'accounting'])->assertOk();

        $this->assertEquals(250000, $component->viewData('incoming'));
        $this->assertEquals(0, $component->viewData('outgoing'));
        $this->assertEquals(250000, $component->viewData('balance'));
        $this->assertSame(2, $component->viewData('rejected'));
    }

    /**
     * Dua layar yang membaca buku kas yang sama harus menjawab sama. Tanpa
     * penjagaan ini, saldo di Buku Kas bisa lebih besar daripada laporan dan
     * tidak ada yang tahu angka mana yang benar.
     */
    public function test_ledger_and_financial_report_agree_on_the_same_entries(): void
    {
        $this->bindRawRows('cash_entries', [
            ['id' => 1, 'entry_date' => '2026-09-15', 'direction' => 'in', 'amount' => 300000],
            ['id' => 2, 'entry_date' => '2026-09-16', 'direction' => 'out', 'amount' => 120000],
            ['id' => 3, 'entry_date' => '2026-09-17', 'direction' => 'entah', 'amount' => 999000],
        ]);

        $ledger = Livewire::test(LedgerScreen::class, ['module' => 'accounting']);
        $report = Livewire::test(ReportScreen::class, ['module' => 'accounting', 'submodule' => 'reports']);

        $this->assertEquals($report->viewData('balance'), $ledger->viewData('balance'));
        $this->assertEquals($report->viewData('totalIncome'), $ledger->viewData('incoming'));
        $this->assertEquals($report->viewData('totalExpense'), $ledger->viewData('outgoing'));
    }

    /**
     * Kolom "Keterangan" dulu memakai `SchemaPresenter::titleField()`, yang
     * untuk buku kas jatuh ke `entry_date` - kolom string pertama pada schema.
     * Akibatnya tanggal tampil dua kali dan keterangan yang ditulis operator
     * tidak pernah terlihat.
     */
    public function test_description_column_shows_the_note_not_the_date(): void
    {
        $cash = app(EntityRepository::class)->for('bengkel-arka', 'cash_entries');
        $cash->save(['id' => 1, 'entry_date' => '2026-09-15', 'direction' => 'in', 'amount' => 100000, 'description' => 'Setoran kasir pagi']);
        $cash->save(['id' => 2, 'entry_date' => '2026-09-16', 'direction' => 'out', 'amount' => 30000, 'category' => 'Konsumsi']);

        $entries = Livewire::test(LedgerScreen::class, ['module' => 'accounting'])->viewData('entries');

        $this->assertSame(['Konsumsi', 'Setoran kasir pagi'], array_column($entries, 'description'));
    }

    public function test_balance_is_not_rewritten_by_a_filter(): void
    {
        $cash = app(EntityRepository::class)->for('bengkel-arka', 'cash_entries');
        $cash->save(['id' => 1, 'entry_date' => '2026-08-31', 'direction' => 'in', 'amount' => 1000000]);
        $cash->save(['id' => 2, 'entry_date' => '2026-09-16', 'direction' => 'out', 'amount' => 250000]);

        $component = Livewire::test(LedgerScreen::class, ['module' => 'accounting'])
            ->set('period', '2026-09');

        // Saldo adalah posisi kas usaha, bukan jumlah baris yang sedang dilihat.
        $this->assertEquals(750000, $component->viewData('balance'));
        $this->assertEquals(0, $component->viewData('incoming'));
        $this->assertEquals(250000, $component->viewData('outgoing'));
        $this->assertSame(['2026-09-16'], array_column($component->viewData('entries'), 'date'));
    }

    public function test_direction_filter_never_shows_the_other_direction(): void
    {
        $cash = app(EntityRepository::class)->for('bengkel-arka', 'cash_entries');
        $cash->save(['id' => 1, 'entry_date' => '2026-09-15', 'direction' => 'in', 'amount' => 100000]);
        $cash->save(['id' => 2, 'entry_date' => '2026-09-16', 'direction' => 'out', 'amount' => 20000]);

        $entries = Livewire::test(LedgerScreen::class, ['module' => 'accounting'])
            ->set('direction', 'out')
            ->viewData('entries');

        $this->assertSame([true], array_column($entries, 'outgoing'));
    }

    public function test_unknown_filter_values_fall_back_to_showing_everything(): void
    {
        $cash = app(EntityRepository::class)->for('bengkel-arka', 'cash_entries');
        $cash->save(['id' => 1, 'entry_date' => '2026-09-15', 'direction' => 'in', 'amount' => 100000]);

        $component = Livewire::test(LedgerScreen::class, ['module' => 'accounting'])
            ->set('period', '../../etc/passwd')
            ->set('direction', 'semua-uang')
            ->assertOk();

        $this->assertCount(1, $component->viewData('entries'));
        $this->assertEquals(100000, $component->viewData('balance'));
    }

    public function test_period_options_come_from_recorded_entries(): void
    {
        $cash = app(EntityRepository::class)->for('bengkel-arka', 'cash_entries');
        $cash->save(['id' => 1, 'entry_date' => '2026-07-02', 'direction' => 'in', 'amount' => 1000]);
        $cash->save(['id' => 2, 'entry_date' => '2026-09-02', 'direction' => 'in', 'amount' => 1000]);
        $cash->save(['id' => 3, 'entry_date' => '2026-09-20', 'direction' => 'in', 'amount' => 1000]);

        $periods = Livewire::test(LedgerScreen::class, ['module' => 'accounting'])->viewData('periods');

        $this->assertSame(['2026-09', '2026-07'], array_column($periods, 'value'));
        $this->assertSame('September 2026', $periods[0]['label']);
    }

    public function test_relation_filter_only_offers_rows_of_the_active_company(): void
    {
        app(CompanyContext::class)->setCurrent('salon-ayu');
        app(EntityRepository::class)->for('salon-ayu', 'projects')->save(['id' => 9, 'name' => 'Proyek usaha lain']);
        app(CompanyContext::class)->setCurrent('bengkel-arka');
        app(EntityRepository::class)->for('bengkel-arka', 'projects')->save(['id' => 7, 'name' => 'Servis Avanza']);

        $cash = app(EntityRepository::class)->for('bengkel-arka', 'cash_entries');
        $cash->save(['id' => 1, 'entry_date' => '2026-09-15', 'direction' => 'in', 'amount' => 500000, 'project_id' => 7]);
        $cash->save(['id' => 2, 'entry_date' => '2026-09-16', 'direction' => 'out', 'amount' => 75000, 'project_id' => null]);

        $component = Livewire::test(LedgerScreen::class, ['module' => 'accounting'])->assertOk();

        $filters = $component->viewData('relationFilters');
        $projectFilter = collect($filters)->firstWhere('field', 'project_id');
        $this->assertNotNull($projectFilter);
        $this->assertSame([7 => 'Servis Avanza'], $projectFilter['options']);
        // Istilah usaha, bukan nama kolom (D-31).
        $this->assertSame('Pekerjaan', $projectFilter['label']);

        $component->set('relation.project_id', '7');
        $this->assertSame([1], array_column($component->viewData('entries'), 'id'));
        // Saldo tetap posisi kas seluruh riwayat: 500.000 masuk - 75.000 keluar.
        $this->assertEquals(425000, $component->viewData('balance'));

        // Entri tanpa pembebanan tetap dapat ditemukan, tidak hilang diam-diam.
        $component->set('relation.project_id', 'none');
        $this->assertSame([2], array_column($component->viewData('entries'), 'id'));
    }

    /**
     * Menyisipkan baris yang tidak melewati `SchemaValidator` di seam
     * `EntityRepository`, bukan lewat driver.
     *
     * Pada driver JSON, validasi berlaku **saat tulis maupun saat baca**
     * (`JsonEntityRepository::readRows()`), jadi baris seperti ini tidak dapat
     * dibuat maupun dibaca dari sana - dan itu memang lapisan pertahanan yang
     * benar. Yang diuji di sini adalah pertahanan kedua: layar tidak boleh
     * mengubah baris yang tidak dapat dibaca menjadi uang masuk, apa pun
     * driver-nya. Tanpa test di seam ini, jaminan itu hanya berlaku selama
     * driver yang sedang dipakai kebetulan memvalidasi saat membaca.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function bindRawRows(string $entity, array $rows): void
    {
        $inner = app(EntityRepository::class);

        app()->instance(EntityRepository::class, new class($inner, $entity, $rows) implements EntityRepository
        {
            /** @param list<array<string, mixed>> $rows */
            public function __construct(
                private EntityRepository $inner,
                private string $entity,
                private array $rows,
                private ?string $scoped = null,
            ) {}

            public function for(string $company, string $entity): static
            {
                $clone = clone $this;
                $clone->inner = $this->inner->for($company, $entity);
                $clone->scoped = $entity;

                return $clone;
            }

            public function all(): array
            {
                return $this->scoped === $this->entity ? $this->rows : $this->inner->all();
            }

            public function find(string|int $id): ?array
            {
                return $this->inner->find($id);
            }

            public function save(array $record): array
            {
                return $this->inner->save($record);
            }

            public function saveAggregate(
                array $parent,
                string $childEntity,
                string $foreignKey,
                array $children,
                ?string $idempotencyField = null,
                array $guards = [],
            ): array {
                return $this->inner->saveAggregate($parent, $childEntity, $foreignKey, $children, $idempotencyField, $guards);
            }

            public function delete(string|int $id): bool
            {
                return $this->inner->delete($id);
            }

            public function query(array $filters = []): array
            {
                return $this->inner->query($filters);
            }
        });
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
