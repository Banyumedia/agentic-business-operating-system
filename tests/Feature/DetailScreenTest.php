<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Contracts\EntityRepository;
use App\Livewire\Screens\DetailScreen;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * MP-01: pola layar detail generik. Satu kelas untuk order/proyek/kontak
 * (dan entitas lain yang mendapat seam `detail` dari MP-00) - bukan tiga
 * layar per entitas.
 */
class DetailScreenTest extends TestCase
{
    private string $jsonPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->jsonPath = storage_path('framework/testing/detail-'.bin2hex(random_bytes(5)));
        config(['datasource.json_path' => $this->jsonPath]);

        Storage::fake('company-json');
        foreach (['bengkel-arka', 'salon-ayu'] as $company) {
            Storage::disk('company-json')->put(
                "json/{$company}/settings.json",
                json_encode(['preset' => 'bengkel'], JSON_THROW_ON_ERROR),
            );
        }
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->jsonPath);
        parent::tearDown();
    }

    public function test_fields_render_from_schema_and_title_falls_back_to_id(): void
    {
        app(CompanyContext::class)->setCurrent('bengkel-arka');
        app(EntityRepository::class)->for('bengkel-arka', 'orders')->save([
            'id' => 1,
            'business_identity_id' => 1,
            'order_no' => 'WO-001',
            'stage' => 'masuk',
        ]);

        $component = Livewire::test(DetailScreen::class, ['module' => 'pos', 'id' => 1]);

        $component->assertSee('WO-001');
        $this->assertSame('WO-001', $component->viewData('title'));
    }

    public function test_negative_id_belonging_to_another_company_is_not_found(): void
    {
        app(CompanyContext::class)->setCurrent('salon-ayu');
        app(EntityRepository::class)->for('salon-ayu', 'orders')->save([
            'id' => 42,
            'business_identity_id' => 1,
            'order_no' => 'WO-LAIN',
            'stage' => 'masuk',
        ]);

        app(CompanyContext::class)->setCurrent('bengkel-arka');

        Livewire::test(DetailScreen::class, ['module' => 'pos', 'id' => 42])
            ->assertStatus(404);
    }

    public function test_negative_unknown_id_is_not_found(): void
    {
        app(CompanyContext::class)->setCurrent('bengkel-arka');

        Livewire::test(DetailScreen::class, ['module' => 'pos', 'id' => 999])
            ->assertStatus(404);
    }

    public function test_screen_fails_closed_when_the_active_company_changes_after_mount(): void
    {
        app(CompanyContext::class)->setCurrent('bengkel-arka');
        app(EntityRepository::class)->for('bengkel-arka', 'orders')->save([
            'id' => 1,
            'business_identity_id' => 1,
            'order_no' => 'WO-001',
            'stage' => 'masuk',
        ]);

        $component = Livewire::test(DetailScreen::class, ['module' => 'pos', 'id' => 1])
            ->assertOk();

        app(CompanyContext::class)->setCurrent('salon-ayu');

        $component->call('$refresh')->assertStatus(403);
    }

    public function test_declared_transition_moves_the_stage_through_the_repository(): void
    {
        app(CompanyContext::class)->setCurrent('bengkel-arka');
        $repository = app(EntityRepository::class)->for('bengkel-arka', 'orders');
        $repository->save(['id' => 1, 'business_identity_id' => 1, 'order_no' => 'WO-001', 'stage' => 'masuk']);

        Livewire::test(DetailScreen::class, ['module' => 'pos', 'id' => 1])
            ->call('move', 'pemeriksaan')
            ->assertSet('failure', null)
            ->assertSee('Tahap diperbarui');

        $this->assertSame('pemeriksaan', $repository->find(1)['stage']);
    }

    public function test_negative_undeclared_transition_is_refused_without_changing_data(): void
    {
        app(CompanyContext::class)->setCurrent('bengkel-arka');
        $repository = app(EntityRepository::class)->for('bengkel-arka', 'orders');
        $repository->save(['id' => 1, 'business_identity_id' => 1, 'order_no' => 'WO-001', 'stage' => 'masuk']);

        Livewire::test(DetailScreen::class, ['module' => 'pos', 'id' => 1])
            ->call('move', 'selesai')
            ->assertSet('notice', null)
            ->assertSee('tidak tersedia');

        $this->assertSame('masuk', $repository->find(1)['stage']);
    }

    public function test_only_valid_transitions_from_the_current_stage_are_offered(): void
    {
        // D-46/UX_UI_SPEC 6.9: bukan pemilih tahap bebas - hanya transisi sah
        // dari tahap sekarang untuk peran saat ini.
        app(CompanyContext::class)->setCurrent('bengkel-arka');
        app(EntityRepository::class)->for('bengkel-arka', 'orders')->save([
            'id' => 1,
            'business_identity_id' => 1,
            'order_no' => 'WO-001',
            'stage' => 'qc',
        ]);

        $transitions = Livewire::test(DetailScreen::class, ['module' => 'pos', 'id' => 1])
            ->viewData('transitions');

        $offered = array_column($transitions, 'to');
        $this->assertContains('pengerjaan', $offered);
        $this->assertContains('siap_diambil', $offered);
        $this->assertNotContains('selesai', $offered);
    }

    public function test_backward_transition_requires_a_recorded_reason(): void
    {
        app(CompanyContext::class)->setCurrent('bengkel-arka');
        $repository = app(EntityRepository::class)->for('bengkel-arka', 'orders');
        $repository->save(['id' => 1, 'business_identity_id' => 1, 'order_no' => 'WO-001', 'stage' => 'qc']);

        $component = Livewire::test(DetailScreen::class, ['module' => 'pos', 'id' => 1])
            ->call('move', 'pengerjaan');

        $this->assertSame('qc', $repository->find(1)['stage']);

        $component->call('confirmMove')->assertSee('wajib disertai alasan');
        $this->assertSame('qc', $repository->find(1)['stage']);

        $component->set('note', 'Cat ulang bagian bumper')->call('confirmMove');
        $this->assertSame('pengerjaan', $repository->find(1)['stage']);
    }

    public function test_list_screen_row_links_to_detail_for_a_module_with_the_seam(): void
    {
        app(CompanyContext::class)->setCurrent('bengkel-arka');
        app(EntityRepository::class)->for('bengkel-arka', 'contacts')->save([
            'id' => 1,
            'name' => 'Pelanggan Uji',
            'type' => 'customer',
        ]);

        $html = $this->get('/app/contacts?company=bengkel-arka')->assertOk()->getContent();

        $this->assertStringContainsString(route('app.module.detail', ['module' => 'contacts', 'id' => 1]), $html);
    }

    public function test_negative_list_screen_row_does_not_link_to_detail_for_a_module_without_the_seam(): void
    {
        // `accounting/entries` (cash_entries) tidak punya seam `detail` -
        // baris TIDAK boleh menautkan ke mana pun, bukan tautan mati.
        app(CompanyContext::class)->setCurrent('bengkel-arka');
        app(EntityRepository::class)->for('bengkel-arka', 'cash_entries')->save([
            'id' => 1,
            'entry_date' => '2026-09-23',
            'direction' => 'in',
            'amount' => 50000,
        ]);

        $html = $this->get('/app/accounting/entries?company=bengkel-arka')->assertOk()->getContent();

        $this->assertStringNotContainsString('/detail/', $html);
    }

    public function test_child_rows_render_from_schema_relations(): void
    {
        app(CompanyContext::class)->setCurrent('bengkel-arka');
        app(EntityRepository::class)->for('bengkel-arka', 'orders')->save([
            'id' => 1,
            'business_identity_id' => 1,
            'order_no' => 'WO-001',
            'stage' => 'masuk',
        ]);
        app(EntityRepository::class)->for('bengkel-arka', 'order_lines')->save([
            'id' => 1,
            'order_id' => 1,
            'description' => 'Ganti Oli',
            'qty' => 1,
            'unit_price' => 75000,
            'line_total' => 75000,
        ]);

        Livewire::test(DetailScreen::class, ['module' => 'pos', 'id' => 1])
            ->assertSee('Ganti Oli');
    }

    public function test_negative_child_rows_from_another_order_do_not_leak(): void
    {
        app(CompanyContext::class)->setCurrent('bengkel-arka');
        app(EntityRepository::class)->for('bengkel-arka', 'orders')->save([
            'id' => 1, 'business_identity_id' => 1, 'order_no' => 'WO-001', 'stage' => 'masuk',
        ]);
        app(EntityRepository::class)->for('bengkel-arka', 'orders')->save([
            'id' => 2, 'business_identity_id' => 1, 'order_no' => 'WO-002', 'stage' => 'masuk',
        ]);
        app(EntityRepository::class)->for('bengkel-arka', 'order_lines')->save([
            'id' => 1, 'order_id' => 2, 'description' => 'Baris Order Lain', 'qty' => 1, 'unit_price' => 1000, 'line_total' => 1000,
        ]);

        Livewire::test(DetailScreen::class, ['module' => 'pos', 'id' => 1])
            ->assertDontSee('Baris Order Lain');
    }
}
