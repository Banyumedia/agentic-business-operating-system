<?php

namespace App\Services\Hermes;

use App\Models\HermesNode;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Satu-satunya kelas yang boleh memanggil dashboard API Hermes (D-72).
 *
 * **Kenapa satu pintu.** Rute `/api/*` dashboard Hermes tidak berversi dan sebagian
 * besar masih berada di dalam `web_server.py`; pembaruan Hermes dapat memindahkannya
 * tanpa peringatan. Dengan seluruh pemakaian dikurung di sini, kerusakan itu muncul
 * sebagai **satu test merah**, bukan sebagai fitur yang diam-diam mati di produksi.
 *
 * **Kenapa daftar-putih, bukan proxy.** Port yang sama menyajikan tulis-berkas dan
 * terminal, jadi token dashboard setara eksekusi kode di host Hermes. Lihat
 * `ControlPlanePaths`.
 *
 * **Batas dengan bridge.** `hermes_nodes.api_url` dan `hermes_profiles.api_url`
 * (T-81) adalah alamat **bridge WhatsApp**: loopback, tanpa autentikasi, satu port
 * per nomor. Control plane punya `control_url` + `control_secret_reference`
 * tersendiri. Mencampurnya mengirim token ke port yang tidak memintanya.
 *
 * **Yang TIDAK dikerjakan kelas ini:** ia tidak menyimpan apa pun. Keadaan runtime
 * milik Hermes; `hermes_profiles` tetap otoritas peta company↔profil (D-37). Cermin
 * bersifat read-through supaya tidak ada salinan yang bisa menyimpang diam-diam
 * (D-72 butir 2).
 */
class HermesControlPlaneClient
{
    /**
     * Kunci yang nilainya tidak boleh pernah masuk log.
     *
     * `qr_payload` adalah kredensial sesi WhatsApp - siapa pun yang memilikinya bisa
     * memasang perangkat sebagai nomor itu. `soul` dan `content` adalah isi prompt
     * yang bisa panjang dan memuat data tenant.
     *
     * @var list<string>
     */
    private const REDACTED_KEYS = [
        'qr_payload', 'qr', 'qrcode', 'soul', 'content', 'token', 'access_token',
        'secret', 'api_key', 'apikey', 'password', 'env', 'creds', 'authorization',
    ];

