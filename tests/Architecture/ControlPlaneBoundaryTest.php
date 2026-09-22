<?php

namespace Tests\Architecture;

use App\Services\Hermes\ControlPlanePaths;
use Tests\TestCase;

/**
 * T-86: daftar-putih hanya berguna bila tidak bisa dilewati.
 *
 * Mengikuti pola T-63a yang sudah terbukti: batas yang penting ditegakkan oleh
 * **struktur**, bukan oleh ingatan orang berikutnya. Yang dijaga di sini bukan
 * selera arsitektur - port dashboard Hermes menyajikan tulis-berkas dan terminal,
 * jadi satu panggilan dari permukaan tenant berarti tenant berjarak satu bug dari
 * eksekusi kode di host Hermes.
 *
 * Empat penjaga, masing-masing menutup cara berbeda untuk melewati daftar-putih:
 *
 * (a) memanggil dashboard dari kelas lain;
 * (b) memanggil klien yang benar dari berkas yang tidak berhak;
 * (c) menumpuk izin di daftar-putih yang tidak pernah dipakai siapa pun;
 * (d) menambah path ke daftar terlarang - atau menghapusnya - tanpa terlihat di review.
 */
class ControlPlaneBoundaryTest extends TestCase
{
    /**
     * Satu-satunya dua berkas yang boleh menyebut rute dashboard Hermes.
     *
     * @var list<string>
     */
    private const DOORS = [
        'app/Services/Hermes/ControlPlanePaths.php',
        'app/Services/Hermes/HermesControlPlaneClient.php',
    ];

    public function test_only_the_control_plane_client_names_dashboard_routes(): void
    {
        // Berkas lain yang menuliskan path dashboard sendiri akan melewati
        // daftar-putih **dan** pemetaan galatnya sekaligus.
        $needles = ['/api/profiles', '/api/pairing', '/api/messaging', '/api/system/stats', '/api/tools/terminal'];
        $violations = [];

        foreach ($this->sourceFiles(['app', 'routes', 'config']) as $relative => $source) {
            if (in_array($relative, self::DOORS, true)) {
                continue;
            }

            foreach ($needles as $needle) {
                if (str_contains($source, $needle)) {
                    $violations[] = $relative.' menyebut '.$needle;
                }
            }
        }

        $this->assertSame([], $violations);
    }

    public function test_only_whitelisted_files_may_reach_the_control_plane(): void
    {
        // Akses control plane adalah super-admin-only (D-72 butir 3). Menegakkannya
        // lewat pemeriksaan peran di dalam layar berarti mengandalkan seseorang
        // mengingatnya di layar berikutnya; di sini ia ditegakkan oleh struktur.
        //
        // **Kenapa daftar-putih, bukan daftar-hitam permukaan tenant.** Versi
        // sebelumnya mendaftar tempat-tempat yang dipakai tenant lalu melarang
        // keduanya di sana. Daftar semacam itu harus **menebak seluruh permukaan
        // tenant**, dan tebakannya ketinggalan begitu ada direktori baru: komponen
        // Livewire di akar `app/Livewire/` (Dashboard, Sidebar, CommandPalette,
        // Lobby), lalu `app/Services/`, `app/Jobs/`, `app/Models/` semuanya lolos.
        // Karena klien punya metode bernama (`health()`, `status()`,
        // `systemStats()`), satu komponen akar cukup memanggil
        // `app(HermesControlPlaneClient::class)->status(...)` tanpa menulis satu pun
        // path dashboard - sehingga lolos penjaga (a) juga.
        //
        // Dibalik, aturannya gagal aman: berkas baru **otomatis dilarang** sampai
        // seseorang sengaja menambahkannya ke daftar di bawah, dan penambahan itu
        // muncul di diff sebagai keputusan.
        $forbidden = ['HermesControlPlaneClient', 'ControlPlanePaths'];
        $violations = [];

        foreach ($this->sourceFiles(['app']) as $relative => $source) {
            if ($this->mayReachControlPlane($relative)) {
                continue;
            }

            foreach ($forbidden as $needle) {
                if (str_contains($source, $needle)) {
                    $violations[] = $relative.' memanggil '.$needle;
                }
            }
        }

        $this->assertSame([], $violations);
    }

    public function test_every_whitelisted_path_is_exercised_by_a_test(): void
    {
        // Daftar-putih yang menumpuk izin tak terpakai adalah permukaan serang
        // gratis: ia memberi wewenang tanpa ada yang pernah memeriksa bentuk
        // pemakaiannya. Pencarian di sini memakai **literal**, bukan konstanta,
        // supaya menambah satu path menuntut satu kasus uji baru yang terlihat di
        // diff - bukan satu loop yang otomatis "meliputi" apa pun yang ditambahkan.
        $corpus = implode("\n", $this->sourceFiles(['tests']));
        $unused = [];

        foreach (array_keys(ControlPlanePaths::ALLOWED) as $entry) {
            [, $template] = explode(' ', $entry, 2);

            if (! str_contains($corpus, "'".$template."'")) {
                $unused[] = $entry;
            }
        }

        $this->assertSame([], $unused, 'Path di daftar-putih tanpa test yang memakainya.');
    }

    public function test_the_forbidden_list_is_locked_by_this_test(): void
    {
        // Menambah **atau menghapus** satu baris dari daftar terlarang D-72 harus
        // menuntut perubahan test ini, sehingga muncul di review sebagai keputusan -
        // bukan sebagai satu baris di berkas konstanta yang mudah terlewat.
        $this->assertSame([
            '/api/fs/',
            '/api/files/',
            '/api/tools/terminal/',
            '/api/git/',
            '/api/env/reveal',
            '/api/ops/',
            '/api/dashboard/plugins',
            '/open-terminal',
        ], ControlPlanePaths::FORBIDDEN);
    }

    /**
     * Satu-satunya tempat di `app/` yang boleh menyebut klien control plane.
     *
     * Sengaja sempit: perintah ping operator, layar admin (super-admin-only), dan
     * lajur Hermes itu sendiri. Menambah baris di sini adalah cara **satu-satunya**
     * memberi akses, jadi tiap penambahan terlihat dan bisa ditanyakan di review.
     */
    private function mayReachControlPlane(string $relative): bool
    {
        return $relative === 'app/Console/Commands/HermesControlPing.php'
            || str_starts_with($relative, 'app/Livewire/Admin/')
            || str_starts_with($relative, 'app/Services/Hermes/');
    }

    /**
     * @param  list<string>  $roots
     * @return array<string, string>
     */
    private function sourceFiles(array $roots): array
    {
        $files = [];

        foreach ($roots as $root) {
            $directory = base_path($root);

            if (! is_dir($directory)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen(base_path()) + 1));
                    $files[$relative] = (string) file_get_contents($file->getPathname());
                }
            }
        }

        return $files;
    }
}
