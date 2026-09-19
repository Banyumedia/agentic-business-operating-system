<?php

namespace Tests\Feature;

use App\Contracts\PresetSource;
use App\Services\FeatureResolver;
use App\Services\Preset\PresetDefinitionValidator;
use Tests\TestCase;

/**
 * Fase 6 ekspansi preset - batch 1.
 *
 * Metrik yang dibuktikan di sini: menambah jenis bisnis baru = menambah DATA
 * (satu JSON preset), bukan kode. Karena itu test ini juga menjaga agar slug
 * batch ini tidak pernah muncul di `app/` atau `resources/` (D-31).
 *
 * Sengaja TIDAK memakai `RefreshDatabase`/seeder: verifikasi di sini murni
 * terhadap definisi preset (data), dan jalur `db:seed` di repo ini sedang
 * rusak pre-existing (mock `OutputStyle` menerima `askQuestion` saat seeding)
 * sehingga `PresetCompositionTest` dan `BusinessPresetSeederTest` sudah merah
 * di HEAD tanpa perubahan batch ini. Cakupan render lintas preset tetap
 * menjadi tanggung jawab kedua test itu setelah bug infra tersebut dibereskan.
 */
class PresetCompositionBatch1Test extends TestCase
{
    /** @var list<string> */
    private const BATCH = [
        'warnet_gaming',
        'cuci_sepatu',
        'percetakan',
        'service_ac',
        'toko_bangunan',
        'cleaning_service',
    ];

    public function test_batch_presets_pass_the_definition_validator(): void
    {
        $validator = app(PresetDefinitionValidator::class);

        foreach (self::BATCH as $slug) {
            $path = database_path("presets/{$slug}.json");
            $this->assertFileExists($path);

            $definition = json_decode((string) file_get_contents($path), flags: JSON_THROW_ON_ERROR);
            $validated = $validator->validate($definition);

            $this->assertSame($slug, $validated['key'], "Key preset harus sama dengan nama file: {$slug}");
            $this->assertSame('A', $validated['tier'], "Batch ini murni Tier A: {$slug}");
            $this->assertNotSame('', trim($validated['description']));
        }
    }

    public function test_batch_presets_only_use_capabilities_from_the_locked_catalog(): void
    {
        // Tier B yang ada hanya dua (D-33); batch ini tidak boleh memakainya
        // karena seluruh presetnya Tier A.
        $tierB = ['pharmacy.prescription', 'construction.retention'];

        foreach (self::BATCH as $slug) {
            $definition = json_decode(
                (string) file_get_contents(database_path("presets/{$slug}.json")),
                true,
                flags: JSON_THROW_ON_ERROR,
            );

            foreach (array_keys($definition['capabilities']) as $capability) {
                $this->assertContains(
                    $capability,
                    FeatureResolver::CAPABILITIES,
                    "Kapabilitas di luar katalog resmi (D-32): {$slug} -> {$capability}"
                );
                $this->assertNotContains(
                    $capability,
                    $tierB,
                    "Preset Tier A tidak boleh memakai kapabilitas Tier B: {$slug} -> {$capability}"
                );
            }

            // `system.ai_agent` menuntut `approval_flow` (dependensi katalog).
            if (($definition['capabilities']['system.ai_agent'] ?? false) === true) {
                $this->assertTrue(
                    $definition['capabilities']['approval_flow'] ?? false,
                    "system.ai_agent membutuhkan approval_flow: {$slug}"
                );
            }
        }
    }

