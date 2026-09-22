<?php

namespace App\Services\Hermes;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Lapisan tipis di atas kontrak HTTP bridge WhatsApp Hermes.
 *
 * Kontraknya fakta, dibaca dari `scripts/whatsapp-bridge/bridge.js`:
 * `POST /send` dengan `{chatId, message}` dan `GET /health`. Dua sifatnya yang
 * mudah salah dipakai dijaga di sini supaya tidak perlu diingat di setiap
 * pemanggil:
 *
 * 1. `/health` menjawab **HTTP 200 walau WhatsApp terputus**, jadi keputusan
 *    sehat/tidak diambil dari field `status`, bukan dari kode HTTP.
 * 2. `/send` bisa menjawab 200 tanpa mengirim apa pun, jadi badan respons wajib
 *    menyatakan sukses.
 *
 * Kelas ini ada karena aturan "tanpa autentikasi hanya sah untuk loopback" tidak
 * boleh hidup di dua tempat. Ada dua pemanggil dengan kebijakan berbeda - lajur
 * tenant (`App\Services\HermesNodeClient`) dan lajur platform
 * (`PlatformHermesNodeClient`) - dan kalau masing-masing menyalin aturannya,
 * keduanya akan menyimpang cepat atau lambat. Yang membedakan kedua lajur adalah
 * **profil siapa yang boleh dipakai**, bukan cara memanggil bridge.
 *
 * Kelas ini sengaja **tidak menulis log**: yang tahu konteksnya (company, node,
 * profil) adalah pemanggil, dan nomor tujuan beserta isi pesan tidak boleh ikut
 * tercatat.
 */
class BridgeGateway
{
    /**
     * Mengirim satu pesan teks.
     *
     * @throws RuntimeException Untuk setiap kegagalan, termasuk respons yang tidak
     *                          mengonfirmasi pengiriman.
     */
    public function sendText(string $apiUrl, string $secretReference, string $to, string $message): void
    {
        $recipient = $this->normalizeNumber($to);

        if ($recipient === '') {
            throw new RuntimeException('Nomor tujuan WhatsApp tidak dapat dibaca.');
        }

        if (trim($message) === '') {
            throw new RuntimeException('Pesan WhatsApp kosong tidak dikirim.');
        }

        $url = $this->endpoint($apiUrl, (string) config('hermes.delivery.send_path', '/send'));
        $headers = $this->authHeaders($secretReference, $apiUrl);

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
            throw new RuntimeException('Node Hermes tidak dapat dihubungi: '.$exception->getMessage(), 0, $exception);
        }

        if ($response->failed()) {
            throw new RuntimeException('Node Hermes menolak pengiriman (HTTP '.$response->status().').');
        }

        $body = $response->json();

        // Bridge menjawab `{success: true, messageId, messageIds}`. `ok` dan `sent`
        // diterima sebagai padanan supaya gateway lain bisa dipakai tanpa mengubah
        // kelas ini.
        if (! is_array($body) || ($body['success'] ?? $body['ok'] ?? $body['sent'] ?? false) !== true) {
            throw new RuntimeException('Node Hermes tidak mengonfirmasi pesan terkirim.');
        }
    }

    /**
     * Memeriksa satu alamat bridge tanpa mengirim pesan apa pun.
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
     * Header autentikasi untuk node, atau kosong bila node itu **menyatakan secara
     * eksplisit** bahwa ia tidak punya autentikasi.
     *
     * Bridge WhatsApp Hermes memang tidak punya token apa pun di port 3000 - sudah
     * diperiksa pada sumbernya. Memaksa operator mengisi referensi rahasia palsu
     * hanya supaya lolos aturan kita akan menyimpan kebohongan di basis data, dan
     * menyembunyikan fakta bahwa jalur itu tidak terlindungi.
     *
     * Karena itu ketiadaan autentikasi harus **dinyatakan**, dan hanya sah untuk
     * loopback: node tanpa autentikasi pada alamat publik berarti siapa pun bisa
     * mengirim WhatsApp sebagai nomor kita atau nomor tenant.
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
     * Rahasia node diambil dari daftar di konfigurasi, bukan dari basis data. Yang
     * tersimpan di `hermes_nodes.api_secret_reference` hanyalah namanya, dan pesan
     * galat pun hanya menyebut nama itu - tidak pernah nilainya.
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
