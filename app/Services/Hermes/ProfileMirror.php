<?php

namespace App\Services\Hermes;

use App\Models\HermesNode;
use App\Models\HermesProfile;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Cermin profil node Hermes, berdampingan dengan baris `hermes_profiles` kita (T-83).
 *
 * **Kenapa read-through, tanpa tabel bayangan** (D-72 butir 2). Menyimpan daftar
 * profil node ke tabel sendiri terdengar rapi sampai node tidak terjangkau: sejak
 * saat itu salinannya menyimpang, dan halaman terus menampilkan angka yang dulu
 * benar tanpa mengatakan bahwa ia dulu. Operator yang melihat layar hijau tidak
 * punya alasan memeriksa apa pun. Halaman yang **jujur kosong** memaksa pertanyaan
 * yang benar. Karena itu satu-satunya penyimpanan di sini adalah cache berumur
 * detik, dan setiap hasil membawa stempel `diambil_pada`.
 *
 * **Kenapa rekonsiliasi harus terlihat, bukan dirapikan** (butir b). Dua arah
 * penyimpangan punya arti yang sama sekali berbeda:
 *
 * - `yatim` - profil ada di node, tidak ada baris kita. Artinya **ada bot berjalan di
 *   luar pembukuan**: tidak ada company yang menanggungnya, tidak ada kuota yang
 *   dihitung, tidak ada yang berwenang atasnya. Ini temuan, bukan sampah.
 * - `hilang_di_node` - baris kita menunjuk profil yang tidak ada di node. Pemetaan
 *   mati, dan bot tenant itu tidak akan menjawab siapa pun.
 *
 * Keduanya **ditampilkan**, dan **tidak ada penghapusan otomatis apa pun**. Node
 * yang sedang sakit, sedang dipindah, atau sedang menjawab separuh akan membuat
 * pembersih otomatis memusnahkan peta company↔profil (D-37) beserta riwayatnya -
 * kerusakan yang jauh lebih besar dan tidak bisa dibangun ulang, dibanding satu
 * baris yang bertanda merah untuk sementara.
 *
 * **Kenapa tidak ada rahasia di hasil** (butir c). `webhook_secret_reference`
 * menyimpan **hash** token bot (QA-08). Hash pun tidak dirender, bahkan terpotong:
 * ia cukup untuk menguji kandidat token secara offline, dan tidak satu pun keputusan
 * operator membutuhkannya di layar. Nilai env tidak diambil sama sekali. Dari sisi
 * node, hanya field yang memang perlu ditampilkan yang disalin - `path` (letak
 * berkas di host) dan `has_env` sengaja **tidak** ikut.
 *
 * **Kenapa SOUL punya metode sendiri** (butir d). Mengambil SOUL untuk seluruh
 * daftar berarti satu panggilan per profil: N+1 ke node, ratusan permintaan untuk
 * satu render pada armada penuh. Selain itu isi SOUL memuat aturan bisnis dan
 * identitas white-label (D-68) - bukan tontonan daftar. `soulFor()` dipakai hanya
 * saat satu profil dibuka.
 *
 * **Kenapa kegagalan dikembalikan, bukan dilempar** (butir e). Pola yang sama dengan
 * `HermesNodeManager::checkHealth()`: laman pemantauan yang mati justru menghilangkan
 * satu-satunya cara melihat bahwa ada node bermasalah. Dan sebabnya **dibedakan** -
 * "belum berwenang" dan "node mati" mengirim operator ke tempat yang berbeda.
 * Menyatukan keduanya membuat ia memeriksa proses dan jaringan di host yang
 * sebenarnya sehat, yang persis keadaan **hari ini**: pembacaan daftar profil dijawab
 * 401 oleh node sampai H-05 mendarat di repo Hermes.
 *
 * Path dashboardnya sengaja tidak disebut di berkas ini - hanya dua berkas pintu yang
 * boleh menamai rute Hermes (T-86 penjaga a), supaya perubahan rute muncul di satu
 * tempat saja.
 *
 * Kelas ini memanggil node **hanya** lewat `HermesControlPlaneClient` (D-72 butir 1),
 * dan hanya lewat metode bernama - tidak ada satu literal path dashboard di berkas
 * ini, supaya perubahan rute di Hermes tetap muncul sebagai satu test merah di satu
 * tempat.
 */
