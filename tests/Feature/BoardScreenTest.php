<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Contracts\CompanySettingsStore;
use App\Contracts\EntityRepository;
use App\Livewire\Screens\BoardScreen;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pola layar `board`: papan okupansi per sumber daya.
 *
 * Yang dijaga di sini adalah kegenerikannya. Kolom papan berasal dari entitas
 * `resources` dan penempatan barisnya dari `references` di schema, sehingga
 * papan meja kasir dan papan check-in memakai satu kelas yang sama tanpa
 * cabang per industri (D-31).
 */
class BoardScreenTest extends TestCase
{
    private string $jsonPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->jsonPath = storage_path('framework/testing/board-'.bin2hex(random_bytes(5)));
        config(['datasource.json_path' => $this->jsonPath]);

        Storage::fake('company-json');

        // Pasangan company/preset di sini murni fixture: yang diuji adalah
        // pola layar, bukan identitas usahanya. `bengkel-arka` dipakai untuk
        // papan berbasis pesanan, `salon-ayu` untuk papan berbasis booking
        // karena preset `rental` menyalakan `bookings.deposit`.
        foreach (['bengkel-arka' => 'bengkel', 'salon-ayu' => 'rental'] as $company => $preset) {
            Storage::disk('company-json')->put(
                "json/{$company}/settings.json",
                json_encode(['preset' => $preset], JSON_THROW_ON_ERROR),
            );
        }