    /**
     * Memanggil satu path dashboard API.
     *
     * @param  string  $template  Template path, mis. `/api/profiles/{name}/soul`.
     *                            **Template**, bukan path terisi: yang dicocokkan ke
     *                            daftar-putih harus bentuk yang tidak bisa disetir
     *                            oleh nilai parameter.
     * @param  array<string, string>  $pathParams  Nilai untuk placeholder di template.
     * @param  array<string, mixed>  $payload  Badan permintaan untuk POST/PUT/PATCH.
     * @param  array<string, mixed>  $query  Parameter query tambahan.
     * @return array<mixed>
     *
     * @throws ControlPlaneException
     */
    public function call(
        HermesNode $node,
        string $method,
        string $template,
        array $pathParams = [],
        ?string $profile = null,
        array $payload = [],
        array $query = [],
    ): array {
        $method = strtoupper($method);

        // Urutan pemeriksaan disengaja: seluruh penolakan terjadi **sebelum** ada
        // permintaan HTTP. Kalau daftar-putih diperiksa setelah permintaan dikirim,
        // node sudah menerima panggilan yang tidak boleh pernah dibuat.
        if (ControlPlanePaths::isForbidden($template)) {
            throw new ControlPlanePathDenied("Path control plane dilarang permanen: {$method} {$template}.");
        }

        $scope = ControlPlanePaths::profileScopeFor($method, $template);

        if ($scope === null) {
            throw new ControlPlanePathDenied("Path control plane di luar daftar-putih: {$method} {$template}.");
        }

        $profile = $profile !== null ? trim($profile) : null;

        if (in_array($scope, [ControlPlanePaths::PROFILE_QUERY, ControlPlanePaths::PROFILE_BODY], true)
            && ($profile === null || $profile === '')) {
            // Tanpa nama profil, Hermes memakai **profil aktif** - state bersama yang
            // bisa berubah kapan saja. Pada armada multi-tenant itu berarti
            // mengonfigurasi tenant yang salah tanpa satu pun galat muncul.
            throw new ControlPlanePathDenied("Endpoint {$method} {$template} wajib menyebut profil secara eksplisit.");
        }

        $controlUrl = trim((string) ($node->control_url ?? ''));

        if ($controlUrl === '') {
            throw new NodeHasNoControlPlane(
                "Node {$node->name} belum punya alamat control plane. Alamat bridge bukan penggantinya."
            );
        }

        $headers = $this->authHeaders((string) ($node->control_secret_reference ?? ''), $controlUrl);
        $url = $this->url($controlUrl, $template, $pathParams);

        if ($scope === ControlPlanePaths::PROFILE_QUERY) {
            $query['profile'] = $profile;
        }

        if ($scope === ControlPlanePaths::PROFILE_BODY) {
            $payload['profile'] = $profile;
        }

        try {
            $request = Http::acceptJson()
                ->withHeaders($headers)
                ->timeout((int) config('hermes.control.timeout', 10));

            $response = match ($method) {
                'GET' => $request->get($url, $query),
                'DELETE' => $request->asJson()->delete($url, $payload ?: $query),
                default => $request->asJson()->send($method, $this->withQuery($url, $query), ['json' => $payload]),
            };
        } catch (Throwable $exception) {
            $this->logFailure($node, $method, $template, null, $exception->getMessage());

            throw new ControlPlaneUnavailable(
                "Control plane node {$node->name} tidak dapat dihubungi: ".$exception->getMessage(),
                0,
                $exception,
            );
        }

        if ($response->failed()) {
            $this->logFailure($node, $method, $template, $response->status(), $this->safeBody($response));

            throw $this->exceptionFor($response, $node, $method, $template);
        }

        $body = $response->json();

        return is_array($body) ? $body : ['raw' => mb_substr((string) $response->body(), 0, 500)];
    }

    /**
     * Memetakan kode status ke pengecualian yang **berbeda-beda**.
     *
     * Menyatukannya menjadi satu galat membuat layar berbohong: 410 dan 429 adalah
     * keadaan wajar dengan jalan keluar sendiri, bukan kerusakan.
     */
    private function exceptionFor(Response $response, HermesNode $node, string $method, string $template): ControlPlaneException
    {
        $where = "{$method} {$template} pada node {$node->name}";

        return match (true) {
            $response->status() === 401 || $response->status() === 403 => new ControlPlaneUnauthorized(
                "Kredensial control plane ditolak ({$where}). Periksa referensi rahasia node."
            ),
            $response->status() === 404 => new ControlPlaneNotFound("Profil atau sesi tidak ada di node ({$where})."),
            $response->status() === 410 => new ControlPlaneSessionExpired("Sesi pairing sudah kedaluwarsa ({$where}). Mulai ulang."),
            $response->status() === 429 => new ControlPlaneRateLimited("Hermes sedang membatasi laju atau mengunci pairing ({$where})."),
            $response->serverError() => new ControlPlaneUnavailable("Control plane node sakit, HTTP {$response->status()} ({$where})."),
            default => new ControlPlaneException("Control plane menolak permintaan, HTTP {$response->status()} ({$where})."),
        };
    }