class ProfileMirror
{
    /**
     * Kunci yang dipakai mencocokkan baris kita dengan profil di node.
     *
     * `instance_id` adalah satu-satunya kolom kita yang unik dan stabil, jadi ia yang
     * dipakai sebagai nama profil di sisi Hermes. Konvensi ini belum ditegakkan oleh
     * apa pun - tidak ada kolom khusus "nama profil node" - dan itu justru alasan
     * kelompok `yatim`/`hilang_di_node` harus terlihat: penyimpangan konvensi muncul
     * sebagai baris, bukan sebagai bot yang diam-diam tidak terlacak.
     */
    private const KUNCI_COCOK = 'instance_id';

    public function __construct(private readonly HermesControlPlaneClient $client) {}

    /**
     * Cermin satu node: tiga kelompok hasil rekonsiliasi + sebab bila gagal.
     *
     * @param  bool  $fresh  Memaksa panggilan baru ke node, melewati cache pendek.
     *                       Tanpa jalan ini tombol "segarkan" berbohong.
     * @return array{
     *     node_id: int|string,
     *     node_name: string,
     *     ok: bool,
     *     sebab: string,
     *     pesan: string,
     *     diambil_pada: string,
     *     jumlah_baris_kami: int,
     *     cocok: list<array<string, mixed>>,
     *     yatim: list<array<string, mixed>>,
     *     hilang_di_node: list<array<string, mixed>>
     * }
     */
    public function forNode(HermesNode $node, bool $fresh = false): array
    {
        $jawaban = $this->fetch($node, $fresh);

        // Baris kita dibaca **di luar** cache. Yang pantas di-cache hanyalah jawaban
        // node (mahal, jauh, bisa gagal); basis data lokal murah, dan menyimpan hasil
        // rekonsiliasi berarti perubahan yang baru saja dibuat operator tidak terlihat
        // di render berikutnya - cache yang membohongi sisi yang justru kita kuasai.
        $baris = HermesProfile::with('companies')
            ->where('node_id', $node->getKey())
            ->get();

        $hasil = [
            'node_id' => $node->getKey(),
            'node_name' => (string) $node->name,
            'ok' => $jawaban['ok'],
            'sebab' => $jawaban['sebab'],
            'pesan' => $jawaban['pesan'],
            'diambil_pada' => $jawaban['diambil_pada'],
            // Selalu disertakan, juga saat gagal: operator tetap tahu berapa baris yang
            // kita punya tanpa kita mengklaim apa pun tentang keadaan di node.
            'jumlah_baris_kami' => $baris->count(),
            'cocok' => [],
            'yatim' => [],
            'hilang_di_node' => [],
        ];

        if (! $jawaban['ok']) {
            // Node tidak terbaca berarti **tidak ada yang bisa direkonsiliasi**. Mengisi
            // `hilang_di_node` dengan seluruh baris kita di sini akan menuduh baris yang
            // sehat berdasarkan jawaban yang tidak pernah datang - dan itulah tuduhan
            // yang membuat orang menghapusnya.
            return $hasil;
        }

        $perNama = $baris->keyBy(self::KUNCI_COCOK);
        $terlihat = [];

        foreach ($jawaban['profiles'] as $profilNode) {
            $nama = trim((string) ($profilNode['name'] ?? ''));

            if ($nama === '') {
                continue;
            }

            $terlihat[$nama] = true;
            $milik = $perNama->get($nama);

            if ($milik instanceof HermesProfile) {
                $hasil['cocok'][] = $this->barisCocok($nama, $profilNode, $milik, $jawaban['diambil_pada']);

                continue;
            }

            $hasil['yatim'][] = $this->barisYatim($nama, $profilNode, $jawaban['diambil_pada']);
        }

        foreach ($baris as $milik) {
            $nama = (string) $milik->{self::KUNCI_COCOK};

            if (! isset($terlihat[$nama])) {
                $hasil['hilang_di_node'][] = $this->barisHilang($milik, $jawaban['diambil_pada']);
            }
        }

        return $hasil;
    }

