<?php

namespace App\Services\Hermes;

/**
 * Daftar-putih path dashboard API Hermes (D-72 butir 1).
 *
 * Alasannya bukan kehati-hatian abstrak: **port yang sama** menyajikan
 * `/api/fs/write-text`, `/api/files/upload`, `/api/tools/terminal/*`, `/api/git/*`,
 * dan `/api/profiles/{name}/open-terminal`. Artinya token dashboard setara
 * **eksekusi kode** di host Hermes. Kalau kelas klien kita menerima path sembarang,
 * satu bug di lapisan atas berubah menjadi RCE di host tenant, dan seluruh
 * `EnforceBotToolScoping` + D-69 menjadi hiasan.
 *
 * Karena itu daftar ini **konstanta, bukan konfigurasi**: menambah path harus
 * menjadi perubahan kode yang terlihat di diff dan lolos test, bukan baris di
 * `.env` yang bisa diubah diam-diam.
 *
 * Nilai tiap entri menyatakan **di mana nama profil harus dikirim**. Ini fakta yang
 * dibaca dari `hermes_cli/web_server.py`, bukan tebakan:
 *
 * - `PROFILE_QUERY` - handler menerima `profile: Optional[str] = None` sebagai
 *   parameter query (mis. `GET /api/pairing`, `GET /api/status`).
 * - `PROFILE_BODY` - handler membaca `body.profile` (mis. `POST /api/pairing/approve`
 *   yang memanggil `_pairing_store(body.profile)`).
 * - `PROFILE_IN_PATH` - nama profil sudah menjadi bagian path, jadi tidak ada yang
 *   bisa jatuh ke profil aktif.
 * - `PROFILE_NONE` - benar-benar tidak ter-scope profil (`GET /api/health`,
 *   `GET /api/system/stats`, dan sesi onboarding yang dicari berdasarkan
 *   `pairing_id`).
 *
 * Perbedaan query-vs-body bukan detail gaya: mengirim profil di tempat yang salah
 * membuat Hermes **mengabaikannya** dan permintaan jatuh ke profil yang sedang
 * aktif - tenant yang salah dikonfigurasi, tanpa galat.
 */
final class ControlPlanePaths
{
    public const PROFILE_NONE = 'none';

    public const PROFILE_QUERY = 'query';

    public const PROFILE_BODY = 'body';

    public const PROFILE_IN_PATH = 'path';

    /**
     * Satu-satunya path yang boleh dipanggil.
     *
     * @var array<string, string>
     */
    public const ALLOWED = [
        'GET /api/profiles' => self::PROFILE_NONE,
        'POST /api/profiles' => self::PROFILE_NONE,
        'PATCH /api/profiles/{name}' => self::PROFILE_IN_PATH,
        'DELETE /api/profiles/{name}' => self::PROFILE_IN_PATH,
        'GET /api/profiles/{name}/soul' => self::PROFILE_IN_PATH,
        'PUT /api/profiles/{name}/soul' => self::PROFILE_IN_PATH,
        'PUT /api/profiles/{name}/model' => self::PROFILE_IN_PATH,
        'GET /api/pairing' => self::PROFILE_QUERY,
        'POST /api/pairing/approve' => self::PROFILE_BODY,
        'POST /api/pairing/revoke' => self::PROFILE_BODY,
        'POST /api/pairing/clear-pending' => self::PROFILE_QUERY,
        'POST /api/messaging/whatsapp/onboarding/start' => self::PROFILE_BODY,
        'GET /api/messaging/whatsapp/onboarding/{pairing_id}' => self::PROFILE_NONE,
        'POST /api/messaging/whatsapp/onboarding/{pairing_id}/apply' => self::PROFILE_QUERY,
        'DELETE /api/messaging/whatsapp/onboarding/{pairing_id}' => self::PROFILE_NONE,
        'GET /api/messaging/platforms' => self::PROFILE_QUERY,
        'GET /api/status' => self::PROFILE_QUERY,
        'GET /api/health' => self::PROFILE_NONE,
        'GET /api/system/stats' => self::PROFILE_NONE,
    ];

    /**
     * Keluarga path yang **dilarang permanen**, apa pun isi daftar-putih.
     *
     * Ini lapis kedua, bukan pengulangan: daftar-putih melindungi dari path yang
     * tidak dikenal, daftar ini melindungi dari baris baru yang keliru ditambahkan
     * ke daftar-putih oleh manusia yang sedang tergesa.
     *
     * @var list<string>
     */
    public const FORBIDDEN = [
        '/api/fs/',
        '/api/files/',
        '/api/tools/terminal/',
        '/api/git/',
        '/api/env/reveal',
        '/api/ops/',
        '/api/dashboard/plugins',
        '/open-terminal',
    ];

    /**
     * Mencari entri daftar-putih untuk satu pasangan method + template path.
     *
     * Yang dicocokkan adalah **template** (`/api/profiles/{name}/soul`), bukan path
     * yang sudah terisi. Kalau yang dicocokkan path terisi, penyerang cukup
     * menamai profilnya `..%2Ffs%2Fwrite-text` untuk kabur dari daftar.
     */
    public static function profileScopeFor(string $method, string $template): ?string
    {
        return self::ALLOWED[strtoupper($method).' '.$template] ?? null;
    }

    public static function isForbidden(string $path): bool
    {
        foreach (self::FORBIDDEN as $needle) {
            if (str_contains($path, $needle)) {
                return true;
            }
        }

        return false;
    }
}
