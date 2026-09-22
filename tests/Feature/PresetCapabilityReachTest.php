<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Mengunci jangkauan dua kapabilitas yang diputuskan dijual (D-64):
 * `finance.accounting` dan `hr.payroll`.
 *
 * Keduanya sempat aktif di **0 dari 40 preset**, sehingga Laporan Keuangan,
 * Bagan Akun, Jurnal, dan Payroll tidak terjangkau siapa pun. Aturan yang
 * dipakai sekarang sengaja tidak memuat penilaian industri:
 *
 * - `hr.payroll` menyala di setiap preset yang sudah punya `hr.employees`.
 *   Payroll adalah kelanjutan wajar dari daftar karyawan.
 * - `finance.accounting` menyala di setiap preset yang sudah punya
 *   `finance.cashbook`. Akuntansi adalah lapisan formal di atas buku kas.
 *
 * Yang membatasi akses adalah **gerbang paket** (D-52): `hr.payroll` di
 * Pro+Enterprise, `finance.accounting` di Enterprise. Menaruh penilaian
 * industri di sini - memutuskan bahwa sebuah barbershop tidak akan pernah
 * butuh buku besar - justru mengembalikan "industri = kode" yang dilarang D-31.
 */
class PresetCapabilityReachTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function impliedCapabilities(): array
    {
        return [
            'payroll mengikuti karyawan' => ['hr.employees', 'hr.payroll'],
            'akuntansi mengikuti buku kas' => ['finance.cashbook', 'finance.accounting'],
        ];
    }

    #[DataProvider('impliedCapabilities')]
    public function test_capability_is_enabled_wherever_its_prerequisite_is(string $prerequisite, string $capability): void
    {
        $missing = [];
        $reach = 0;

        foreach ($this->presets() as $key => $capabilities) {
            if (($capabilities[$prerequisite] ?? false) !== true) {
                continue;
            }

            if (($capabilities[$capability] ?? false) === true) {
                $reach++;

                continue;
            }

            $missing[] = $key;
        }

        $this->assertSame([], $missing, "Preset berikut punya {$prerequisite} tanpa {$capability}: ".implode(', ', $missing));
        $this->assertGreaterThan(0, $reach, "Tidak ada preset yang menyalakan {$capability}.");
    }

    public function test_negative_no_preset_enables_the_capability_without_its_prerequisite(): void
    {
        // Payroll tanpa daftar karyawan, atau akuntansi tanpa buku kas, akan
        // menghasilkan menu yang datanya tidak punya sumber.
        $orphans = [];

        foreach ($this->presets() as $key => $capabilities) {
            foreach (self::impliedCapabilities() as [$prerequisite, $capability]) {
                if (($capabilities[$capability] ?? false) === true
                    && ($capabilities[$prerequisite] ?? false) !== true) {
                    $orphans[] = "{$key}: {$capability} tanpa {$prerequisite}";
                }
            }
        }

        $this->assertSame([], $orphans, implode(', ', $orphans));
    }

    /** @return array<string, array<string, bool>> */
    private function presets(): array
    {
        $presets = [];

        foreach (glob(database_path('presets/*.json')) ?: [] as $path) {
            $definition = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
            $presets[basename($path, '.json')] = $definition['capabilities'] ?? [];
        }

        return $presets;
    }
}
