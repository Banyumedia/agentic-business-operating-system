<?php

namespace App\Services;

use App\Models\Company;
use App\Models\HermesProfile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Pengiriman WhatsApp lewat node Hermes milik tenant.
 *
 * Sebelum ini kelas ini **selalu melempar** ("not configured for actual delivery
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
 *    basis data (COMMERCIAL_AND_AI_AGENTIC_SPEC §Hermes Profile). Referensi yang
 *    tidak terdaftar = gagal, dan pesan galatnya menyebut nama referensinya saja,
 *    tidak pernah nilainya.
 * 6. Respons non-2xx, koneksi gagal, atau badan respons yang tidak menyatakan
 *    sukses = gagal.
 *
 * Verifikasi nomor penerima **bukan** tanggung jawab kelas ini: pemanggil yang
 * tahu siapa penerimanya (mis. `ReceivableReminderService` memeriksa
 * `wa_is_verified` milik owner). Di sini yang dijaga adalah jalur pengirimannya.
 *
 * PERHATIAN - jangan tertukar dengan `App\Contracts\HermesNodeClient`. Antarmuka
 * itu untuk WhatsApp **platform** (dunning langganan D-23/D-49), tidak ter-scope
 * company, dan implementasi bawaannya hanya mencatat ke log. Pesan atas nama
 * tenant wajib lewat kelas ini (D-63).
 */
class HermesNodeClient
{
    /**
     * Mengirim satu pesan WhatsApp untuk sebuah company.
     *
     * @throws RuntimeException Bila jalur pengiriman tidak sah atau pengiriman gagal.
     */
    public function sendWhatsAppMessage(string $companyId, string $to, string $message): void
    {
        $recipient = $this->normalizeNumber($to);

        if ($recipient === '') {
            throw new RuntimeException('Nomor tujuan WhatsApp tidak dapat dibaca.');
        }

        if (trim($message) === '') {
            throw new RuntimeException('Pesan WhatsApp kosong tidak dikirim.');
        }

        $profile = $this->profileFor($companyId);
        $node = $profile->node;

        if ($node === null) {
            throw new RuntimeException("Profil Hermes belum ditempatkan pada node: company {$companyId}.");
        }

        if (($node->status ?? null) !== 'active') {
            throw new RuntimeException('Node Hermes tidak aktif: '.($node->status ?? 'tidak diketahui').'.');
        }

        $address = $this->addressForProfile($profile);
        $url = $this->endpoint($address, (string) config('hermes.delivery.send_path', '/send'));
        $headers = $this->authHeaders((string) $node->api_secret_reference, $address);

        try {
            $response = Http::asJson()
                ->acceptJson()
                ->withHeaders($headers)
                ->timeout((int) config('hermes.delivery.timeout', 10))
                ->post($url, [
                    // Bridge menerima JID WhatsApp, bukan nomor telanjang.
                    'chatId' => $recipient.'@s.whatsapp.net',
                    'message' => $message,
                ]);
        } catch (Throwable $exception) {
            // Nomor dan isi pesan tidak ikut dicatat: log bukan tempat data pelanggan.
            Log::warning('Pengiriman WhatsApp gagal menghubungi node Hermes', [
                'company_id' => $companyId,
                'node_id' => $node->id,
                'error' => $exception->getMessage(),
            ]);

            throw new RuntimeException('Node Hermes tidak dapat dihubungi: '.$exception->getMessage(), 0, $exception);
        }

        if ($response->failed()) {
            throw new RuntimeException('Node Hermes menolak pengiriman (HTTP '.$response->status().').');
        }

        // Node yang menjawab 200 tapi tidak menyatakan terkirim tetap dianggap
        // gagal. Tanpa pemeriksaan ini, kegagalan di sisi node akan tercatat
        // sebagai pengingat terkirim dan tidak pernah dicoba ulang.
        $body = $response->json();

        // Bridge menjawab `{success: true, messageId, messageIds}`. `ok` dan
        // `sent` diterima sebagai padanan supaya gateway lain bisa dipakai tanpa
        // mengubah kelas ini.
        if (! is_array($body) || ($body['success'] ?? $body['ok'] ?? $body['sent'] ?? false) !== true) {
            throw new RuntimeException('Node Hermes tidak mengonfirmasi pesan terkirim.');
        }
    }

    /**
     * Memeriksa satu node tanpa mengirim pesan apa pun.
     *
     * Dipakai `bos:hermes-ping` supaya konfigurasi bisa dibuktikan sebelum ada
     * pesan sungguhan yang dikirim ke nomor siapa pun.
     *
     * @return array{ok: bool, status: int|null, detail: string}
     */
    public function ping(string $apiUrl, string $secretReference): array
    {
        try {
            $headers = $this->authHeaders($secretReference, $apiUrl);
            $url = $this->endpoint($apiUrl, (string) config('hermes.delivery.health_path', '/health'));
        } catch (RuntimeException $exception) {
            return ['ok' => false, 'status' => null, 'detail' => $exception->getMessage()];
        }

        try {
            $response = Http::acceptJson()
                ->withHeaders($headers)
                ->timeout((int) config('hermes.delivery.timeout', 10))
                ->get($url);
        } catch (Throwable $exception) {
            return ['ok' => false, 'status' => null, 'detail' => $exception->getMessage()];
        }

        if (! $response->successful()) {
            return [
                'ok' => false,
                'status' => $response->status(),
                'detail' => 'Node menjawab HTTP '.$response->status().'.',
            ];
        }

        // HTTP 200 belum berarti WhatsApp tersambung: bridge tetap menjawab 200
        // dengan `status: disconnected`. Melaporkannya sebagai sehat akan membuat
        // operator mencari masalah di tempat yang salah.
        $body = $response->json();
        $connection = is_array($body) ? (string) ($body['status'] ?? '') : '';
        $healthy = (array) config('hermes.delivery.healthy_statuses', ['connected']);

        if ($connection !== '' && ! in_array($connection, $healthy, true)) {
            return [
                'ok' => false,
                'status' => $response->status(),
                'detail' => 'Node hidup tetapi WhatsApp '.$connection.'.',
            ];
        }

        return [
            'ok' => true,
            'status' => $response->status(),
            'detail' => 'Node menjawab: '.mb_substr((string) $response->body(), 0, 200),
        ];
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

    /**
     * Header autentikasi untuk node, atau kosong bila node itu **menyatakan
     * secara eksplisit** bahwa ia tidak punya autentikasi.
     *
     * Bridge WhatsApp Hermes memang tidak punya token apa pun di port 3000 -
     * sudah diperiksa pada sumbernya. Memaksa operator mengisi referensi rahasia
     * palsu hanya supaya lolos aturan kita akan menyimpan kebohongan di basis
     * data, dan menyembunyikan fakta bahwa jalur itu tidak terlindungi.
     *
     * Karena itu ketiadaan autentikasi harus **dinyatakan**, dan hanya sah untuk
     * loopback: node tanpa autentikasi pada alamat publik berarti siapa pun bisa
     * mengirim WhatsApp sebagai nomor tenant.
     *
     * @return array<string, string>
     */
    private function authHeaders(string $reference, string $apiUrl): array
    {
        if ($reference === (string) config('hermes.delivery.no_auth_reference', 'none')) {
            if (! $this->isLoopback($apiUrl)) {
                throw new RuntimeException('Node tanpa autentikasi hanya diizinkan pada alamat loopback.');
            }

            return [];
        }

        return ['X-Hermes-Secret' => $this->secretFor($reference)];
    }

    private function isLoopback(string $apiUrl): bool
    {
        $host = parse_url($apiUrl, PHP_URL_HOST);

        return in_array($host, ['127.0.0.1', 'localhost', '::1', '[::1]'], true);
    }

    /**
     * Rahasia node diambil dari daftar di konfigurasi, bukan dari basis data.
     * Yang tersimpan di `hermes_nodes.api_secret_reference` hanyalah namanya.
     */
    private function secretFor(string $reference): string
    {
        if ($reference === '') {
            throw new RuntimeException('Node Hermes tidak punya referensi rahasia.');
        }

        $secret = data_get(config('hermes.node_secrets', []), $reference);

        if (! is_string($secret) || $secret === '') {
            throw new RuntimeException("Rahasia node Hermes belum dipasang untuk referensi {$reference}.");
        }

        return $secret;
    }

    private function endpoint(string $apiUrl, string $path): string
    {
        if (! str_starts_with($apiUrl, 'http://') && ! str_starts_with($apiUrl, 'https://')) {
            throw new RuntimeException('Alamat node Hermes tidak valid.');
        }

        return rtrim($apiUrl, '/').'/'.ltrim($path, '/');
    }

    private function normalizeNumber(?string $number): string
    {
        $digits = preg_replace('/[^0-9]/', '', (string) $number) ?? '';

        return str_starts_with($digits, '08') ? '628'.substr($digits, 2) : $digits;
    }
}
