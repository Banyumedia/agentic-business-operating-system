<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Contracts\PresetSource;
use App\Services\DynamicMenuRegistry;
use App\Services\FeatureResolver;
use App\Services\Preset\PresetDefinitionValidator;
use Tests\TestCase;

/**
 * Fase 6 ekspansi preset - batch 3.
 *
 * Metrik yang dibuktikan di sini: menambah jenis bisnis baru = menambah DATA
 * (satu JSON preset), bukan kode. Karena itu test ini juga menjaga agar slug
 * batch ini tidak pernah muncul di `app/` atau `resources/` (D-31).
 *
 * Sengaja TIDAK memakai `RefreshDatabase`: definisi JSON diuji melalui
 * kontrak runtime yang relevan tanpa membuat salinan data preset.
 */
class PresetCompositionBatch3Test extends TestCase
{
    /** @var list<string> */
    private const BATCH = [
        'toko_hp',
        'petshop',
        'optik',
        'it_support',
        'kantor_hukum',
        'mebel_custom',
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
        // Tier B butuh gate rencana tersendiri (D-33, D-57). Batch ini Tier A,
        // jadi tidak boleh menyalakan satu pun capability Tier B.
        $tierB = ['pharmacy.prescription', 'construction.retention', 'manufacturing.production_order'];

        foreach (self::BATCH as $slug) {
            $definition = $this->definition($slug);

            foreach (array_keys($definition['capabilities']) as $capability) {
                $this->assertContains(
                    $capability,
                    FeatureResolver::CAPABILITIES,
                    "Kapabilitas di luar katalog resmi (D-32): {$slug} -> {$capability}"
                );
                $this->assertNotContains(
                    $capability,
                    $tierB,
                    "Preset Tier A tidak boleh menyalakan capability Tier B: {$slug} -> {$capability}"
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
            $definition = $this->definition($slug);

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

    public function test_batch_workflow_effects_are_backed_by_an_active_capability(): void
    {
        // Efek transisi memakai modul lain; kalau capability-nya mati, efek itu
        // tidak pernah punya tempat bekerja dan alur jadi menyesatkan.
        $requires = [
            'stock.reserve' => 'inventory',
            'stock.deduct' => 'inventory',
            'invoice.create_dp' => 'milestone_billing',
            'invoice.create_final' => 'milestone_billing',
            'deposit.collect' => 'bookings.deposit',
            'deposit.settle' => 'bookings.deposit',
            'journal.post' => 'finance.accounting',
            'approval.request' => 'approval_flow',
        ];

        foreach (self::BATCH as $slug) {
            $definition = $this->definition($slug);

            foreach ($definition['workflows'] as $entity => $workflow) {
                foreach ($workflow['transitions'] as $transition) {
                    foreach ($transition['effects'] ?? [] as $effect) {
                        if (! isset($requires[$effect])) {
                            continue;
                        }

                        $capability = $requires[$effect];
                        $enabled = ($definition['capabilities'][$capability] ?? false) === true;

                        // Penagihan boleh bersandar pada kasir kalau preset
                        // tidak memakai termin proyek.
                        if (! $enabled && str_starts_with($effect, 'invoice.')) {
                            $enabled = ($definition['capabilities']['pos'] ?? false) === true;
                            $capability .= ' atau pos';
                        }

                        $this->assertTrue(
                            $enabled,
                            "Efek {$effect} butuh capability {$capability} aktif: {$slug}.{$entity}"
                        );
                    }
                }
            }
        }
    }

    public function test_every_active_capability_has_a_navigable_home(): void
    {
        // Capability yang dinyalakan tapi grup menu pemiliknya tidak ada di
        // `menus.order` jadi fitur mati: dibayar di tier, tak pernah terlihat.
        // Contoh nyata yang pernah lolos: `timesheet` tanpa grup `projects`.
        $home = [
            'contacts' => 'contacts',
            'deals' => 'contacts',
            'projects' => 'projects',
            'projects.progress_billing' => 'projects',
            'timesheet' => 'projects',
            'scheduling' => 'bookings',
            'bookings' => 'bookings',
            'bookings.deposit' => 'bookings',
            'inventory' => 'inventory',
            'inventory.batch_expiry' => 'inventory',
            'inventory.bom' => 'inventory',
            'pos' => 'pos',
            'pos.tables' => 'pos',
            'milestone_billing' => 'accounting',
            'finance.cashbook' => 'accounting',
            'finance.accounting' => 'accounting',
            'hr.employees' => 'employees',
            'hr.payroll' => 'employees',
        ];

        foreach (self::BATCH as $slug) {
            $definition = $this->definition($slug);
            $order = $definition['menus']['order'];

            foreach ($definition['capabilities'] as $capability => $enabled) {
                if ($enabled !== true || ! isset($home[$capability])) {
                    continue;
                }

                $this->assertContains(
                    $home[$capability],
                    $order,
                    "Capability {$capability} aktif tapi grup menu {$home[$capability]} tidak dinavigasikan: {$slug}"
                );
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
            $definition = $this->definition($slug);

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

    public function test_every_declared_menu_key_resolves_and_quotations_are_reachable(): void
    {
        app(CompanyContext::class)->setCurrent('klinik-sehat');
        $registry = app(DynamicMenuRegistry::class);
        $aliases = ['employees' => 'hrd'];

        foreach (self::BATCH as $slug) {
            $definition = $this->definition($slug);

            foreach ($definition['menus']['order'] as $menuKey) {
                $resolved = $aliases[$menuKey] ?? $menuKey;
                $this->assertTrue($registry->hasModule($resolved), "Menu tidak dikenal registry: {$slug} -> {$menuKey}");
            }

            if (($definition['capabilities']['quotations'] ?? false) === true) {
                $this->assertContains('quotations', $definition['menus']['order'], "Menu quotation tidak dideklarasikan: {$slug}");
                $this->assertTrue($registry->hasPath('quotations', null), "Quotation tidak terjangkau: {$slug}");
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
        $markers = [];
        foreach (self::BATCH as $slug) {
            $definition = $this->definition($slug);
            $markers[$slug] = array_unique([
                strtolower($slug),
                str_replace('_', '-', strtolower($slug)),
                strtolower((string) $definition['name']),
            ]);
        }

        $roots = [app_path(), resource_path()];
        $violations = [];

        foreach ($roots as $root) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
            foreach ($iterator as $file) {
                if (! $file->isFile() || ! in_array($file->getExtension(), ['php', 'css', 'js'], true)) {
                    continue;
                }

                $source = strtolower((string) file_get_contents($file->getPathname()));
                foreach ($markers as $slugMarkers) {
                    foreach ($slugMarkers as $marker) {
                        if (str_contains($source, $marker)) {
                            $violations[] = $file->getPathname().':'.$marker;
                        }
                    }
                }
            }
        }

        $this->assertSame([], $violations, implode("\n", $violations));
    }

    /** @return array<string, mixed> */
    private function definition(string $slug): array
    {
        return json_decode(
            (string) file_get_contents(database_path("presets/{$slug}.json")),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }
}
