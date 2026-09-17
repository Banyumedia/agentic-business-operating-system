<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Contracts\EntityRepository;
use App\Livewire\CommandPalette;
use App\Services\CompanySettingsStore;
use App\Services\DynamicMenuRegistry;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
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

    public function test_search_result_urls_are_real_routes_that_return_200(): void
    {
        app(CompanyContext::class)->setCurrent('bengkel-arka');
        app(EntityRepository::class)->for('bengkel-arka', 'contacts')->save(['name' => 'Sparepart Unik Utama']);

        $component = Livewire::test(CommandPalette::class)->set('search', 'Sparepart Unik Utama');

        // Diambil dari viewData langsung, bukan parsing <a> pertama di HTML -
        // urutan DOM tidak menjamin baris mana yang sedang diverifikasi.
        $dataResults = array_values(array_filter(
            $component->viewData('results'),
            fn (array $result): bool => $result['type'] === 'Data',
        ));

        $this->assertNotEmpty($dataResults, 'Baris kontak unik harus muncul sebagai hasil Data.');

        $result = $dataResults[0];
        $this->assertSame('Sparepart Unik Utama', $result['title']);
        $this->assertSame('Pelanggan', $result['module']);
        $this->assertSame('/app/contacts', $result['url']);
        $this->assertNotSame('#', $result['url']);
        $this->assertStringNotContainsString('href="#"', $component->html());

        $this->get($result['url'].'?company=bengkel-arka')->assertOk();
    }

    public function test_hrd_attendance_route_renders_successfully_with_the_canonical_schema(): void
    {
        // T-F13R poin 1/2: registry sebelumnya merujuk entity 'timesheets'
        // yang tidak punya schema - route ini melempar InvalidArgumentException
        // sebelum diperbaiki. Company `bengkel-arka` di sini memakai preset
        // `bengkel` (hr.employees aktif) dan datasource JSON yang diisolasi
        // oleh setUp(), bukan fixture demo asli.
        $this->get('/app/hrd/attendance?company=bengkel-arka')->assertOk();
    }

    public function test_search_finds_a_unique_timesheet_entry_and_its_result_url_returns_200(): void
    {
        app(CompanyContext::class)->setCurrent('bengkel-arka');
        app(EntityRepository::class)->for('bengkel-arka', 'timesheet_entries')->save([
            'employee_id' => 1,
            'work_date' => '2026-09-10',
            'hours' => 8,
            'note' => 'Catatan Lembur Unik Sembilan',
        ]);

        $results = Livewire::test(CommandPalette::class)
            ->set('search', 'Catatan Lembur Unik Sembilan')
            ->viewData('results');

        $dataResults = array_values(array_filter($results, fn (array $result): bool => $result['type'] === 'Data'));
        $this->assertNotEmpty($dataResults, 'Baris timesheet unik harus muncul sebagai hasil Data.');

        $result = $dataResults[0];
        // Judul memakai titleField() schema (kolom string pertama selain
        // name/title, di sini 'work_date') - bukan field yang dicocokkan
        // pencarian; ini konsisten dengan SchemaPresenter, bukan defect baru.
        $this->assertSame('2026-09-10', $result['title']);
        $this->assertSame('HRD', $result['module']);
        $this->assertSame('/app/hrd/attendance', $result['url']);

        $this->get($result['url'].'?company=bengkel-arka')->assertOk();
    }

    public function test_missing_schema_from_a_broken_registry_entry_fails_visibly(): void
    {
        // Simulasi langsung "registry rusak" (kelas defect T-F13R yang baru
        // diperbaiki di DynamicMenuRegistry): satu item menu merujuk entity
        // tanpa schema. Sebelum perbaikan poin 4, ini ditelan jadi hasil
        // kosong; sekarang harus melempar InvalidArgumentException yang
        // sama seperti dilempar EntitySchema::load().
        $brokenRegistry = new class extends DynamicMenuRegistry
        {
            public function __construct() {}

            public function visibleModules(): array
            {
                return [['slug' => 'ghost', 'name' => 'Ghost', 'icon' => 'circle', 'route' => '/app/ghost']];
            }

            public function menusFor(?string $module): array
            {
                return [[
                    'label' => 'Ghost',
                    'icon' => 'circle',
                    'route' => '/app/ghost',
                    'screen' => 'list',
                    'entity' => 'ghost_entity_tanpa_schema',
                    'term' => null,
                ]];
            }
        };
        $this->app->instance(DynamicMenuRegistry::class, $brokenRegistry);

        app(CompanyContext::class)->setCurrent('bengkel-arka');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Schema entitas tidak ditemukan');

        Livewire::test(CommandPalette::class)->set('search', 'apapun');
    }

    public function test_corrupt_entity_data_fails_visibly_instead_of_returning_empty_results(): void
    {
        app(CompanyContext::class)->setCurrent('bengkel-arka');
        app(EntityRepository::class)->for('bengkel-arka', 'contacts')->save(['name' => 'Sebelum Rusak']);

        // Merusak file entity yang aktif dicari (bukan entity registry yang
        // salah - itu sudah diperbaiki). Pencarian tidak boleh menelan error
        // ini menjadi "tidak ada hasil": harus tetap gagal terlihat.
        file_put_contents($this->jsonPath.'/bengkel-arka/contacts.json', '{rusak');

        $this->expectException(\JsonException::class);

        Livewire::test(CommandPalette::class)->set('search', 'apapun');
    }

    public function test_stale_component_only_reflects_the_currently_active_company_after_switch(): void
    {
        app(CompanyContext::class)->setCurrent('bengkel-arka');
        app(EntityRepository::class)->for('bengkel-arka', 'contacts')->save(['name' => 'Milik Bengkel Unik Stale']);

        app(CompanyContext::class)->setCurrent('klinik-sehat');
        app(EntityRepository::class)->for('klinik-sehat', 'contacts')->save(['name' => 'Milik Klinik Unik Stale']);

        // Component "hidup" saat company A (bengkel) aktif...
        app(CompanyContext::class)->setCurrent('bengkel-arka');
        $component = Livewire::test(CommandPalette::class);

        // ...lalu company aktif berpindah ke B (klinik) setelah component
        // sudah mount - tanpa remount. `searchEntityData()` tidak menyimpan
        // company di properti Livewire manapun, jadi tidak ada state basi
        // untuk dibocorkan; `EntityRepository::for()` tetap jadi lapis kedua
        // yang menolak company yang tidak cocok dengan context aktif.
        app(CompanyContext::class)->setCurrent('klinik-sehat');

        $results = $component->set('search', 'Unik Stale')->viewData('results');
        $titles = array_column(array_filter($results, fn (array $result): bool => $result['type'] === 'Data'), 'title');

        $this->assertContains('Milik Klinik Unik Stale', $titles);
        $this->assertNotContains('Milik Bengkel Unik Stale', $titles);
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
