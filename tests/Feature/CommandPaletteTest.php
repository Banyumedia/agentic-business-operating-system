<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Contracts\EntityRepository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class CommandPaletteTest extends TestCase
{
    private string $jsonPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Data entitas diisolasi supaya test ini tidak menulis ke data demo nyata.
        $this->jsonPath = storage_path('framework/testing/palette-kb-'.bin2hex(random_bytes(5)));
        config(['datasource.json_path' => $this->jsonPath]);

        Storage::fake('company-json');
        Storage::disk('company-json')->put(
            'json/bengkel-arka/business_identity.json',
            json_encode(['id' => 1, 'preset' => 'bengkel', 'tax_mode' => 'non_taxable'], JSON_THROW_ON_ERROR),
        );
        Storage::disk('company-json')->put(
            'json/klinik-sehat/business_identity.json',
            json_encode(['id' => 2, 'preset' => 'klinik', 'tax_mode' => 'non_taxable'], JSON_THROW_ON_ERROR),
        );
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->jsonPath);
        parent::tearDown();
    }

    public function test_palette_is_a_native_modal_dialog_with_focus_trap_and_escape(): void
    {
        $html = $this->get('/?company=bengkel-arka')->assertOk()->getContent();

        // <dialog>.showModal() = focus trap + Esc native; cancel dicegah default-nya
        // agar Alpine yang mengatur penutupan dan pengembalian fokus.
        $this->assertStringContainsString('<dialog', $html);
        $this->assertStringContainsString('x-on:cancel.prevent="closePalette()"', $html);
        $this->assertStringContainsString('this.$refs.dialog.showModal()', $html);
        $this->assertStringContainsString('x-on:close="restoreFocus()"', $html);
        $this->assertStringContainsString('this.opener?.focus()', $html);
        $this->assertStringContainsString('role="dialog"', $html);
        $this->assertStringContainsString('aria-modal="true"', $html);
        $this->assertStringContainsString('wire:ignore.self', $html);
    }

    public function test_palette_opens_from_ctrl_k_and_dispatched_event(): void
    {
        $html = $this->get('/?company=bengkel-arka')->assertOk()->getContent();

        $this->assertStringContainsString('x-on:keydown.window.ctrl.k.prevent="openPalette()"', $html);
        $this->assertStringContainsString('x-on:keydown.window.meta.k.prevent="openPalette()"', $html);
        $this->assertStringContainsString('x-on:open-command-palette.window="openPalette()"', $html);
        $this->assertStringContainsString('aria-keyshortcuts="Control+K Meta+K"', $html);
    }

    public function test_search_input_is_a_combobox_that_activates_options_with_the_keyboard(): void
    {
        app(CompanyContext::class)->setCurrent('bengkel-arka');
        app(EntityRepository::class)->for('bengkel-arka', 'contacts')->save(['name' => 'Ketik Navigasi Unik']);

        $html = Livewire::test(\App\Livewire\CommandPalette::class)
            ->set('search', 'Ketik Navigasi Unik')
            ->html();

        // Arrow keys memindahkan aktif; Enter mengaktifkan; input reset saat mengetik.
        $this->assertStringContainsString('x-on:keydown.arrow-down.prevent="moveActive(1)"', $html);
        $this->assertStringContainsString('x-on:keydown.arrow-up.prevent="moveActive(-1)"', $html);
        $this->assertStringContainsString('x-on:keydown.enter.prevent="activateActive()"', $html);
        $this->assertStringContainsString('x-on:input="activeIndex = -1"', $html);
        $this->assertStringContainsString('x-bind:aria-activedescendant="activeDescendant()"', $html);
    }

    public function test_active_option_wraps_around_and_follows_the_result_count(): void
    {
        $this->assertStringContainsString(
            "(this.activeIndex + delta + options.length) % options.length",
            file_get_contents(resource_path('views/livewire/command-palette.blade.php')),
        );
    }

    public function test_menu_results_precede_data_results_in_the_listbox(): void
    {
        app(CompanyContext::class)->setCurrent('bengkel-arka');
        app(EntityRepository::class)->for('bengkel-arka', 'contacts')->save(['name' => 'Pelanggan Unik']);

        // "Pelanggan" cocok dengan label menu terminologi sekaligus baris data.
        $results = Livewire::test(\App\Livewire\CommandPalette::class)
            ->set('search', 'Pelanggan')
            ->viewData('results');

        $types = array_column($results, 'type');
        $this->assertContains('Menu', $types);
        $this->assertNotFalse(array_search('Menu', $types, true), 'Menu harus muncul sebelum hasil Data.');
        $this->assertNotFalse(array_search('Data', $types, true));
        $this->assertLessThan(
            (float) array_search('Data', $types, true),
            (float) array_search('Menu', $types, true),
            'Menu harus berada sebelum Data pada urutan hasil.',
        );

        // Setiap hasil punya route nyata, bukan dummy "#".
        foreach ($results as $result) {
            $this->assertNotSame('#', $result['url']);
            $this->assertStringStartsWith('/app/', $result['url']);
        }
    }

    public function test_palette_renders_no_dummy_icon_literals(): void
    {
        $html = $this->get('/?company=bengkel-arka')->assertOk()->getContent();

        $this->assertStringNotContainsString(">{{ '?' }}</span>", $html);
        $this->assertStringNotContainsString('? </span>', $html);
    }
}