    /**
     * Header autentikasi control plane.
     *
     * H-05 menambahkan provider **bearer** di sisi Hermes, jadi bentuknya
     * `Authorization: Bearer <token>`. Ketiadaan autentikasi mengikuti aturan yang
     * sama dengan bridge: harus **dinyatakan** lewat referensi `none`, dan hanya sah
     * untuk loopback. Control plane tanpa token di alamat publik berarti siapa pun
     * bisa menulis SOUL dan menyetujui pairing di host kita.
     *
     * @return array<string, string>
     */
    private function authHeaders(string $reference, string $controlUrl): array
    {
        $noAuth = (string) config('hermes.delivery.no_auth_reference', 'none');

        if ($reference === '' || $reference === $noAuth) {
            if (! $this->isLoopback($controlUrl)) {
                throw new ControlPlaneUnauthorized(
                    'Control plane tanpa autentikasi hanya diizinkan pada alamat loopback.'
                );
            }

            return [];
        }

        $secret = data_get(config('hermes.control_secrets', []), $reference);

        if (! is_string($secret) || $secret === '') {
            // Nama referensi disebut, nilainya tidak pernah - pola yang sama dengan
            // `BridgeGateway::secretFor()`.
            throw new ControlPlaneUnauthorized("Rahasia control plane belum dipasang untuk referensi {$reference}.");
        }

        return ['Authorization' => 'Bearer '.$secret];
    }

    private function isLoopback(string $url): bool
    {
        return in_array(parse_url($url, PHP_URL_HOST), ['127.0.0.1', 'localhost', '::1', '[::1]'], true);
    }

    /**
     * @param  array<string, string>  $pathParams
     */
    private function url(string $controlUrl, string $template, array $pathParams): string
    {
        if (! str_starts_with($controlUrl, 'http://') && ! str_starts_with($controlUrl, 'https://')) {
            throw new ControlPlaneException('Alamat control plane tidak valid.');
        }

        $path = $template;

        foreach ($pathParams as $key => $value) {
            // Nilai di-encode per segmen: nama profil `../fs/write-text` tidak boleh
            // bisa kabur dari path yang sudah lolos daftar-putih.
            $path = str_replace('{'.$key.'}', rawurlencode((string) $value), $path);
        }

        if (str_contains($path, '{')) {
            throw new ControlPlaneException("Parameter path control plane belum lengkap: {$template}.");
        }

        return rtrim($controlUrl, '/').'/'.ltrim($path, '/');
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function withQuery(string $url, array $query): string
    {
        return $query === [] ? $url : $url.'?'.http_build_query($query);
    }

    private function logFailure(HermesNode $node, string $method, string $template, ?int $status, string $detail): void
    {
        Log::warning('Panggilan control plane Hermes gagal', [
            'node_id' => $node->id,
            // Template, bukan path terisi: nama profil tidak perlu ikut tercatat.
            'endpoint' => $method.' '.$template,
            'status' => $status,
            'detail' => $this->redact($detail),
        ]);
    }

    /**
     * Badan respons dipotong seperti pola `HermesNodeClient::ping()`, lalu disaring.
     */
    private function safeBody(Response $response): string
    {
        $body = $response->json();

        if (is_array($body)) {
            foreach (self::REDACTED_KEYS as $key) {
                if (array_key_exists($key, $body)) {
                    $body[$key] = '[disaring]';
                }
            }

            return mb_substr((string) json_encode($body), 0, 300);
        }

        return mb_substr((string) $response->body(), 0, 300);
    }

    /**
     * Jaring terakhir: kalau nilai rahasia tetap lolos ke dalam teks bebas (mis. pesan
     * pengecualian dari klien HTTP), ia dihapus di sini sebelum menyentuh log.
     */
    private function redact(string $detail): string
    {
        foreach (array_merge(
            array_values((array) config('hermes.control_secrets', [])),
            array_values((array) config('hermes.node_secrets', [])),
        ) as $secret) {
            if (is_string($secret) && $secret !== '') {
                $detail = str_replace($secret, '[disaring]', $detail);
            }
        }

        foreach (self::REDACTED_KEYS as $key) {
            // Bentuk JSON `"qr_payload":"..."` maupun `qr_payload=...` sama-sama ditutup.
            $detail = (string) preg_replace('/"'.preg_quote($key, '/').'"\s*:\s*"[^"]*"/i', '"'.$key.'":"[disaring]"', $detail);
            $detail = (string) preg_replace('/\b'.preg_quote($key, '/').'=[^&\s]+/i', $key.'=[disaring]', $detail);
        }

        return $detail;
    }
}
