<?php

namespace App\Services;

use App\Models\Company;
use App\Models\HermesProfile;
use App\Services\Hermes\BridgeGateway;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Pengiriman WhatsApp lewat node Hermes milik tenant.
 *
 * Sebelumnya kelas ini **selalu melempar** ("not configured for actual delivery
 * in this environment"), jadi T-49 (pengingat piutang), T-51 (undangan staf), dan
 * efek workflow `notify_owner_wa` hanya pernah terbukti lewat mock. Sekarang ia
 * benar-benar memanggil node, tetap fail-closed di setiap simpul.
 *
 * Rantai penyelesaian - kegagalan di simpul mana pun berarti **tidak mengirim**,
 * bukan mengirim lewat jalur lain:
 *
 * 1. Company harus ada.
 * 2. Harus ada `hermes_profiles` bertipe `primary` yang **melayani company itu**
 *    lewat pivot `hermes_profile_companies`. Tanpa pembatasan ini, bot satu usaha
 *    bisa mengirim atas nama usaha lain yang kebetulan satu pemilik.
 * 3. Profil harus berstatus siap (`config('hermes.delivery.ready_statuses')`).
 *    Profil `unpaired` belum menempel ke nomor WhatsApp mana pun.
 * 4. Node harus ada dan `status = active`.
 * 5. Rahasia node diselesaikan dari **referensi**, bukan disimpan plaintext di
 *    basis data (COMMERCIAL_AND_AI_AGENTIC_SPEC §Hermes Profile) - dijaga
 *    `BridgeGateway`, beserta aturan "tanpa autentikasi hanya sah untuk loopback".
 * 6. Respons non-2xx, koneksi gagal, atau badan respons yang tidak menyatakan
 *    sukses = gagal.
 *
 * Verifikasi nomor penerima **bukan** tanggung jawab kelas ini: pemanggil yang
 * tahu siapa penerimanya (mis. `ReceivableReminderService` memeriksa
 * `wa_is_verified` milik owner). Di sini yang dijaga adalah jalur pengirimannya.
 *
 * PERHATIAN - jangan tertukar dengan `App\Contracts\HermesNodeClient`. Antarmuka
 * itu untuk WhatsApp **platform** (dunning langganan D-23/D-49) dan tidak
 * ter-scope company. Pesan atas nama tenant wajib lewat kelas ini (D-63).
 */
class HermesNodeClient
{
    private BridgeGateway $gateway;

    public function __construct(?BridgeGateway $gateway = null)
    {
        // Tetap bisa di-`new` tanpa argumen: tiga test lama dan beberapa pemanggil
        // membuatnya langsung, dan memaksa mereka menyuntikkan gateway tidak
        // menambah jaminan apa pun.
        $this->gateway = $gateway ?? new BridgeGateway;
    }

    /**
     * Mengirim satu pesan WhatsApp untuk sebuah company.
     *
     * @throws RuntimeException Bila jalur pengiriman tidak sah atau pengiriman gagal.
     */
    public function sendWhatsAppMessage(string $companyId, string $to, string $message): void
    {
        $profile = $this->profileFor($companyId);
        $node = $profile->node;

        if ($node === null) {
            throw new RuntimeException("Profil Hermes belum ditempatkan pada node: company {$companyId}.");
        }

        // `draining` ikut diterima **untuk profil yang sudah tertempel**: node yang
        // sedang dikosongkan (T-105, `POST /api/gateway/drain`) berhenti menerima
        // penempatan baru tetapi tetap melayani nomor yang sudah jalan. Menolak
        // draining di sini berarti drain memutus tenant seketika, bukan mengosongkan
        // dengan tertib. `maintenance` dan `down` tetap ditolak - keduanya menyatakan
        // node tidak melayani sama sekali, dan kelonggaran draining tidak boleh
        // merembet ke sana.
        if (! in_array($node->status ?? null, ['active', 'draining'], true)) {
            throw new RuntimeException('Node Hermes tidak melayani pengiriman: '.($node->status ?? 'tidak diketahui').'.');
        }

        try {
            $this->gateway->sendText(
                $this->addressForProfile($profile),
                (string) $node->api_secret_reference,
                $to,
                $message,
            );
        } catch (RuntimeException $exception) {
            // Nomor dan isi pesan tidak ikut dicatat: log bukan tempat data
            // pelanggan. Yang dicatat adalah sebabnya.
            Log::warning('Pengiriman WhatsApp tenant gagal', [
                'company_id' => $companyId,
                'node_id' => $node->id,
                'reason' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    /**
     * Memeriksa satu node tanpa mengirim pesan apa pun.
     *
     * Dipakai `bos:hermes-ping` dan `ProfileStatusRefresher` supaya konfigurasi
     * bisa dibuktikan sebelum ada pesan sungguhan yang dikirim ke nomor siapa pun.
     *
     * @return array{ok: bool, status: int|null, detail: string}
     */
    public function ping(string $apiUrl, string $secretReference): array
    {
        return $this->gateway->ping($apiUrl, $secretReference);
    }

    /**
     * Alamat bridge yang dipakai sebuah profil.
     *
     * Satu bridge WhatsApp = satu nomor = satu port, jadi alamatnya milik
     * **profil**. `hermes_nodes.api_url` tinggal jadi cadangan untuk penyebaran
     * satu-bridge-satu-host dan untuk baris lama yang belum diisi - kalau alamat
     * itu satu-satunya tempat, setiap nomor baru memaksa satu baris node baru dan
     * `max_capacity` kehilangan arti.
     */
    public function addressForProfile(HermesProfile $profile): string
    {
        $own = trim((string) ($profile->api_url ?? ''));

        if ($own !== '') {
            return $own;
        }

        return trim((string) ($profile->node->api_url ?? ''));
    }

    private function profileFor(string $companyId): HermesProfile
    {
        $company = Company::query()->find($companyId);

        if ($company === null) {
            throw new RuntimeException("Company tidak ditemukan untuk pengiriman WhatsApp: {$companyId}.");
        }

        $profile = HermesProfile::query()
            ->with('node')
            ->where('type', 'primary')
            // Profil milik platform melayani nol company. Kalaupun seseorang
            // menautkannya lewat pivot, lajur tenant tidak boleh memakainya:
            // pesan atas nama tenant harus keluar dari bot tenant itu sendiri,
            // bukan dari bot platform (D-63).
            ->where(function ($query) {
                $query->where('is_platform_provided', false)
                    ->orWhereNull('is_platform_provided');
            })
            ->whereHas('companies', fn ($query) => $query->whereKey($company->getKey()))
            ->first();

        if ($profile === null) {
            throw new RuntimeException("Tidak ada profil Hermes yang melayani company {$companyId}.");
        }

        $ready = (array) config('hermes.delivery.ready_statuses', ['paired', 'active']);

        if (! in_array((string) $profile->status, $ready, true)) {
            throw new RuntimeException('Profil Hermes belum siap mengirim (status '.$profile->status.').');
        }

        return $profile;
    }
}
