# H-05 — Plugin auth server-ke-server untuk dashboard API Hermes

Ditulis di berkas terpisah karena pekerjaan ini terjadi di dua repo: kode
plugin ada di `hermes-agent` (di luar workspace ini), dan sisi konfigurasi
(`config/hermes.php`, `.env`, node uji) ada di `agentic-bos`. D-75 mencatat
keputusan; berkas ini mencatat pelaksanaan dan verifikasinya.

## Yang dibangun

Satu plugin `dashboard_auth`, ditulis meniru bentuk `plugins/dashboard_auth/drain`
persis — gerbang entropi, `hmac.compare_digest`, fail-closed — karena Hermes
sudah punya kerangka lengkap (`hermes_cli/dashboard_auth/`) dan D-72/D-75
melarang menambal core, hanya memakai titik-ekstensi resmi.

| Berkas (`%LOCALAPPDATA%\hermes\hermes-agent\`) | Isi |
|---|---|
| `plugins/dashboard_auth/agenticbos/__init__.py` | `AgenticBosSecretProvider` + `assess_secret_strength()` + `register()` |
| `plugins/dashboard_auth/agenticbos/plugin.yaml` | manifest plugin |
| `plugins/dashboard_auth/agenticbos/README.md` | dokumentasi operator |
| `tests/plugins/dashboard_auth/test_agenticbos_provider.py` | 22 test |

Gerbang entropi sama dengan `drain`: `>=43` karakter url-safe-base64, `>=16`
karakter distinct, `>=128` bit Shannon. Rahasia dibaca dari
`HERMES_DASHBOARD_AGENTICBOS_SECRET`; kosong atau gagal gerbang → provider
tidak terdaftar sama sekali (fail-closed, bukan fallback ke tanpa-auth).

Provider mendaftarkan diri lewat `ctx.register_dashboard_auth_provider()`, lalu
memanggil `register_token_route()` untuk setiap path **parameter-free** di
daftar-putih `ControlPlanePaths::ALLOWED` (D-72):

```
/api/profiles
/api/pairing
/api/pairing/approve
/api/pairing/revoke
/api/pairing/clear-pending
/api/messaging/whatsapp/onboarding/start
/api/messaging/platforms
/api/system/stats
```

## Insiden ditemukan dan diperbaiki di jalan

Registrasi awal plugin (sebelum ditinjau ulang) menyertakan **dua path
tambahan**: `/api/health` dan `/api/status`. Setelah restart dashboard,
`/api/health` menjawab **401** untuk pemanggil tanpa token — regresi nyata,
bukan hipotetis, karena Agentic BOS sendiri (`HermesControlPlaneClient::health()`)
memanggil endpoint ini tanpa header `Authorization` (T-82 sudah mencatat
`/api/health` dan `/api/status` tidak butuh autentikasi).

**Akar sebab**, dibaca dari kode bukan diduga: `_token_auth_seam` di
`web_server.py` adalah middleware **terluar**, dan ia berjalan **sebelum**
pengecekan `PUBLIC_API_PATHS`. Begitu sebuah path terdaftar lewat
`register_token_route()`, seam memaksa jalur itu **hanya** bisa lolos lewat
token — pengecekan "apakah path ini publik" tidak pernah tercapai untuk path
yang sudah terdaftar sebagai token route. Jadi mendaftarkan path yang
sebelumnya publik membuatnya **token-only**, bukan **token-atau-publik**.

Dibuktikan dari `dashboard-auth.log` (`%LOCALAPPDATA%\hermes\logs\dashboard-auth.log`,
JSON per baris):

```json
{"event":"token_auth_failure","reason":"no_provider_recognises_token","path":"/api/health"}
```

**Perbaikan:** `/api/health` dan `/api/status` dikeluarkan dari daftar
registrasi plugin (bukan dari `PUBLIC_API_PATHS` Hermes — itu bukan milik kita
untuk diubah). Ditambahkan komentar eksplisit di kode plugin yang menjelaskan
kenapa keduanya tidak boleh pernah masuk daftar itu, plus test regresi
(`test_negative_already_public_paths_are_never_registered_as_token_routes`)
yang mematok kedua path itu tidak pernah dipanggil ke `register_token_route()`.

## Verifikasi empiris terhadap dashboard hidup

Dashboard berjalan di `http://127.0.0.1:9119` (profil `nalarin-anti-bully`,
venv **produksi** — bukan `.venv` dev — karena proses ini juga melayani
gateway WhatsApp yang sedang hidup).

| Panggilan | Tanpa token | Dengan token |
|---|---|---|
| `GET /api/health` | **200** | — |
| `GET /api/status` | **200** | — |
| `GET /api/profiles` | **401** | **200** |

Setelah perbaikan, kedua path publik tetap publik dan path yang dilindungi
tetap menuntut token — pemetaan yang dituntut D-72 butir (3) terbukti
terhadap instalasi sungguhan, bukan `Http::fake()`.

