<?php

namespace App\Services\Hermes;

use App\Models\HermesNode;
use App\Models\HermesProfile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Memilih node dan port untuk profil Hermes baru (T-105).
 *
 * Ada karena `ensurePrimaryProfile()` dulu tidak pernah mengisi `node_id`: profil
 * lahir tanpa node, lalu `HermesNodeClient` menolak "belum ditempatkan pada node".
 * Tenant baru mendapat bot yang tidak akan pernah bisa mengirim, dan penyebabnya
 * muncul jauh dari kejadian - saat pesan pertama, bukan saat provisioning.
 *
 * Dua keputusan yang dijaga di sini:
 *
 * 1. **Kelayakan dari kenyataan, bukan dari kolom.** Node layak bila `status =
 *    'active'` (bukan maintenance/down/draining) DAN jumlah profil yang **nyata**
 *    menempel masih di bawah `max_capacity`. `active_profiles` sengaja tidak
 *    dipercaya sebagai satu-satunya kebenaran: kolom itu cache yang bisa menyimpang
 *    (QA-02), jadi node lowong bisa terlihat penuh dan menolak profil yang sah.
 *
 * 2. **Menolak, bukan menempatkan sembarangan.** Bila tidak ada node layak, melempar
 *    - gagal di depan lebih baik daripada tenant bisu. Membuat profil tanpa node
 *    hanya memindahkan kegagalan ke titik yang lebih jauh dan lebih membingungkan.
 *
 * Pemilihan mengunci baris node (`lockForUpdate`) supaya dua permintaan bersamaan
 * tidak sama-sama lolos cek kapasitas lalu keduanya menulis - cek-lalu-tulis tanpa
 * kunci adalah balapan yang bisa melewati `max_capacity`.
 */
class NodePlacement
{
    /**
     * Hasil penempatan: node terpilih dan port bridge yang dialokasikan.
     *
     * @return array{node: HermesNode, port: int}
     *
     * @throws RuntimeException Bila tidak ada node layak, atau node terpilih kehabisan
     *                          port dalam rentang yang dikonfigurasi.
     */
    public function place(): array
    {
        // Seluruh pemilihan dibungkus transaksi supaya `lockForUpdate` punya arti:
        // kunci baris hanya ditahan selama transaksi. Pemanggil (`ensurePrimaryProfile`)
        // juga sudah berjalan dalam transaksi; nested transaction Laravel menjadikan ini
        // savepoint, jadi aman dipanggil dari dalam maupun luar transaksi.
        return DB::transaction(function (): array {
            $node = $this->pickEligibleNodeLocked();

            if ($node === null) {
                throw new RuntimeException(
                    'Tidak ada node Hermes yang layak untuk penempatan: '
                    .'butuh node berstatus active dengan kapasitas tersisa. '
                    .'Daftarkan atau kosongkan node di /admin/hermes-nodes.'
                );
            }

            $port = $this->allocatePort($node);

            return ['node' => $node, 'port' => $port];
        });
    }

    /**
     * Node berstatus active dengan kapasitas nyata tersisa, barisnya dikunci.
     *
     * Diurutkan menaik berdasarkan id supaya penempatan deterministik dan mudah
     * dibuktikan di test. Kandidat dipersempit di basis data ke `status = 'active'`
     * lebih dulu; kapasitas dievaluasi dari hitungan nyata karena kolom `active_profiles`
     * bisa berbohong.
     */
    private function pickEligibleNodeLocked(): ?HermesNode
    {
        // Hanya `active` yang menerima penempatan baru. `draining` sengaja **tidak**
        // masuk: node yang sedang dikosongkan tetap melayani profil lama (dijaga di
        // HermesNodeClient) tetapi tidak boleh menerima tenant baru.
        $candidates = HermesNode::query()
            ->where('status', 'active')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($candidates as $node) {
            // Hitungan nyata, bukan kolom cache. Node yang belum penuh dipakai.
            if ($node->attachedProfileCount() < (int) $node->max_capacity) {
                return $node;
            }
        }

        return null;
    }

    /**
     * Port bebas terkecil dalam rentang config yang belum dipakai profil lain di
     * node ini.
     *
     * Rentang dibatasi supaya alokasi tidak menyentuh port di luar kesepakatan
     * (mis. webhook Cloud API bawaan Hermes di 8090). Unique-per-node di basis data
     * adalah jaring terakhir; alokasi ini mencoba tidak pernah sampai mengandalkannya.
     */
    private function allocatePort(HermesNode $node): int
    {
        $start = (int) config('hermes.placement.port_range.start', 3000);
        $end = (int) config('hermes.placement.port_range.end', 3099);

        // Port yang sudah dipakai profil lain **di node ini**. Port sama di node lain
        // tidak relevan - unique-nya per node.
        $used = HermesProfile::query()
            ->where('node_id', $node->id)
            ->whereNotNull('bridge_port')
            ->pluck('bridge_port')
            ->map(fn ($port) => (int) $port)
            ->all();

        $used = array_flip($used);

        for ($port = $start; $port <= $end; $port++) {
            if (! isset($used[$port])) {
                return $port;
            }
        }

        // Rentang habis: ini keadaan yang harus terlihat, bukan disembunyikan dengan
        // memakai ulang port yang sudah terpakai (yang akan membuat dua nomor
        // bertabrakan). max_capacity idealnya lebih kecil dari lebar rentang, tetapi
        // kalau operator menaikkannya, kegagalan ini menyebut alasannya.
        throw new RuntimeException(
            "Node {$node->name} kehabisan port bridge dalam rentang {$start}-{$end}."
        );
    }
}
