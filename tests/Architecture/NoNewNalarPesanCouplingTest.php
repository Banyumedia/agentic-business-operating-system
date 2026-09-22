<?php

namespace Tests\Architecture;

use Tests\TestCase;

/**
 * NalarPesan dikesampingkan (D-67), jadi keterikatan padanya tidak boleh
 * bertambah.
 *
 * Yang sudah ada sengaja **tidak dihapus**: `POST /api/webhooks/nalar-pesan`
 * adalah penerima pesanan masuk yang sudah fail-closed (menolak bila rahasianya
 * kosong). Membongkarnya bukan bagian dari "dikesampingkan" - itu perubahan
 * lingkup tersendiri.
 *
 * Yang dijaga di sini adalah pertumbuhannya: daftar berkas yang boleh menyebut
 * NalarPesan dikunci. Menambah satu saja - terutama panggilan **keluar** ke
 * layanan itu - membuat test ini merah, sehingga keputusannya diambil sadar oleh
 * Bos, bukan menyelinap lewat satu commit.
 */
class NoNewNalarPesanCouplingTest extends TestCase
{
    /** @var list<string> */
    private const ALLOWED = [
        'app/Http/Controllers/Api/NalarPesanWebhookController.php',
        'config/services.php',
        'routes/api.php',
    ];

    public function test_nalar_pesan_references_stay_inside_the_known_inbound_webhook(): void
    {
        $unexpected = array_values(array_diff($this->filesMentioningNalarPesan(), self::ALLOWED));

        $this->assertSame([], $unexpected, implode("\n", array_merge(
            ['Keterikatan baru ke NalarPesan ditemukan (D-67 melarang penambahan):'],
            $unexpected,
            ['Bila ini memang diputuskan Bos, cabut D-67 lebih dulu lalu perbarui daftar di test ini.'],
        )));
    }

    public function test_no_outbound_call_targets_nalar_pesan(): void
    {
        // Penerima pesanan masuk boleh ada; yang dilarang adalah Agentic BOS
        // memanggil NalarPesan keluar. Pengiriman WhatsApp tenant tetap lewat
        // App\Services\HermesNodeClient yang fail-closed.
        $violations = [];

        foreach ($this->sourceFiles() as $relative => $contents) {
            if (! preg_match('/nalar[\s_-]?pesan/i', $contents)) {
                continue;
            }

            foreach (['Http::post', 'Http::get', 'Http::withHeaders', 'file_get_contents(\'http', 'curl_init'] as $outbound) {
                if (str_contains($contents, $outbound)) {
                    $violations[] = $relative.' memuat '.$outbound;
                }
            }
        }

        $this->assertSame([], $violations, implode("\n", array_merge(
            ['Panggilan keluar ke NalarPesan tidak diizinkan (D-67):'],
            $violations,
        )));
    }

    /** @return list<string> */
    private function filesMentioningNalarPesan(): array
    {
        $found = [];

        foreach ($this->sourceFiles() as $relative => $contents) {
            if (preg_match('/nalar[\s_-]?pesan/i', $contents)) {
                $found[] = $relative;
            }
        }

        sort($found);

        return $found;
    }

    /** @return array<string, string> */
    private function sourceFiles(): array
    {
        $root = dirname(__DIR__, 2);
        $files = [];

        foreach (['app', 'config', 'routes', 'database'] as $directory) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root.DIRECTORY_SEPARATOR.$directory, \FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
                $files[$relative] = (string) file_get_contents($file->getPathname());
            }
        }

        return $files;
    }
}