    public function test_batch_workflows_declare_terminal_and_require_note_on_backward_transitions(): void
    {
        foreach (self::BATCH as $slug) {
            $definition = json_decode(
                (string) file_get_contents(database_path("presets/{$slug}.json")),
                true,
                flags: JSON_THROW_ON_ERROR,
            );

            $this->assertNotEmpty($definition['workflows'], "Preset wajib punya alur kerja: {$slug}");

            foreach ($definition['workflows'] as $entity => $workflow) {
                $this->assertNotEmpty($workflow['terminal'], "Terminal wajib dideklarasikan (D-46): {$slug}.{$entity}");

                $index = [];
                foreach ($workflow['stages'] as $position => $stage) {
                    $index[$stage['code']] = $position;
                    $this->assertMatchesRegularExpression('/^[a-z_]+$/', $stage['code']);
                }

                foreach ($workflow['terminal'] as $terminal) {
                    $this->assertArrayHasKey($terminal, $index, "Stage terminal tidak terdaftar: {$slug}.{$entity}.{$terminal}");
                }

                foreach ($workflow['transitions'] as $transition) {
                    $sources = $transition['from'] === '*'
                        ? array_keys(array_diff_key($index, array_flip($workflow['terminal'])))
                        : [$transition['from']];

                    foreach ($sources as $source) {
                        if ($index[$transition['to']] < $index[$source]) {
                            $this->assertTrue(
                                $transition['requires_note'] ?? false,
                                "Transisi mundur wajib requires_note (D-46): {$slug}.{$entity}.{$source} -> {$transition['to']}"
                            );
                        }
                    }
                }
            }
        }
    }

    public function test_batch_dashboard_widgets_are_backed_by_an_active_capability(): void
    {
        // Widget hanya dirender bila kapabilitas pendukungnya aktif; kalau
        // tidak, dashboard preset baru akan tampil kosong tanpa ada yang tahu.
        $requires = [
            'upcoming_schedule' => 'scheduling',
            'low_stock' => 'inventory',
            'kpi_cashflow' => 'finance.cashbook',
            'deals_pipeline' => 'deals',
            'pending_approvals' => 'approval_flow',
        ];

        foreach (self::BATCH as $slug) {
            $definition = json_decode(
                (string) file_get_contents(database_path("presets/{$slug}.json")),
                true,
                flags: JSON_THROW_ON_ERROR,
            );

            $widgets = array_column($definition['dashboard']['industry_zone'], 'widget');
            $this->assertNotEmpty($widgets, "Dashboard preset tidak boleh kosong: {$slug}");

            foreach ($widgets as $widget) {
                $this->assertArrayHasKey(
                    $widget,
                    $requires,
                    "Widget belum diimplementasikan WidgetRegistry, dashboard akan kosong: {$slug} -> {$widget}"
                );
                $this->assertTrue(
                    $definition['capabilities'][$requires[$widget]] ?? false,
                    "Widget {$widget} butuh kapabilitas {$requires[$widget]} aktif: {$slug}"
                );
            }
        }
    }

    public function test_batch_presets_are_discoverable_through_the_preset_source(): void
    {
        // Preset baru harus langsung terbaca kontrak `PresetSource` tanpa
        // registrasi tambahan di kode - ini yang membuat penambahannya
        // benar-benar "hanya data".
        $source = app(PresetSource::class);
        $keys = array_column($source->all(), 'key');

        foreach (self::BATCH as $slug) {
            $this->assertContains($slug, $keys, "Preset tidak ditemukan PresetSource: {$slug}");

            $found = $source->find($slug);
            $this->assertNotNull($found);
            $this->assertSame($slug, $found['key']);
        }
    }

    public function test_batch_slugs_never_appear_in_application_code(): void
    {
        // Inti D-31 dan metrik task ini: preset ditambah, diff kode nol.
        $roots = [app_path(), resource_path()];
        $violations = [];

        foreach ($roots as $root) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
            foreach ($iterator as $file) {
                if (! $file->isFile() || ! in_array($file->getExtension(), ['php', 'css', 'js'], true)) {
                    continue;
                }

                $contents = (string) file_get_contents($file->getPathname());
                foreach (self::BATCH as $slug) {
                    if (str_contains($contents, $slug)) {
                        $violations[] = $file->getPathname().' memuat slug preset '.$slug;
                    }
                }
            }
        }

        $this->assertSame([], $violations, implode("\n", $violations));
    }
}