    /**
     * SOUL satu profil, diambil hanya saat profil itu dibuka.
     *
     * Kegagalan dikembalikan dengan alasan yang sama seperti daftar: layar yang
     * membuka satu profil tidak boleh mati karena node sedang sakit. Tidak di-cache:
     * SOUL adalah yang dibaca tepat sebelum disunting, dan menyunting salinan basi
     * berarti menimpa perubahan orang lain.
     *
     * @return array{ok: bool, sebab: string, pesan: string, ada: bool, isi: string, diambil_pada: string}
     */
    public function soulFor(HermesNode $node, string $profileName): array
    {
        $diambilPada = now()->toIso8601String();

        try {
            $jawaban = $this->client->profileSoul($node, $profileName);
        } catch (Throwable $exception) {
            [$sebab, $pesan] = $this->sebabDari($exception, $node);

            return [
                'ok' => false,
                'sebab' => $sebab,
                'pesan' => $pesan,
                'ada' => false,
                'isi' => '',
                'diambil_pada' => $diambilPada,
            ];
        }

        return [
            'ok' => true,
            'sebab' => 'ok',
            'pesan' => '',
            // Profil tanpa `SOUL.md` menjawab 200 dengan `exists: false` - bukan 404 -
            // jadi "tidak ada SOUL" dan "profil tidak ada" tetap dua keadaan berbeda.
            'ada' => (bool) ($jawaban['exists'] ?? false),
            'isi' => (string) ($jawaban['content'] ?? ''),
            'diambil_pada' => $diambilPada,
        ];
    }

    /**
     * Membuang jawaban node yang tersimpan untuk satu node.
     *
     * Dipakai tombol "segarkan" lewat `forNode(..., fresh: true)`, dan tersedia
     * terpisah supaya aksi yang **mengubah** profil di node bisa membatalkan cermin
     * yang baru saja menjadi salah.
     */
    public function forget(HermesNode $node): void
    {
        Cache::forget($this->cacheKey($node));
    }

    /**
     * Mengambil daftar profil dari node, dengan cache pendek per node.
     *
     * Umurnya sengaja pendek dan bisa dikonfigurasi: cukup untuk menahan beberapa
     * panggilan dalam satu render halaman, terlalu pendek untuk menjadi sumber
     * kebenaran bayangan.
     *
     * Kegagalan **juga** di-cache. Alasannya bukan kerapian: node yang mati menghabiskan
     * seluruh `control.timeout` setiap panggilan, jadi tanpa ini satu node sakit
     * mengubah tiap render menjadi penjumlahan timeout - halaman pemantauan menjadi
     * tidak terpakai justru saat ia dibutuhkan. `fresh: true` selalu menembusnya.
     *
     * @return array{ok: bool, sebab: string, pesan: string, diambil_pada: string, profiles: list<array<string, mixed>>}
     */
    private function fetch(HermesNode $node, bool $fresh): array
    {
        if ($fresh) {
            $this->forget($node);
        }

        $ttl = max(1, (int) config('hermes.control.mirror_ttl', 20));

        return Cache::remember($this->cacheKey($node), $ttl, function () use ($node): array {
            $diambilPada = now()->toIso8601String();

            try {
                $jawaban = $this->client->profiles($node);
            } catch (Throwable $exception) {
                [$sebab, $pesan] = $this->sebabDari($exception, $node);

                return [
                    'ok' => false,
                    'sebab' => $sebab,
                    'pesan' => $pesan,
                    'diambil_pada' => $diambilPada,
                    'profiles' => [],
                ];
            }

            $profiles = $jawaban['profiles'] ?? [];

            return [
                'ok' => true,
                'sebab' => 'ok',
                'pesan' => '',
                'diambil_pada' => $diambilPada,
                'profiles' => is_array($profiles) ? array_values(array_filter($profiles, 'is_array')) : [],
            ];
        });
    }