**Verifikasi end-to-end dari sisi Laravel** (node uji sementara, dihapus dari
DB setelah verifikasi karena DB dev dipakai bersama sesi lain):

```
php artisan bos:hermes-control-ping --node=1
→ OK Host Lokal — version 0.19.1

app(App\Services\Hermes\ProfileMirror::class)->forNode($node, fresh: true)
→ ok=true, sebab=ok, yatim_count=36
```

`ProfileMirror` membaca 36 profil nyata dari `GET /api/profiles` lewat
bearer — jalur yang sebelum H-05 selalu menjawab `belum_berwenang`
(T-82/T-83 mencatat ini sebagai satu-satunya yang menghalangi).

## Konfigurasi yang terpasang

- Hermes: `.env` di `%LOCALAPPDATA%\hermes\.env` (bukan di `hermes-agent/`,
  ditemukan lewat `get_env_path()` — lokasi ini tidak jelas dari nama folder)
  mendapat baris `HERMES_DASHBOARD_AGENTICBOS_SECRET=<43+ char, token_urlsafe(32)>`.
- Laravel: `config/hermes.php` → `control_secrets['agenticbos'] = env('HERMES_CONTROL_AGENTICBOS_SECRET')`,
  `.env` mendapat baris yang sama nilainya dengan sisi Hermes (commit `6a5524b`).
- Nilai rahasia **tidak pernah** masuk basis data — `hermes_nodes.control_secret_reference`
  menyimpan nama referensi (`agenticbos`), sesuai pola `api_secret_reference`
  yang sudah ada (D-72 butir 4).

## Gate

- Sisi Hermes (`hermes-agent`, venv dev `.venv`): 121 test passed (99 sebelumnya
  + 22 plugin `agenticbos`, termasuk test regresi publik-path).
- Sisi Laravel: tidak ada test baru ditambahkan untuk H-05 sendiri — pekerjaan
  ini murni konfigurasi + verifikasi terhadap infrastruktur yang sudah punya
  test kontraknya (`ControlPlaneClientTest`, T-82). Suite penuh tidak
  dijalankan ulang khusus untuk perubahan ini karena tidak ada baris kode
  Laravel yang berubah, hanya `config/hermes.php` (nilai env) dan `.env`.

## Status setelah H-05

Seluruh lajur control plane yang sebelumnya menganggur menunggu ini
(T-82/T-83/T-84 sisi baca) sekarang bisa diverifikasi terhadap node hidup.
Aksi **tulis** (buat profil, tulis SOUL, approve pairing, mulai onboarding)
belum pernah dicoba terhadap node sungguhan lewat jalur ini — hanya `GET`
yang diverifikasi di atas.

## Yang belum tertutup — H-05b

`register_token_route()` mencocokkan `request.url.path` **exact-match saja**
(`hermes_cli/dashboard_auth/token_auth.py`); tidak ada dukungan
prefix/template. Tujuh entri di `ControlPlanePaths::ALLOWED` punya parameter
dan karena itu tidak bisa didaftarkan sebagai token route hari ini:

```
PATCH/DELETE /api/profiles/{name}
GET/PUT      /api/profiles/{name}/soul
PUT          /api/profiles/{name}/model
GET/DELETE   /api/messaging/whatsapp/onboarding/{pairing_id}
POST         /api/messaging/whatsapp/onboarding/{pairing_id}/apply
```

Dari tujuh itu, hanya `GET /api/profiles/{name}/soul` yang dipanggil kode
Agentic BOS saat ini (`HermesControlPlaneClient::profileSoul()`, T-83, dipakai
membaca SOUL profil saat dibuka di panel admin). Path lain belum punya
pemanggil.

**Sengaja tidak ditutup di sini** karena menutupnya berarti salah satu dari:
(a) menambal `token_auth.py`/`is_token_route` di core Hermes untuk mendukung
path-template — dilarang D-72/D-75 (menciptakan otak kedua, utang pemeliharaan
setiap Hermes diperbarui); atau (b) menghilangkan kebutuhan Agentic BOS untuk
membaca SOUL lewat jalur token (mis. operator menyalakan sesi cookie manual
untuk aksi itu saja). Keduanya keputusan produk/arsitektur, bukan detail
implementasi — dibiarkan terbuka sebagai utang bernama, bukan ditambal diam-diam.

## Batas jujur

Pekerjaan plugin (kode Python) terjadi di repo `hermes-agent`, di luar
workspace Agentic BOS, dan di luar kendali versi proyek ini kecuali dicatat di
sini. Commit plugin: `55c605314` (repo `hermes-agent`, branch `main`). Commit
sisi Laravel: `6a5524b` (`config/hermes.php` + `.env` reference name, tanpa
nilai rahasia).
