<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Contracts\EntityRepository;
use App\Livewire\Screens\PipelineScreen;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Kontrak aksesibilitas lintas layar (I6): skip-link di layout,
 * focus trap + restorasi fokus pada dialog perpindahan mundur (D-46).
 */
class AccessibilityContractTest extends TestCase
{
    private string $jsonPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->jsonPath = storage_path('framework/testing/a11y-'.bin2hex(random_bytes(5)));
        config(['datasource.json_path' => $this->jsonPath]);
        Storage::fake('company-json');
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

    public function test_public_shell_has_skip_link_and_theme_target(): void
    {
        session(['active_company' => 'bengkel-arka']);
        $html = $this->get(route('lobby'))->assertOk()->getContent();

        $this->assertStringContainsString('Lewati ke konten utama', $html);
        $this->assertStringContainsString('href="#main-content"', $html);
        $this->assertStringContainsString('id="main-content"', $html);
    }

    public function test_module_shell_has_skip_link(): void
    {
        session(['active_company' => 'bengkel-arka']);
        $html = $this->get(route('app.module', ['module' => 'contacts']))->assertOk()->getContent();

        $this->assertStringContainsString('Lewati ke konten utama', $html);
        $this->assertStringContainsString('id="main-content"', $html);
    }

    public function test_backward_move_dialog_traps_focus_and_restores_opener(): void
    {
        app(CompanyContext::class)->setCurrent('bengkel-arka');
        app(EntityRepository::class)->for('bengkel-arka', 'orders')
            ->save(['id' => 2, 'business_identity_id' => 1, 'order_no' => 'WO-002', 'stage' => 'qc']);

        $component = Livewire::test(PipelineScreen::class, ['module' => 'pos', 'submodule' => 'pipeline']);
        $component->call('move', 2, 'pengerjaan');

        $html = $component->html();
        $this->assertStringContainsString('x-trap.inert.noscroll="true"', $html);
        $this->assertStringContainsString('opener: document.activeElement', $html);
        // Fokus kembali ke pembuka setelah batal (dua pola setara diterima).
        $this->assertTrue(
            str_contains($html, 'cancelMove().then(() => opener?.focus())')
            || str_contains($html, 'cancelMove().then(() => target?.focus())'),
            'Dialog harus mengembalikan fokus ke elemen pembuka saat dibatalkan.'
        );
    }

    /**
     * Kontrak layar sempit 360px (I6): grid multi-kolom, sidebar tetap,
     * dan tabel wajib punya jeda responsif atau pembungkus scroll.
     * Audit statis supaya regresi kelasnya tertangkap tanpa browser.
     */
    public function test_main_screens_have_no_unwrapped_fixed_widths(): void
    {
        $views = [
            'resources/views/livewire/auth/login.blade.php',
            'resources/views/livewire/onboarding.blade.php',
            'resources/views/livewire/lobby.blade.php',
            'resources/views/livewire/dashboard.blade.php',
            'resources/views/livewire/settings.blade.php',
            'resources/views/livewire/sidebar.blade.php',
            'resources/views/livewire/screens/list.blade.php',
            'resources/views/livewire/screens/ledger.blade.php',
            'resources/views/livewire/screens/cashier.blade.php',
            'resources/views/livewire/screens/pipeline.blade.php',
            'resources/views/livewire/screens/calendar.blade.php',
        ];

        foreach ($views as $path) {
            $source = file_get_contents(base_path($path));

            // Grid multi-kolom harus punya prefiks breakpoint (: sebelum nama
            // kelas); tanpa prefiks berarti grid tetap multi-kolom di 360px.
            $this->assertDoesNotMatchRegularExpression(
                '/(?<!:)grid-cols-(?:[2-9]\b|\[[^\]]*\])/',
                preg_replace('/\s+/', ' ', $source),
                "{$path}: grid multi-kolom tanpa prefiks breakpoint",
            );

            // Tabel wajib dibungkus overflow-x-auto (scroll sopan, bukan
            // overflow halaman).
            if (str_contains($source, '<table')) {
                $this->assertStringContainsString('overflow-x-auto', $source, "{$path}: tabel tanpa pembungkus scroll");
            }
        }
    }
}
