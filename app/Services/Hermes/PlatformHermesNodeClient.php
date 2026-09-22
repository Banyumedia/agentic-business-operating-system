<?php

namespace App\Services\Hermes;

use App\Contracts\HermesNodeClient;
use App\Models\HermesProfile;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Pengirim WhatsApp **platform** yang benar-benar mengirim.
 *
 * Menggantikan `FakeHermesNodeClient` sebagai implementasi bawaan. Yang dipakai
 * fake itu mengembalikan `true` tanpa mengirim apa pun, sehingga dunning langganan
 * (D-23/D-49) tampak berjalan padahal tidak ada pelanggan yang pernah menerima
 * peringatan - dan kegagalannya tidak pernah terlihat karena tidak ada yang
 * memeriksa hasilnya.
 *
 * **Pengirimnya bot CS platform, bukan bot dev.** Keduanya profil
 * `is_platform_provided`, tetapi hanya tipe yang tercatat di
 * `hermes.platform.sender_profile_type` yang sah. Memakai bot dev berarti (a)
 * nomor internal kita beredar ke pelanggan, dan (b) pelanggan mendapat kanal ke
 * bot yang berwenang menjalankan perintah. Kalau profil CS belum ada atau belum
 * paired, kelas ini **menolak** - tidak ada jatuh kembali ke bot dev.
 *
 * Kontraknya mengembalikan `bool`, jadi kegagalan tidak melempar. Supaya tidak
 * kembali menjadi kegagalan senyap, setiap penolakan **dicatat** dengan sebabnya;
 * nomor tujuan dan isi pesan tidak ikut dicatat.
 */
class PlatformHermesNodeClient implements HermesNodeClient
{
    public function __construct(private readonly BridgeGateway $gateway) {}

    public function sendWhatsApp(string $waNumber, string $message): bool
    {
        try {
            $profile = $this->senderProfile();
            $node = $profile->node;

            if ($node === null) {
                throw new RuntimeException('Profil bot platform belum ditempatkan pada node.');
            }

            // Sama seperti lajur tenant (T-105): `draining` tetap melayani profil yang
            // sudah tertempel; hanya `maintenance`/`down` yang menolak sama sekali.
            if (! in_array($node->status ?? null, ['active', 'draining'], true)) {
                throw new RuntimeException('Node bot platform tidak melayani pengiriman: '.($node->status ?? 'tidak diketahui').'.');
            }

            $this->gateway->sendText(
                $this->address($profile),
                (string) $node->api_secret_reference,
                $waNumber,
                $message,
            );

            return true;
        } catch (Throwable $exception) {
            Log::warning('Pengiriman WhatsApp platform gagal', [
                'profile_type' => $this->senderType(),
                'reason' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @throws RuntimeException Bila tidak ada bot platform yang sah dan siap.
     */
    private function senderProfile(): HermesProfile
    {
        $type = $this->senderType();

        $profile = HermesProfile::query()
            ->with('node')
            ->where('is_platform_provided', true)
            ->where('type', $type)
            ->orderBy('id')
            ->first();

        if ($profile === null) {
            throw new RuntimeException("Belum ada bot platform bertipe {$type}. Daftarkan lewat bos:hermes-profile --platform.");
        }

        $ready = (array) config('hermes.delivery.ready_statuses', ['paired', 'active']);

        if (! in_array((string) $profile->status, $ready, true)) {
            throw new RuntimeException('Bot platform belum siap mengirim (status '.$profile->status.').');
        }

        return $profile;
    }

    private function senderType(): string
    {
        return (string) config('hermes.platform.sender_profile_type', 'addon');
    }

    /**
     * Alamat bridge milik profil, jatuh kembali ke alamat node - aturan yang sama
     * dengan lajur tenant (T-81).
     */
    private function address(HermesProfile $profile): string
    {
        $own = trim((string) ($profile->api_url ?? ''));

        return $own !== '' ? $own : trim((string) ($profile->node->api_url ?? ''));
    }
}
