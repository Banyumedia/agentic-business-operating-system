<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Contracts\CompanySettingsStore;
use App\Contracts\EntityRepository;
use App\Livewire\Screens\PipelineScreen;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;
use Throwable;

class PipelineScreenTest extends TestCase
{
    private string $jsonPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->jsonPath = storage_path('framework/testing/pipe-'.bin2hex(random_bytes(5)));
        config(['datasource.json_path' => $this->jsonPath]);

        // Disk company dipalsukan supaya settings dan workflow log tidak pernah
        // menyentuh storage nyata (log workflow tidak digitignore).
        Storage::fake('company-json');
        foreach (['bengkel-arka' => 'bengkel', 'klinik-sehat' => 'klinik', 'salon-ayu' => 'salon'] as $company => $preset) {
            Storage::disk('company-json')->put(
                "json/{$company}/settings.json",
                json_encode(['preset' => $preset], JSON_THROW_ON_ERROR),
            );
        }
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->jsonPath);
        parent::tearDown();
    }

    public function test_columns_come_from_the_company_workflow_for_every_preset(): void
    {
        $expected = [
            // bengkel: alur ada pada work order
            ['bengkel-arka', 'pos', 'pipeline', ['Masuk', 'Pemeriksaan', 'Pengerjaan', 'QC', 'Siap Diambil', 'Selesai', 'Dibatalkan']],
            // klinik: alur ada pada janji temu
            ['klinik-sehat', 'bookings', 'pipeline', ['Dijadwalkan', 'Check-in', 'Diperiksa', 'Selesai', 'Dibatalkan']],
            // salon: alur janji/jadwal dengan tahap "dilayani"
            ['salon-ayu', 'bookings', 'pipeline', ['Dijadwalkan', 'Check-in', 'Dilayani', 'Selesai', 'Dibatalkan']],
        ];

        foreach ($expected as [$company, $module, $submodule, $labels]) {
            app(CompanyContext::class)->setCurrent($company);

            $columns = Livewire::test(PipelineScreen::class, ['module' => $module, 'submodule' => $submodule])
                ->assertOk()
                ->viewData('columns');

            $this->assertSame($labels, array_column($columns, 'label'), "Kolom papan {$company} tidak sesuai alur preset.");
        }
    }

    public function test_card_title_and_grouping_come_from_schema_and_stage(): void
    {
        $this->seedOrders();

        $columns = Livewire::test(PipelineScreen::class, ['module' => 'pos', 'submodule' => 'pipeline'])
            ->viewData('columns');

        $byStage = array_column($columns, null, 'code');
        $this->assertSame(['WO-001'], array_column($byStage['masuk']['cards'], 'title'));
        $this->assertSame(['WO-002'], array_column($byStage['qc']['cards'], 'title'));
        $this->assertSame([], $byStage['selesai']['cards']);
    }

    public function test_declared_transition_moves_the_stage_through_the_repository(): void
    {
        $this->seedOrders();
        $repository = app(EntityRepository::class)->for('bengkel-arka', 'orders');

        Livewire::test(PipelineScreen::class, ['module' => 'pos', 'submodule' => 'pipeline'])
            ->call('move', 1, 'pemeriksaan')
            ->assertSet('failure', null)
            ->assertSee('Tahap diperbarui');

        $this->assertSame('pemeriksaan', $repository->find(1)['stage']);
    }

    public function test_undeclared_transition_is_refused_without_changing_data(): void
    {
        $this->seedOrders();
        $repository = app(EntityRepository::class)->for('bengkel-arka', 'orders');

        Livewire::test(PipelineScreen::class, ['module' => 'pos', 'submodule' => 'pipeline'])
            ->call('move', 1, 'selesai')
            ->assertSet('notice', null)
            ->assertSee('tidak tersedia');

        $this->assertSame('masuk', $repository->find(1)['stage']);
    }

    public function test_owner_only_transition_is_hidden_from_staff_and_refused(): void
    {
        $this->seedOrders();
        $repository = app(EntityRepository::class)->for('bengkel-arka', 'orders');
        session()->forget('company_role');

        $component = Livewire::test(PipelineScreen::class, ['module' => 'pos', 'submodule' => 'pipeline']);

        // Pembatalan hanya untuk owner, jadi tidak ditawarkan ke staf.
        $cards = array_column($component->viewData('columns'), null, 'code')['masuk']['cards'];
        $this->assertNotContains('dibatalkan', array_column($cards[0]['transitions'], 'to'));

        $component->call('move', 1, 'dibatalkan')->assertSee('tidak tersedia');
        $this->assertSame('masuk', $repository->find(1)['stage']);
    }

    public function test_backward_transition_requires_a_recorded_reason(): void
    {
        $this->seedOrders();
        $repository = app(EntityRepository::class)->for('bengkel-arka', 'orders');

        $component = Livewire::test(PipelineScreen::class, ['module' => 'pos', 'submodule' => 'pipeline']);

        // Mundur dari QC ke pengerjaan: dialog alasan dibuka, belum berpindah.
        $component->call('move', 2, 'pengerjaan')
            ->assertSet('pendingStage', 'pengerjaan')
            ->assertSee('role="dialog"', false)
            ->assertSee('Alasan');
        $this->assertSame('qc', $repository->find(2)['stage']);

        // Alasan kosong ditolak.
        $component->set('note', '   ')->call('confirmMove')->assertSee('wajib disertai alasan');
        $this->assertSame('qc', $repository->find(2)['stage']);

        // Dengan alasan, perpindahan tersimpan dan alasan tercatat di log.
        $component->set('note', 'Baut pengikat kurang kencang')->call('confirmMove')->assertSet('failure', null);
        $this->assertSame('pengerjaan', $repository->find(2)['stage']);

        $log = json_decode(
            Storage::disk('company-json')->get('json/bengkel-arka/workflow_log.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $this->assertSame('Baut pengikat kurang kencang', end($log)['note']);
    }

    public function test_transition_needing_approval_is_held_and_not_persisted(): void
    {
        $this->seedOrders();
        session(['company_role' => 'owner']);
        $repository = app(EntityRepository::class)->for('bengkel-arka', 'orders');

        Livewire::test(PipelineScreen::class, ['module' => 'pos', 'submodule' => 'pipeline'])
            ->call('move', 1, 'dibatalkan')
            ->assertSet('failure', null)
            ->assertSee('ditahan sampai pemilik menyetujui');

        // Stage tidak boleh ikut tersimpan selama masih menunggu persetujuan.
        $this->assertSame('masuk', $repository->find(1)['stage']);
    }

    public function test_rows_with_a_stage_outside_the_workflow_are_reported_not_hidden(): void
    {
        $this->seedOrders();
        app(EntityRepository::class)->for('bengkel-arka', 'orders')->save([
            'id' => 9,
            'business_identity_id' => 1,
            'order_no' => 'WO-009',
            'stage' => 'tahap-asing',
        ]);

        Livewire::test(PipelineScreen::class, ['module' => 'pos', 'submodule' => 'pipeline'])
            ->assertSet('failure', null)
            ->assertViewHas('orphans', 1)
            ->assertSee('di luar alur kerja aktif');
    }

    public function test_board_refuses_to_act_after_the_active_company_changes(): void
    {
        $this->seedOrders();
        $component = Livewire::test(PipelineScreen::class, ['module' => 'pos', 'submodule' => 'pipeline']);

        app(CompanyContext::class)->setCurrent('salon-ayu');
        $component->call('move', 1, 'pemeriksaan')->assertForbidden();
    }

    public function test_board_is_closed_when_the_capability_is_revoked(): void
    {
        $this->seedOrders();
        $component = Livewire::test(PipelineScreen::class, ['module' => 'pos', 'submodule' => 'pipeline'])->assertOk();

        app(CompanySettingsStore::class)->update('bengkel-arka', static function (array $settings): array {
            $settings['features']['pos'] = false;

            return $settings;
        });

        $component->call('$refresh')->assertForbidden();
    }

    public function test_route_and_tenant_state_cannot_be_forced_by_the_client(): void
    {
        $this->seedOrders();

        // Setiap properti yang menentukan tenant, route, dan target aksi wajib
        // ditolak saat klien mencoba mengubahnya.
        foreach ([
            'module' => 'contacts',
            'submodule' => 'history',
            'company' => 'salon-ayu',
            'pendingId' => 99,
            'pendingStage' => 'selesai',
        ] as $property => $value) {
            $component = Livewire::test(PipelineScreen::class, ['module' => 'pos', 'submodule' => 'pipeline']);

            try {
                $component->set($property, $value);
                $this->fail("Properti {$property} seharusnya tidak dapat diubah klien.");
            } catch (Throwable) {
                $this->assertTrue(true);
            }
        }

        // Data tidak berubah setelah seluruh percobaan.
        $this->assertSame('masuk', app(EntityRepository::class)->for('bengkel-arka', 'orders')->find(1)['stage']);
    }

    public function test_pipeline_sources_have_no_industry_branch_or_hardcoded_stage(): void
    {
        $source = implode("\n", [
            file_get_contents(app_path('Livewire/Screens/PipelineScreen.php')),
            file_get_contents(resource_path('views/livewire/screens/pipeline.blade.php')),
        ]);

        $this->assertDoesNotMatchRegularExpression('/\b(?:bengkel|klinik|salon|laundry|apotek|kontraktor|agency)\b/i', $source);
        $this->assertDoesNotMatchRegularExpression('/\b(?:masuk|pemeriksaan|pengerjaan|qc|siap_diambil|dijadwalkan|check_in|dilayani|diperiksa)\b/', $source);
        $this->assertStringNotContainsString('DB::', $source);
    }

    private function seedOrders(): void
    {
        app(CompanyContext::class)->setCurrent('bengkel-arka');
        $repository = app(EntityRepository::class)->for('bengkel-arka', 'orders');

        $repository->save(['id' => 1, 'business_identity_id' => 1, 'order_no' => 'WO-001', 'stage' => 'masuk']);
        $repository->save(['id' => 2, 'business_identity_id' => 1, 'order_no' => 'WO-002', 'stage' => 'qc']);
    }
}