    /**
     * Kunci cache **per node**.
     *
     * Kunci yang tidak menyebut node membuat jawaban satu host dipakai untuk host lain:
     * armada yang tampak sehat karena satu node sehat. Itu kegagalan yang tidak
     * meninggalkan jejak apa pun.
     */
    private function cacheKey(HermesNode $node): string
    {
        return 'hermes.mirror.profiles.node.'.$node->getKey();
    }

    /**
     * Menerjemahkan pengecualian control plane menjadi sebab + pesan untuk operator.
     *
     * Urutan `instanceof` dari yang paling khusus: tujuh pengecualian T-82 semuanya
     * turunan `ControlPlaneException`, jadi mencocokkan induknya lebih dulu akan
     * meratakan semua menjadi satu galat - persis yang dicegah T-82 butir (d).
     *
     * Pesannya ditulis untuk orang yang harus **melakukan sesuatu**: masing-masing
     * menyebut tempat yang berbeda untuk diperiksa. Tidak ada nilai rahasia yang bisa
     * masuk ke sini - pesan dari klien hanya pernah menyebut **nama** referensi.
     *
     * @return array{0: string, 1: string}
     */
    private function sebabDari(Throwable $exception, HermesNode $node): array
    {
        $nama = (string) $node->name;

        return match (true) {
            $exception instanceof NodeHasNoControlPlane => [
                'tanpa_control_plane',
                "Node {$nama} belum punya alamat control plane, jadi daftar profilnya tidak bisa dibaca. Ini konfigurasi yang belum diisi di /admin/hermes-nodes - bukan node yang mati. Alamat bridge bukan penggantinya.",
            ],
            $exception instanceof ControlPlaneUnauthorized => [
                'belum_berwenang',
                "Kita belum berwenang membaca daftar profil di node {$nama}: kredensial control plane ditolak. Nodenya sendiri belum tentu bermasalah - periksa referensi rahasianya, dan ingat bahwa endpoint ini menolak semua pemanggil sampai auth server-ke-server (H-05) terpasang di Hermes.",
            ],
            $exception instanceof ControlPlaneRateLimited => [
                'dibatasi_laju',
                "Node {$nama} sedang membatasi laju permintaan. Ini keadaan wajar dengan jalan keluarnya sendiri: tunggu, lalu segarkan.",
            ],
            $exception instanceof ControlPlaneNotFound => [
                'tidak_ada_di_node',
                "Node {$nama} menjawab bahwa yang diminta tidak ada. Untuk daftar profil ini biasanya berarti rute dashboard sudah berpindah setelah Hermes diperbarui - periksa daftar-putih path, jangan melonggarkannya.",
            ],
            $exception instanceof ControlPlaneSessionExpired => [
                'sesi_kedaluwarsa',
                "Sesi di node {$nama} sudah kedaluwarsa. Mulai ulang alurnya.",
            ],
            $exception instanceof ControlPlanePathDenied => [
                'ditolak_daftar_putih',
                'Panggilan ini ditolak daftar-putih path control plane sebelum menyentuh jaringan. Ini bug di sisi kita, bukan gangguan node.',
            ],
            $exception instanceof ControlPlaneUnavailable => [
                'tidak_terjangkau',
                "Node {$nama} tidak dapat dihubungi atau sedang sakit. Periksa proses Hermes dan jalur jaringan ke control plane-nya.",
            ],
            // Jaring terakhir. Pesan pengecualiannya sengaja **tidak** ditempelkan:
            // teks bebas dari lapisan bawah bisa memuat apa saja, dan layar ini tidak
            // punya penyaring rahasia sendiri.
            default => [
                'gagal',
                "Daftar profil node {$nama} gagal dibaca karena sebab yang tidak dikenali. Periksa log aplikasi.",
            ],
        };
    }

