<?php

namespace App\Services\Hermes;

use App\Models\HermesProfile;
use App\Services\HermesNodeClient;

/**
 * Menurunkan `hermes_profiles.status` dari kenyataan bridge, bukan dari ketikan.
 *
 * Sebelum ini status hanya bisa berubah lewat basis data, jadi ia bisa berbohong:
 * profil bertanda `paired` padahal nomornya sudah lepas akan membuat setiap
 * pengiriman dicoba lalu gagal, tanpa petunjuk di mana masalahnya. Dua jebakan
 * yang ditutup di sini:
 *
 * 1. Bridge menjawab **HTTP 200 walau WhatsApp terputus** - keputusan diambil dari
 *    field `status` di badan respons (lewat `HermesNodeClient::ping()`), bukan dari
 *    kode HTTP.
 * 2. Profil yang belum ditempatkan pada node dan belum punya alamat sendiri
 *    **tidak dihubungi sama sekali**. Tidak ada alamat berarti tidak ada yang bisa
 *    diperiksa; menebak `localhost` akan memeriksa bridge milik profil lain.
 *
 * Kosakata yang ditulis hanya dua: `paired` bila bridge menyatakan `connected`,
 * `unpaired` untuk segala hal lain. Sisi baca tetap permisif
 * (`hermes.delivery.ready_statuses`) supaya baris lama tidak mendadak berhenti
 * mengirim, tetapi sisi tulis tidak menambah sinonim baru.
 */
class ProfileStatusRefresher
{
    public function __construct(private readonly HermesNodeClient $client) {}

    /**
     * Memeriksa satu profil dan menyimpan hasilnya.
     *
     * @return array{profile_id: int|string, label: string, address: string, ok: bool, status: string, detail: string}
     */
    public function refresh(HermesProfile $profile): array
    {
        $address = $this->client->addressForProfile($profile);
        $label = (string) ($profile->label ?: $profile->instance_id);

        if ($address === '') {
            return [
                'profile_id' => $profile->getKey(),
                'label' => $label,
                'address' => '',
                'ok' => false,
                'status' => (string) $profile->status,
                'detail' => 'Profil belum punya alamat bridge maupun node.',
            ];
        }

        $result = $this->client->ping($address, $this->secretReferenceFor($profile));
        $status = $result['ok'] ? 'paired' : 'unpaired';

        $profile->forceFill([
            'status' => $status,
            'last_ping_at' => now(),
        ])->save();

        return [
            'profile_id' => $profile->getKey(),
            'label' => $label,
            'address' => $address,
            'ok' => $result['ok'],
            'status' => $status,
            'detail' => $result['detail'],
        ];
    }

    /**
     * Rahasia tetap milik node: profil hanya menyimpan alamatnya.
     *
     * Profil yang punya alamat sendiri tanpa node jatuh ke referensi
     * "tanpa autentikasi", yang oleh `HermesNodeClient` hanya diizinkan pada
     * loopback - jadi alamat publik tanpa node tetap ditolak, bukan dihubungi
     * telanjang.
     */
    private function secretReferenceFor(HermesProfile $profile): string
    {
        $reference = (string) ($profile->node->api_secret_reference ?? '');

        return $reference !== ''
            ? $reference
            : (string) config('hermes.delivery.no_auth_reference', 'none');
    }
}
