<?php

namespace App\Services\Hermes;

use App\Models\HermesNode;
use App\Models\HermesProfile;
use Throwable;

/**
 * Keadaan runtime armada Hermes, dibaca dari control plane (T-84).
 *
 * **HTTP 200 bukan bukti sehat.** Pelajaran yang sama dengan `/health` bridge di
 * T-81, dan buktinya ada di instalasi ini: `gateway_state.json` memuat
 * `whatsapp: fatal whatsapp_not_paired` sementara servernya menjawab normal. Jadi
 * keputusan diambil dari **isi badan respons**, bukan dari kode HTTP.
 *
 * Daftar keadaan mati tidak dikarang: ia disalin dari `_PLATFORM_DEAD_STATES` milik
 * Hermes (`fatal`, `disconnected`, `stopped`). Kalau kita menyusun daftar sendiri,
 * penilaian kita dan penilaian dashboard Hermes akan berbeda untuk keadaan yang sama
 * - dan operator akan percaya yang mana pun yang ia buka lebih dulu.
 *
 * **Tiga keadaan dibedakan, tidak diringkas jadi "gagal"**, karena masing-masing
 * mengirim operator ke tempat yang berbeda:
 *
 * - `node_tidak_terjangkau` - proses Hermes atau jaringannya.
 * - `platform_bermasalah` - konfigurasi atau pairing **di dalam** profil; nodenya
 *   sendiri sehat.
 * - `sehat` - tidak ada yang perlu dilakukan.
 *
 * Ditambah dua keadaan yang bukan kerusakan tetapi juga bukan sehat:
 * `tanpa_control_plane` (belum dikonfigurasi) dan `belum_berwenang` (keadaan hari ini
 * sampai H-05 mendarat di Hermes).
 *
 * **Kelas ini tidak menulis apa pun ke basis data.** `hermes_profiles.status` sudah
 * punya penulis tunggal - `ProfileStatusRefresher`, dari bridge WhatsApp (T-81).
 * Menambah penulis kedua dari sumber berbeda menghasilkan dua kebenaran yang saling
 * menimpa tiap sepuluh menit, dan tidak ada yang bisa tahu mana yang benar. Keadaan
 * gateway dan keadaan nomor WhatsApp **memang bisa berbeda secara sah**: gateway
 * hidup sementara nomornya lepas adalah keadaan yang paling perlu terlihat, dan
 * memerasnya menjadi satu kolom akan menyembunyikannya.
 */
class FleetMonitor
{
    /**
     * Keadaan platform yang dianggap mati oleh Hermes sendiri.
     *
     * Disalin dari `_PLATFORM_DEAD_STATES` di `hermes_cli/web_server.py`.
     *
     * @var list<string>
     */
    private const KEADAAN_MATI = ['fatal', 'disconnected', 'stopped'];

    public function __construct(private readonly HermesControlPlaneClient $client) {}

