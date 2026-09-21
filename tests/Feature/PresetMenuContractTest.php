<?php

namespace Tests\Feature;

use App\Contracts\CompanyContext;
use App\Services\DynamicMenuRegistry;
use Tests\TestCase;

/**
 * Kontrak `menus.order` untuk SELURUH preset.
 *
 * Catatan penting tentang apa yang `menus.order` lakukan: ia hanya mengatur
 * URUTAN. `DynamicMenuRegistry::modules()` tetap meng-append setiap modul
 * katalog yang tidak didaftarkan, jadi capability yang aktif tidak pernah
 * benar-benar hilang dari navigasi. Yang rusak kalau preset lupa
 * mendaftarkan sebuah grup adalah urutannya: grup itu terlempar ke belakang,
 * dan dulu bisa mendarat di bawah "Pengaturan".
 */
class PresetMenuContractTest extends TestCase
{
    /** Capability => grup menu yang menjadi rumahnya di katalog registry. */
    private const CAPABILITY_HOME = [
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
        'hr.employees' => 'hrd',
        'hr.payroll' => 'hrd',
    ];

    /** Alias yang diterima `DynamicMenuRegistry::modules()`. */
    private const ALIASES = ['employees' => 'hrd'];

    public function test_every_declared_menu_key_resolves_to_a_registry_module(): void
    {
        $registry = app(DynamicMenuRegistry::class);
        $violations = [];

        foreach ($this->presets() as $slug => $definition) {
            foreach ($definition['menus']['order'] as $key) {
                $resolved = self::ALIASES[$key] ?? $key;

                if (! $registry->hasModule($resolved)) {
                    $violations[] = "{$slug}: '{$key}' bukan grup menu di registry";
                }
            }
        }

        // Kunci yang tidak dikenal dibuang diam-diam oleh `modules()`, jadi
        // salah tulis tidak pernah error - hanya urutan yang tidak terjadi.
        $this->assertSame([], $violations, implode("\n", $violations));
    }

    public function test_every_active_capability_group_is_declared_in_menu_order(): void
    {
        $violations = [];

        foreach ($this->presets() as $slug => $definition) {
            $declared = array_map(
                static fn (string $key): string => self::ALIASES[$key] ?? $key,
                $definition['menus']['order'],
            );

            foreach ($definition['capabilities'] as $capability => $enabled) {
                if ($enabled !== true || ! isset(self::CAPABILITY_HOME[$capability])) {
                    continue;
                }

                $home = self::CAPABILITY_HOME[$capability];
                if (! in_array($home, $declared, true)) {
                    $violations[] = "{$slug}: capability '{$capability}' aktif tapi grup '{$home}' tidak diurutkan";
                }
            }
        }

        $this->assertSame([], $violations, implode("\n", $violations));
    }

    public function test_settings_is_declared_last_in_every_preset(): void
    {
        foreach ($this->presets() as $slug => $definition) {
            $order = $definition['menus']['order'];

            $this->assertSame(
                'settings',
                end($order),
                "Pengaturan harus menjadi entri terakhir menus.order: {$slug}"
            );
        }
    }

    public function test_runtime_menu_order_always_ends_with_settings(): void
    {
        // Jaring pengaman untuk preset yang lupa: apa pun isi `menus.order`,
        // Pengaturan tidak boleh mendahului modul operasional.
        app(CompanyContext::class)->setCurrent('bengkel-arka');
        $registry = app(DynamicMenuRegistry::class);

        $modules = $registry->modules();
        $this->assertSame('settings', end($modules));

        $visible = array_column($registry->visibleModules(), 'slug');
        $this->assertNotEmpty($visible);
        $this->assertSame('settings', end($visible));

        // Kasir adalah layar harian; ia harus berada sebelum Pengaturan.
        $this->assertContains('pos', $visible);
        $this->assertLessThan(
            array_search('settings', $visible, true),
            array_search('pos', $visible, true),
            'Kasir tidak boleh berada di bawah Pengaturan.'
        );
    }

    /** @return array<string, array<string, mixed>> */
    private function presets(): array
    {
        $presets = [];

        foreach (glob(database_path('presets/*.json')) ?: [] as $path) {
            $presets[basename($path, '.json')] = json_decode(
                (string) file_get_contents($path),
                true,
                flags: JSON_THROW_ON_ERROR,
            );
        }

        $this->assertNotEmpty($presets);

        return $presets;
    }
}