        $this->enableTablesBoard();
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->jsonPath);
        parent::tearDown();
    }

    public function test_columns_are_resources_and_rows_land_on_the_referenced_column(): void
    {
        app(CompanyContext::class)->setCurrent('bengkel-arka');
        $this->seedResources('bengkel-arka');
        $orders = app(EntityRepository::class)->for('bengkel-arka', 'orders');
        $orders->save(['id' => 1, 'business_identity_id' => 1, 'order_no' => 'WO-001', 'stage' => 'masuk', 'resource_id' => 2, 'grand_total' => 150000]);
        $orders->save(['id' => 2, 'business_identity_id' => 1, 'order_no' => 'WO-002', 'stage' => 'pengerjaan', 'resource_id' => 2, 'grand_total' => 50000]);

        $data = Livewire::test(BoardScreen::class, ['module' => 'pos', 'submodule' => 'tables'])
            ->assertOk()
            ->viewData('columns');

        $this->assertSame(['Bay 1', 'Bay 2'], array_column($data, 'label'));
        $this->assertSame([], $data[0]['cards']);
        $this->assertSame(['WO-001', 'WO-002'], array_column($data[1]['cards'], 'title'));

        // Total per kolom dihitung dari field nilai di schema.
        $this->assertSame(200000.0, $data[1]['amount']);
        $this->assertSame(0.0, $data[0]['amount']);
    }

    public function test_rows_in_a_terminal_stage_count_as_closed_instead_of_occupied(): void
    {
        app(CompanyContext::class)->setCurrent('bengkel-arka');
        $this->seedResources('bengkel-arka');
        $orders = app(EntityRepository::class)->for('bengkel-arka', 'orders');
        $orders->save(['id' => 1, 'business_identity_id' => 1, 'order_no' => 'WO-001', 'stage' => 'selesai', 'resource_id' => 1]);
        $orders->save(['id' => 2, 'business_identity_id' => 1, 'order_no' => 'WO-002', 'stage' => 'dibatalkan', 'resource_id' => 1]);
        $orders->save(['id' => 3, 'business_identity_id' => 1, 'order_no' => 'WO-003', 'stage' => 'masuk', 'resource_id' => 1]);

        $component = Livewire::test(BoardScreen::class, ['module' => 'pos', 'submodule' => 'tables'])->assertOk();

        // Tahap terminal diambil dari preset, bukan daftar di dalam layar.
        $this->assertSame(2, $component->viewData('closed'));
        $this->assertSame(1, $component->viewData('occupied'));
        $this->assertSame(['WO-003'], array_column($component->viewData('columns')[0]['cards'], 'title'));
    }

    public function test_rows_without_a_resource_get_their_own_column(): void
    {
        app(CompanyContext::class)->setCurrent('bengkel-arka');
        $this->seedResources('bengkel-arka');
        app(EntityRepository::class)->for('bengkel-arka', 'orders')
            ->save(['id' => 1, 'business_identity_id' => 1, 'order_no' => 'WO-009', 'stage' => 'masuk']);

        $columns = Livewire::test(BoardScreen::class, ['module' => 'pos', 'submodule' => 'tables'])
            ->viewData('columns');

        // Baris tanpa sumber daya tidak boleh hilang diam-diam.
        $last = end($columns);
        $this->assertSame('unassigned', $last['key']);
        $this->assertSame(['WO-009'], array_column($last['cards'], 'title'));
    }

    public function test_unassigned_column_is_absent_when_every_row_is_placed(): void
    {
        app(CompanyContext::class)->setCurrent('bengkel-arka');
        $this->seedResources('bengkel-arka');
        app(EntityRepository::class)->for('bengkel-arka', 'orders')
            ->save(['id' => 1, 'business_identity_id' => 1, 'order_no' => 'WO-001', 'stage' => 'masuk', 'resource_id' => 1]);

        $columns = Livewire::test(BoardScreen::class, ['module' => 'pos', 'submodule' => 'tables'])
            ->viewData('columns');

        $this->assertNotContains('unassigned', array_column($columns, 'key'));
    }

    public function test_deposit_totals_appear_only_for_entities_whose_schema_declares_them(): void
    {
        // Pesanan tidak punya deposit di schema.
        app(CompanyContext::class)->setCurrent('bengkel-arka');
        $this->seedResources('bengkel-arka');
        $this->assertFalse(
            Livewire::test(BoardScreen::class, ['module' => 'pos', 'submodule' => 'tables'])->viewData('hasDeposit')
        );

        // Booking punya `deposit_amount`, jadi kolom deposit muncul sendiri.
        app(CompanyContext::class)->setCurrent('salon-ayu');
        $this->seedResources('salon-ayu');
        app(EntityRepository::class)->for('salon-ayu', 'bookings')->save([
            'id' => 1,
            'resource_id' => 1,
            'starts_at' => '2026-09-22T09:00:00+07:00',
            'ends_at' => '2026-09-22T11:00:00+07:00',
            'stage' => 'dipesan',
            'deposit_amount' => 75000,
        ]);

        $component = Livewire::test(BoardScreen::class, ['module' => 'bookings', 'submodule' => 'checkin'])->assertOk();

        $this->assertTrue($component->viewData('hasDeposit'));
        $this->assertSame(75000.0, $component->viewData('columns')[0]['deposit']);
    }

    public function test_board_is_reachable_through_the_module_route_by_convention(): void
    {
        Storage::disk('company-json')->put(
            'json/bengkel-arka/business_identity.json',
            json_encode(['id' => 1, 'preset' => 'bengkel', 'tax_mode' => 'non_taxable'], JSON_THROW_ON_ERROR),
        );
        app(CompanyContext::class)->setCurrent('bengkel-arka');
        $this->seedResources('bengkel-arka');

        // Pola `board` tidak lagi jatuh ke kartu kontrak.
        $this->get('/app/pos/tables?company=bengkel-arka')
            ->assertOk()
            ->assertViewHas('screenComponent', 'screens.board-screen')
            ->assertDontSee('Kontrak layar aktif');
    }

    public function test_screen_fails_closed_when_the_active_company_changes_after_mount(): void
    {
        app(CompanyContext::class)->setCurrent('bengkel-arka');
        $this->seedResources('bengkel-arka');
        $component = Livewire::test(BoardScreen::class, ['module' => 'pos', 'submodule' => 'tables'])->assertOk();

        app(CompanyContext::class)->setCurrent('salon-ayu');
        $component->call('$refresh')->assertForbidden();
    }

    public function test_board_is_closed_when_the_capability_is_revoked(): void
    {
        app(CompanyContext::class)->setCurrent('bengkel-arka');
        $this->seedResources('bengkel-arka');
        $component = Livewire::test(BoardScreen::class, ['module' => 'pos', 'submodule' => 'tables'])->assertOk();

        app(CompanySettingsStore::class)->update('bengkel-arka', static function (array $settings): array {
            $settings['features']['pos.tables'] = false;

            return $settings;
        });

        // Mencabut capability harus menutup layar yang sedang terbuka.
        $component->call('$refresh')->assertForbidden();
    }

    public function test_board_screen_contains_no_industry_names(): void
    {
        $source = implode("\n", [
            file_get_contents(app_path('Livewire/Screens/BoardScreen.php')),
            file_get_contents(resource_path('views/livewire/screens/board.blade.php')),
        ]);

        foreach (['meja', 'kamar', 'bay', 'kandang', 'restoran', 'kafe', 'bengkel', 'laundry'] as $word) {
            $this->assertStringNotContainsStringIgnoringCase(
                $word,
                $source,
                "Pola layar tidak boleh menyebut istilah industri: {$word}"
            );
        }
    }

    private function enableTablesBoard(): void
    {
        app(CompanySettingsStore::class)->update('bengkel-arka', static function (array $settings): array {
            $settings['features']['pos.tables'] = true;

            return $settings;
        });
    }

    private function seedResources(string $company): void
    {
        $resources = app(EntityRepository::class)->for($company, 'resources');
        $resources->save(['id' => 1, 'type' => 'slot', 'name' => 'Bay 1', 'status' => 'available', 'capacity' => 4]);
        $resources->save(['id' => 2, 'type' => 'slot', 'name' => 'Bay 2', 'status' => 'available', 'capacity' => 2]);
    }
}