    /**
     * Keadaan satu profil pada satu node.
     *
     * @return array{
     *     node_id: int|string, node_name: string, profil: string, keadaan: string,
     *     pesan: string, diambil_pada: string,
     *     platform: list<array{id: string, aktif: bool, keadaan: string, mati: bool}>,
     *     platform_bermasalah: list<array{id: string, keadaan: string}>
     * }
     */
    public function forProfile(HermesNode $node, string $profileName): array
    {
        $dasar = [
            'node_id' => $node->getKey(),
            'node_name' => (string) $node->name,
            'profil' => $profileName,
            'diambil_pada' => now()->toIso8601String(),
            'platform' => [],
            'platform_bermasalah' => [],
        ];

        try {
            $jawaban = $this->client->messagingPlatforms($node, $profileName);
        } catch (Throwable $exception) {
            [$keadaan, $pesan] = $this->keadaanDari($exception, $node);

            // `array_merge`, bukan `+`: pada operator `+` kunci **kiri** yang menang,
            // sehingga larik kosong di `$dasar` akan menimpa hasil sebenarnya. Cacat
            // itu sempat lolos di sini dan ditangkap test.
            return array_merge($dasar, ['keadaan' => $keadaan, 'pesan' => $pesan]);
        }

        // Hanya field yang dibutuhkan untuk menilai sehat/tidak yang disalin. Jawaban
        // node juga memuat `env_path`, `gateway_start_command`, dan daftar env yang
        // sudah diredaksi Hermes - semuanya tidak diperlukan di sini, dan setiap field
        // yang diteruskan adalah satu peluang lagi membocorkan sesuatu ke layar.
        $platform = [];
        $bermasalah = [];

        foreach ((array) ($jawaban['platforms'] ?? []) as $entri) {
            if (! is_array($entri)) {
                continue;
            }

            $id = (string) ($entri['id'] ?? $entri['platform_id'] ?? $entri['key'] ?? '');

            if ($id === '') {
                continue;
            }

            $aktif = (bool) ($entri['enabled'] ?? false);
            $keadaan = mb_strtolower(trim((string) ($entri['state'] ?? $entri['status'] ?? '')));
            // Platform yang sengaja dimatikan bukan kerusakan. Menghitungnya sebagai
            // masalah membuat setiap profil selalu merah, dan armada yang selalu merah
            // sama tidak bergunanya dengan armada yang selalu hijau.
            $mati = $aktif && in_array($keadaan, self::KEADAAN_MATI, true);

            $platform[] = ['id' => $id, 'aktif' => $aktif, 'keadaan' => $keadaan, 'mati' => $mati];

            if ($mati) {
                $bermasalah[] = ['id' => $id, 'keadaan' => $keadaan];
            }
        }

        return array_merge($dasar, [
            'keadaan' => $bermasalah === [] ? 'sehat' : 'platform_bermasalah',
            'pesan' => $bermasalah === []
                ? ''
                : 'Node hidup, tetapi platform berikut mati: '.implode(', ', array_column($bermasalah, 'id')).'. Periksa konfigurasi dan pairing di dalam profil, bukan prosesnya.',
            'platform' => $platform,
            'platform_bermasalah' => $bermasalah,
        ]);
    }

    /**
     * Keadaan seluruh profil yang menempel pada satu node.
     *
     * @return list<array<string, mixed>>
     */
    public function forNode(HermesNode $node): array
    {
        return HermesProfile::query()
            ->where('node_id', $node->getKey())
            ->orderBy('id')
            ->get()
            ->map(fn (HermesProfile $profile): array => array_merge(
                $this->forProfile($node, (string) $profile->instance_id),
                ['profil_id' => $profile->getKey(), 'label' => (string) ($profile->label ?? '')],
            ))
            ->all();
    }

    /**
     * Keadaan seluruh armada: setiap node yang punya control plane.
     *
     * @return list<array<string, mixed>>
     */
    public function forFleet(): array
    {
        $baris = [];

        foreach (HermesNode::query()->orderBy('id')->get() as $node) {
            foreach ($this->forNode($node) as $keadaanProfil) {
                $baris[] = $keadaanProfil;
            }
        }

        return $baris;
    }

    public function sehat(string $keadaan): bool
    {
        return $keadaan === 'sehat';
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function keadaanDari(Throwable $exception, HermesNode $node): array
    {
        $nama = (string) $node->name;

        return match (true) {
            $exception instanceof NodeHasNoControlPlane => [
                'tanpa_control_plane',
                "Node {$nama} belum punya alamat control plane, jadi keadaan platformnya tidak bisa dibaca. Ini konfigurasi yang belum diisi, bukan node yang mati.",
            ],
            $exception instanceof ControlPlaneUnauthorized => [
                'belum_berwenang',
                "Kita belum berwenang membaca keadaan platform di node {$nama}. Nodenya belum tentu bermasalah - endpoint ini menolak semua pemanggil sampai auth server-ke-server (H-05) terpasang di Hermes.",
            ],
            $exception instanceof ControlPlaneRateLimited => [
                'dibatasi_laju',
                "Node {$nama} sedang membatasi laju permintaan. Tunggu, lalu periksa lagi.",
            ],
            // 5xx dan gagal koneksi sama-sama berarti "jangan cari di dalam profil":
            // yang perlu diperiksa adalah proses Hermes dan jalur jaringannya.
            $exception instanceof ControlPlaneUnavailable => [
                'node_tidak_terjangkau',
                "Node {$nama} tidak dapat dihubungi atau sedang sakit. Periksa proses Hermes dan jaringan ke control plane-nya.",
            ],
            default => [
                'gagal',
                "Keadaan platform node {$nama} gagal dibaca karena sebab yang tidak dikenali. Periksa log aplikasi.",
            ],
        };
    }
}
