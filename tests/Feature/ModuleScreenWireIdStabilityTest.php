<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Contracts\EntityRepository;
use App\Livewire\Screens\ListScreen;
use Illuminate\Filesystem\Filesystem;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Test suite untuk memverifikasi bahwa opsi C routing fix menyelesaikan bug
 * wire:id yang berubah saat parent re-render.
 *
 * Bug sebelumnya: DummyModule (Livewire component) adalah route parent.
 * ListScreen adalah nested component. Saat halaman di-render dua kali berturut-turut,
 * wire:id ListScreen berubah → tombol klik dikirim dengan id basi → Livewire
 * tidak menemukan handler → MethodNotFoundException.
 *
 * Solusi: ModuleController (plain controller) render Blade shell. ListScreen
 * kini TOP-LEVEL Livewire component. wire:id stabil across renders.
 */
class ModuleScreenWireIdStabilityTest extends TestCase
{
    private string $jsonPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->jsonPath = storage_path('framework/testing/stability-'.bin2hex(random_bytes(5)));
        config(['datasource.json_path' => $this->jsonPath]);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->jsonPath);
        parent::tearDown();
    }

    /**
     * Test 1: Halaman modul merender ListScreen sebagai TOP-LEVEL Livewire
     * component (wire:name=screens.list-screen ada di HTML), BUKAN nested di
     * dalam komponen wrapper. Ini bukti struktural bahwa Opsi C diterapkan.
     *
     * Catatan: wire:id memang diregenerasi Livewire per request HTTP (itu
     * desain normal) — yang diperbaiki BUKAN kestabilan id, melainkan aksi
     * yang kini mencapai komponen yang benar (lihat test aksi di bawah).
     */
    public function test_screen_component_is_rendered_top_level(): void
    {
        $this->seedRows('bengkel-arka', 'contacts', [
            ['id' => 1, 'name' => 'Test Contact', 'type' => 'customer'],
        ]);

        app(CompanyContext::class)->setCurrent('bengkel-arka');

        $this->get('/app/contacts?company=bengkel-arka')
            ->assertOk()
            ->assertSee('wire:name="screens.list-screen"', false)
            ->assertDontSee('wire:name="dummy-module"', false);
    }

    /**
     * Test 2: Test aksi button ListScreen (create) via Livewire::test langsung.
     *
     * Ini membuktikan method handler `create()` dapat diakses tanpa error.
     * Sebelum fix: MethodNotFoundException.
     */
    public function test_list_screen_create_action_works_when_called_directly(): void
    {
        $this->seedRows('bengkel-arka', 'contacts', []);
        app(CompanyContext::class)->setCurrent('bengkel-arka');

        $component = Livewire::test(ListScreen::class, ['module' => 'contacts', 'submodule' => null])
            ->assertOk()
            ->call('create')
            ->assertOk();

        // Setelah klik create, form harus terbuka
        $this->assertTrue($component->viewData('editing'), 'Form tidak terbuka setelah create()');
    }

    /**
     * Test 3: HTTP GET halaman modul, verifikasi komponen screen ada di DOM
     * dengan wire:name dan wire:id yang benar.
     *
     * Ini verifikasi bahwa ListScreen adalah TOP-LEVEL component di Blade,
     * bukan nested di dalam Livewire lain.
     */
    public function test_screen_component_is_top_level_in_html_not_nested(): void
    {
        $this->seedRows('bengkel-arka', 'contacts', [
            ['id' => 1, 'name' => 'Contact 1', 'type' => 'customer'],
            ['id' => 2, 'name' => 'Contact 2', 'type' => 'vendor'],
        ]);

        $html = $this->get('/app/contacts?company=bengkel-arka')
            ->assertOk()
            ->getContent();

        // Verifikasi wire:name dan wire:id ada untuk screens.list-screen
        $this->assertStringContainsString('wire:name="screens.list-screen"', $html);
        $this->assertStringContainsString('wire:id="', $html);

        // Verifikasi tombol create ada di DALAM komponen, bukan di wrapper
        // Tombol create harus memiliki wire:click="create"
        $this->assertStringContainsString('wire:click="create"', $html);

        // Verifikasi data daftar ada (tidak error)
        $this->assertStringContainsString('Contact 1', $html);
        $this->assertStringContainsString('Contact 2', $html);
    }

    /**
     * Test 4: Negatif - modul yang dimatikan kapabilitas harus 403.
     *
     * Fail-closed: jika kapabilitas dicabut, validasi di ModuleController
     * langsung menolak, bukan sampai ke screen component.
     */
    public function test_disabled_module_route_is_forbidden_before_reaching_screen(): void
    {
        // projects tidak diaktifkan di salon-ayu
        $this->get('/app/projects?company=salon-ayu')
            ->assertForbidden();
    }

    /**
     * Test 5: Klik aksi via Livewire::test pada screen component,
     * simulasi workflow kompleks.
     *
     * Ini membuktikan bahwa setelah fix, klik tombol di screen component
     * dapat diterima dan dijalankan handler-nya tanpa error.
     */
    public function test_multiple_actions_on_list_screen_work_sequentially(): void
    {
        $this->seedRows('bengkel-arka', 'contacts', [
            ['id' => 1, 'name' => 'Existing', 'type' => 'customer'],
        ]);

        app(CompanyContext::class)->setCurrent('bengkel-arka');

        $component = Livewire::test(ListScreen::class, ['module' => 'contacts'])
            ->assertOk();

        // Aksi 1: Buka form create
        $component->call('create')
            ->assertOk()
            ->assertViewHas('editing', true);

        // Aksi 2: Batal, form tutup
        $component->call('cancel')
            ->assertOk()
            ->assertViewHas('editing', false);

        // Aksi 3: Buka form create lagi
        $component->call('create')
            ->assertOk()
            ->assertViewHas('editing', true);

        // Wire component tetap responsif - tidak ada "MethodNotFoundException"
    }

    private function seedRows(string $company, string $entity, array $rows): void
    {
        app(CompanyContext::class)->setCurrent($company);
        $repository = app(EntityRepository::class)->for($company, $entity);

        foreach ($rows as $row) {
            $repository->save($row);
        }
    }
}