    /**
     * Baris untuk profil yang ada di kedua sisi.
     *
     * @param  array<string, mixed>  $profilNode
     * @return array<string, mixed>
     */
    private function barisCocok(string $nama, array $profilNode, HermesProfile $milik, string $diambilPada): array
    {
        return [
            'nama_profil_node' => $nama,
            'profil_id' => $milik->getKey(),
            'instance_id' => (string) $milik->instance_id,
            'label' => (string) ($milik->label ?? ''),
            // Nama company saja. Id dan slug tidak dibutuhkan untuk menilai "siapa yang
            // dilayani profil ini", dan setiap field tambahan adalah satu peluang lagi
            // membocorkan sesuatu ke HTML.
            'companies' => $milik->companies->pluck('name')->values()->all(),
            'status' => (string) ($milik->status ?? ''),
            // T-68 tidak dilonggarkan: profil milik platform melayani **nol** company,
            // jadi daftar company kosong padanya adalah keadaan yang benar - bukan cacat
            // data. Tanpa penanda ini keduanya tidak bisa dibedakan di layar.
            'milik_platform' => (bool) $milik->is_platform_provided,
            'berjalan_di_node' => $this->berjalan($profilNode),
            'bawaan_node' => (bool) ($profilNode['is_default'] ?? false),
            'diambil_pada' => $diambilPada,
        ];
    }

    /**
     * Baris untuk profil yang berjalan di node tanpa baris kita.
     *
     * `profil_id` null bukan data yang hilang - itulah keluhannya: tidak ada pembukuan
     * di belakang bot ini.
     *
     * @param  array<string, mixed>  $profilNode
     * @return array<string, mixed>
     */
    private function barisYatim(string $nama, array $profilNode, string $diambilPada): array
    {
        return [
            'nama_profil_node' => $nama,
            'profil_id' => null,
            'instance_id' => null,
            'label' => null,
            'companies' => [],
            'status' => null,
            'milik_platform' => false,
            'berjalan_di_node' => $this->berjalan($profilNode),
            // Setiap Hermes punya profil `default`. Menyaringnya dari kelompok ini akan
            // menyembunyikan bot dev yang nyata berjalan; membiarkannya tanpa penanda
            // membuat kelompok yatim selalu berisi satu baris yang wajar - dan kelompok
            // yang selalu merah akan diabaikan. Jadi ia tetap tampil, tetapi bertanda.
            'bawaan_node' => (bool) ($profilNode['is_default'] ?? false),
            'diambil_pada' => $diambilPada,
        ];
    }

    /**
     * Baris untuk pemetaan kita yang tidak punya profil di node.
     *
     * Baris ini **tidak** disentuh di basis data. Ia hanya ditandai.
     *
     * @return array<string, mixed>
     */
    private function barisHilang(HermesProfile $milik, string $diambilPada): array
    {
        return [
            'nama_profil_node' => null,
            'profil_id' => $milik->getKey(),
            'instance_id' => (string) $milik->instance_id,
            'label' => (string) ($milik->label ?? ''),
            'companies' => $milik->companies->pluck('name')->values()->all(),
            'status' => (string) ($milik->status ?? ''),
            'milik_platform' => (bool) $milik->is_platform_provided,
            'berjalan_di_node' => false,
            'bawaan_node' => false,
            'diambil_pada' => $diambilPada,
        ];
    }

    /**
     * Apakah gateway profil ini hidup menurut node.
     *
     * `null` bila node tidak mengatakannya, dan itu dibedakan dari `false`: "tidak
     * tahu" dan "mati" bukan hal yang sama, dan menampilkan yang pertama sebagai yang
     * kedua akan memicu pemeriksaan yang tidak perlu.
     *
     * @param  array<string, mixed>  $profilNode
     */
    private function berjalan(array $profilNode): ?bool
    {
        return array_key_exists('gateway_running', $profilNode)
            ? (bool) $profilNode['gateway_running']
            : null;
    }
}
