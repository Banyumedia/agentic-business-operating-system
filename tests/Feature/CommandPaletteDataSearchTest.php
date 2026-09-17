<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Contracts\EntityRepository;
use App\Livewire\CommandPalette;
use App\Services\CompanySettingsStore;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class CommandPaletteDataSearchTest extends TestCase
{
    private string $jsonPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Data entitas diisolasi supaya test ini tidak menulis ke data demo
        // nyata di storage/app/json - hanya preset/business_identity yang
        // memakai Storage::fake('company-json').
        $this->jsonPath = storage_path('framework/testing/palette-'.bin2hex(random_bytes(5)));
        config(['datasource.json_path' => $this->jsonPath]);

        Storage::fake('company-json');
        Storage::disk('company-json')->put(
            'json/bengkel-arka/business_identity.json',
            json_encode(['id' => 1, 'preset' => 'bengkel', 'tax_mode' => 'non_taxable'], JSON_THROW_ON_ERROR),
        );
        Storage::disk('company-json')->put(
            'json/klinik-sehat/business_identity.json',
            json_encode(['id' => 1, 'preset' => 'klinik', 'tax_mode' => 'non_taxable'], JSON_THROW_ON_ERROR),
        );
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->jsonPath);
        parent::tearDown();
    }

    public function test_search_finds_entity_data_from_the_active_company_repository(): void
    {
        app(CompanyContext::class)->setCurrent('bengkel-arka');
        app(EntityRepository::class)->for('bengkel-arka', 'contacts')->save(['name' => 'Zetta Motor Unik']);

        Livewire::test(CommandPalette::class)
            ->set('search', 'Zetta Motor Unik')
            ->assertSee('Zetta Motor Unik')
            ->assertSee('/app/contacts');
    }

    public function test_data_results_use_company_terminology_for_the_module_label(): void
    {
        app(CompanyContext::class)->setCurrent('klinik-sehat');
        app(EntityRepository::class)->for('klinik-sehat', 'contacts')->save(['name' => 'Rekam Pasien Unik']);

        Livewire::test(CommandPalette::class)
            ->set('search', 'Rekam Pasien Unik')
            ->assertSee('Pasien');
    }

    public function test_search_result_hrefs_are_real_routes_that_do_not_404(): void
    {
        app(CompanyContext::class)->setCurrent('bengkel-arka');
        app(EntityRepository::class)->for('bengkel-arka', 'contacts')->save(['name' => 'Sparepart Unik Utama']);

        $component = Livewire::test(CommandPalette::class)->set('search', 'Sparepart Unik Utama');
        $html = $component->html();

        $this->assertStringNotContainsString('href="#"', $html);

        preg_match('/href="([^"]+)"/', $html, $matches);
        $this->assertNotEmpty($matches, 'Hasil pencarian harus memiliki href.');

        $this->get($matches[1].'?company=bengkel-arka')->assertOk();
    }

    public function test_data_results_are_scoped_to_the_active_company_only(): void
    {
        app(CompanyContext::class)->setCurrent('bengkel-arka');
        app(EntityRepository::class)->for('bengkel-arka', 'contacts')->save(['name' => 'Rahasia Bengkel Unik']);

        app(CompanyContext::class)->setCurrent('klinik-sehat');
        app(EntityRepository::class)->for('klinik-sehat', 'contacts')->save(['name' => 'Rahasia Klinik Unik']);

        Livewire::test(CommandPalette::class)
            ->set('search', 'Rahasia')
            ->assertSee('Rahasia Klinik Unik')
            ->assertDontSee('Rahasia Bengkel Unik');
    }

    public function test_disabled_capability_entities_are_excluded_from_data_results(): void
    {
        app(CompanyContext::class)->setCurrent('bengkel-arka');
        app(EntityRepository::class)->for('bengkel-arka', 'projects')->save(['name' => 'Proyek Unik Rahasia']);

        app(CompanySettingsStore::class)->update('bengkel-arka', static function (array $settings): array {
            $settings['features']['projects'] = false;

            return $settings;
        });

        // Bukan assertDontSee($search) - pesan "tidak ditemukan" meng-echo
        // balik search term itu sendiri, jadi assertion HTML akan selalu
        // gagal walau memang tidak ada hasil data. Periksa viewData langsung.
        $results = Livewire::test(CommandPalette::class)
            ->set('search', 'Proyek Unik Rahasia')
            ->viewData('results');

        $this->assertSame([], array_filter($results, fn (array $r): bool => $r['type'] === 'Data'));
    }

    public function test_short_search_term_shows_no_data_results(): void
    {
        app(CompanyContext::class)->setCurrent('bengkel-arka');
        app(EntityRepository::class)->for('bengkel-arka', 'contacts')->save(['name' => 'Zx']);

        Livewire::test(CommandPalette::class)
            ->set('search', 'Z')
            ->assertDontSee('Zx');
    }

    public function test_command_palette_source_has_no_industry_named_branch(): void
    {
        $source = file_get_contents(app_path('Livewire/CommandPalette.php'));

        $this->assertDoesNotMatchRegularExpression('/\b(?:bengkel|klinik|salon|laundry|apotek|kontraktor|agency)\b/i', $source);
        $this->assertStringNotContainsString('DB::', $source);
    }
}
