<?php

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

class NoLiteralTermsTest extends TestCase
{
    public function test_no_literal_terms_hardcoded_in_views()
    {
        // Check for specific literal terms that should use term()
        $literals = [
            'Pelanggan', 'Klien', 'Pasien', 'Penyewa',
            'Pegawai', 'Karyawan', 'Terapis', 'Mekanik',
        ];

        // Kamus istilah per industri hanya berlaku di UI tenant. Panel Super
        // Admin adalah UI platform: ia tidak punya company aktif, sehingga
        // `term()` (yang resolve lewat `CompanyPresetResolver::current()`)
        // tidak punya konteks di sana. "Klien" di panel itu berarti tenant
        // pelanggan platform, bukan entity `contact` milik sebuah usaha.
        $platformViewDirs = [
            'resources'.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'admin',
            'resources'.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.'livewire'.DIRECTORY_SEPARATOR.'admin',
        ];

        // Frasa majemuk yang kebetulan memuat kata kamus tetapi bukan istilah
        // kamus: ini nama produk/fungsi yang terkunci, sama perlakuannya
        // dengan "Karyawan AI" (D-30).
        $compoundPhrases = [
            'Pelanggan' => ['Layanan Pelanggan'],
        ];

        $dir = __DIR__.'/../../resources/views';

        $violations = [];

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                // `getPathname()` masih memuat `/../../` dari $dir, jadi
                // dinormalkan dulu sebelum dipakai untuk pencocokan direktori.
                $relativePath = str_replace(
                    dirname(__DIR__, 2).DIRECTORY_SEPARATOR,
                    '',
                    (string) realpath($file->getPathname()),
                );

                $isPlatformView = false;
                foreach ($platformViewDirs as $platformDir) {
                    if (str_starts_with($relativePath, $platformDir.DIRECTORY_SEPARATOR)) {
                        $isPlatformView = true;
                        break;
                    }
                }

                if ($isPlatformView) {
                    continue;
                }

                $content = file_get_contents($file->getPathname());

                // Only check text nodes in HTML or strings, roughly.
                // A stricter way is to parse blade, but regex usually catches literal text.
                foreach ($literals as $literal) {
                    $regex = '/(?<![\'"])'.preg_quote($literal, '/').'(?![\'"])/'; // naive check outside quotes mostly

                    if (preg_match_all($regex, $content, $matches, PREG_OFFSET_CAPTURE)) {
                        foreach ($matches[0] as $match) {
                            $offset = $match[1];

                            // "Karyawan AI" is the fixed product brand name for
                            // the AI assistant (D-30/COMMERCIAL spec), not the
                            // per-industry `staff` dictionary term. Flagging it
                            // here would force renaming a locked product name.
                            if ($literal === 'Karyawan' && substr($content, $offset, 11) === 'Karyawan AI') {
                                continue;
                            }

                            if ($this->matchesCompoundPhrase($content, $offset, $literal, $compoundPhrases)) {
                                continue;
                            }

                            $line = substr_count(substr($content, 0, $offset), "\n") + 1;

                            $violations[] = "{$relativePath}:{$line} contains literal term '{$literal}'. Use {{ term('...') }} instead.";
                        }
                    }
                }
            }
        }

        // This test might be noisy if there are legit uses, but per D-31 it should be 0.
        // We will assert empty and see what fails.
        $this->assertEmpty(
            $violations,
            "Found literal terms in blade views:\n".implode("\n", array_unique($violations))
        );
    }

    /**
     * Kata kamus yang muncul sebagai bagian frasa majemuk terkunci, mis.
     * "Layanan Pelanggan" (customer service) yang merujuk fungsi bot, bukan
     * entity `contact` sebuah usaha.
     *
     * @param  array<string, list<string>>  $compoundPhrases
     */
    private function matchesCompoundPhrase(string $content, int $offset, string $literal, array $compoundPhrases): bool
    {
        foreach ($compoundPhrases[$literal] ?? [] as $phrase) {
            $position = strpos($phrase, $literal);
            if ($position === false) {
                continue;
            }

            $start = $offset - $position;
            if ($start >= 0 && substr($content, $start, strlen($phrase)) === $phrase) {
                return true;
            }
        }

        return false;
    }

    public function test_a11y_attributes()
    {
        // Check role="dialog"
        $dir = __DIR__.'/../../resources/views';

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $content = file_get_contents($file->getPathname());

                if (str_contains($content, 'role="dialog"') || str_contains($content, "role='dialog'") || str_contains($content, 'x-bind:role="desktop ? \'complementary\' : \'dialog\'"')) {
                    if (! str_contains($content, 'aria-modal') && ! str_contains($content, 'x-bind:aria-modal')) {
                        $this->fail("Dialog missing aria-modal in file: {$file->getPathname()}");
                    }

                    if (! str_contains($content, 'aria-labelledby')) {
                        $this->fail("Dialog missing aria-labelledby in file: {$file->getPathname()}");
                    }
                }
            }
        }

        // Navigation should have exactly one aria-current="page"
        // In our Livewire app, aria-current="page" is typically conditional.
        // We will just verify it's used in the sidebar.
        $sidebarContent = file_get_contents($dir.'/livewire/sidebar.blade.php');
        $this->assertStringContainsString('aria-current="page"', $sidebarContent, "Sidebar missing aria-current='page' for active state");

        // Button opening drawer (sidebar) should have aria-expanded and aria-controls
        $layoutContent = file_get_contents($dir.'/components/layouts/module.blade.php');
        $this->assertStringContainsString('aria-controls="module-sidebar"', $layoutContent, 'Layout missing aria-controls for sidebar toggle');
        $this->assertStringContainsString('aria-expanded="', $layoutContent, 'Layout missing aria-expanded for sidebar toggle');

        $this->assertTrue(true);
    }
}
