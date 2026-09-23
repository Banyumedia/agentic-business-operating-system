# Kontrak Integrasi Hermes (T-66 + T-67)

Status: **FAKTA dari pembacaan kode**, bukan asumsi lagi. Versi sebelumnya
dokumen ini memuat bentuk permintaan yang **dikarang** tanpa node sungguhan;
seluruhnya diganti.

Sumber yang dibaca (instalasi Hermes agent di PC pengembangan,
`%LOCALAPPDATA%\hermes\hermes-agent`):

| Berkas | Yang dipastikan |
|---|---|
| `gateway/platforms/api_server.py` | daftar endpoint, auth, port, multiplexing profil |
| `gateway/platforms/whatsapp_cloud.py` | env var, konstanta Graph API, webhook, HMAC |
| `plugins/platforms/whatsapp/adapter.py` | Baileys jalan sebagai subprocess Node, bukan servis HTTP |
| `hermes_cli/pairing.py` | perintah pairing pengguna + `PairingStore` |
| `config.yaml`, `gateway_state.json` | platform yang ada dan statusnya |
| `profiles/<nama>/` | isolasi per profil |

---

## 1. Temuan yang membatalkan asumsi

### 1.1 `api_server` bukan API pengiriman pesan

`api_server` adalah **server API yang kompatibel OpenAI** untuk *mengobrol dengan
agent*, bukan untuk mengirim WhatsApp. Daftar endpoint dari docstring-nya:

```
POST   /v1/chat/completions          OpenAI Chat Completions
POST   /v1/responses                 OpenAI Responses API (stateful)
GET    /v1/responses/{id}            ambil response tersimpan
DELETE /v1/responses/{id}
GET    /v1/models                    daftar model + alias model_routes
GET    /v1/capabilities              kemampuan API, machine-readable
GET    /api/sessions                 daftar sesi Hermes
POST   /api/sessions                 buat sesi kosong
GET|PATCH|DELETE /api/sessions/{id}
GET    /api/sessions/{id}/messages
POST   /api/sessions/{id}/fork
POST   /api/sessions/{id}/chat[/stream]
POST   /v1/runs                      mulai run, balas 202 + run_id
GET    /v1/runs/{id}                 status run
GET    /v1/runs/{id}/events          SSE lifecycle
POST   /v1/runs/{id}/approval        jawab approval yang tertunda
POST   /v1/runs/{id}/stop            interupsi agent
GET    /health                       health check
GET    /health/detailed              status kaya untuk probe lintas container
```

- **Auth:** header/bearer dengan `API_SERVER_KEY`
- **Port bawaan:** `8642`
- **Diaktifkan:** `platforms.api_server.enabled` di `config.yaml` (sekarang `false`)
- **Multi-profil:** bila `gateway.multiplex_profiles` aktif, profil sekunder
  dicapai lewat prefiks URL — `GET /p/<profil>/v1/models`,
  `POST /p/<profil>/v1/chat/completions`, dan seterusnya. Profil yang tidak
  dilayani gateway ini menghasilkan **404**.

**Tidak ada** endpoint kirim WhatsApp, tidak ada endpoint pairing, tidak ada QR.

**Konsekuensi:** bentuk yang dipakai `App\Services\HermesNodeClient` sekarang
(`POST {api_url}/api/wa/send` dengan `{instance_id, to, message}` dan header
`X-Hermes-Secret`) **tidak cocok dengan apa pun yang ada di Hermes hari ini**.
Kelas itu tetap benar secara perilaku — fail-closed di setiap simpul — tetapi
target panggilannya belum ada.

Prefiks `/p/<profil>/` adalah kabar baik yang tidak terduga: itulah cara
mengalamatkan profil per tenant lewat **satu** listener, persis yang dibutuhkan
model "satu tenant = satu profil".

### 1.2 `whatsapp_cloud` tidak bisa diarahkan ke kirimdev tanpa menambal Hermes

Env var yang didukung adaptor resmi:

```
WAJIB     WHATSAPP_CLOUD_PHONE_NUMBER_ID
          WHATSAPP_CLOUD_ACCESS_TOKEN
OPSIONAL  WHATSAPP_CLOUD_APP_ID
          WHATSAPP_CLOUD_APP_SECRET      HMAC untuk X-Hub-Signature-256
          WHATSAPP_CLOUD_WABA_ID
          WHATSAPP_CLOUD_VERIFY_TOKEN    hub.verify_token
          WHATSAPP_CLOUD_WEBHOOK_HOST    default: dual-stack semua interface
          WHATSAPP_CLOUD_WEBHOOK_PORT    default 8090
          WHATSAPP_CLOUD_WEBHOOK_PATH    default /whatsapp/webhook
          WHATSAPP_CLOUD_API_VERSION     default v20.0
```

Dan di dalam kode:

```python
GRAPH_API_BASE = "https://graph.facebook.com"
```

**Konstanta modul, tidak ada override lewat env.** Jadi rencana "arahkan
`whatsapp_cloud` ke base URL kirimdev" **tidak bisa dijalankan apa adanya**.
Pilihannya:

| Opsi | Biaya | Akibat |
|---|---|---|
| **A. Tambal Hermes** — jadikan `GRAPH_API_BASE` bisa di-override (mis. `WHATSAPP_CLOUD_GRAPH_BASE`) | satu baris, tapi **patch yang harus dipelihara** setiap Hermes diperbarui | jalur resmi memakai **satu otak** yang sama; SOUL white-label dan skill cukup sekali |
| **B. Panggil kirimdev langsung dari Laravel** | nol perubahan Hermes | jalur resmi punya **otak sendiri**; white-label dan skill jadi dua kali; bertentangan dengan alasan awal memilih satu mesin |
| **C. Usulkan ke upstream Hermes** | paling bersih jangka panjang | menunggu, tidak bisa dijadwalkan |

Rekomendasi: **A**, dengan patch dicatat sebagai utang pemeliharaan yang
eksplisit, dan C diajukan paralel supaya utang itu berakhir.

Hal lain yang berguna dari adaptor ini dan tidak perlu kita bangun ulang:
verifikasi `X-Hub-Signature-256` atas raw body dengan perbandingan constant-time,
proteksi replay lewat cache `wamid` (5000 entri, FIFO), jendela percakapan
24 jam + fallback template, unggah/unduh media dengan batas ukuran per tipe
sesuai Meta, dan konversi voice-note opus.

### 1.3 Baileys adalah subprocess Node **yang menyajikan HTTP** (koreksi)

Versi sebelumnya bagian ini berbunyi "bukan servis HTTP ... tidak ada endpoint
HTTP untuk memulai sesi, mengambil QR, atau membaca status". **Itu salah**, dan
kesalahannya sudah terlihat dari kode kita sendiri: T-69 mengarahkan
`App\Services\HermesNodeClient` ke `POST /send` + `GET /health`, dan T-81
menurunkan status profil dari `/health` — keduanya mustahil kalau bridge bukan
servis HTTP.

Yang sebenarnya, dibaca dari `scripts/whatsapp-bridge/bridge.js` (44 KB, Express):

```
node bridge.js --port 3000 --session <session dir>     (baris 19)
const PORT = parseInt(getArg('port', '3000'), 10);     (baris 82)
app.listen(PORT, '127.0.0.1', ...)                     (baris 1179)

GET  /messages     POST /send          POST /edit        POST /send-media
POST /send-poll    POST /send-location POST /typing      POST /read
GET  /chat/:id     GET  /health
```

Jadi: **ada** servis HTTP, **hanya loopback** (`127.0.0.1`), **tanpa
autentikasi** — itulah sebabnya `config/hermes.php` punya
`no_auth_reference` dan `HermesNodeClient` hanya mengizinkannya untuk loopback.
QR bukan cuma dicetak ke terminal: `emitPairEvent({ event: 'qr', qr })`
(baris 450) mengalirkannya ke gateway Python. Mode `--pair-only` memang tidak
menyalakan server HTTP (baris 1163), dan kemungkinan itulah asal salah baca yang
lama.

**Konsekuensi:** T-72 (layar pairing QR di web kita) **tidak** memerlukan
endpoint baru di Hermes. Lihat §1.4.

### 1.4 Dashboard Hermes adalah control plane HTTP yang sudah lengkap

`hermes_cli/web_server.py` (FastAPI, ~695 KB) + `hermes_cli/web_routers/*`
menyajikan API yang dipakai SPA dashboard Hermes sendiri. SPA itu **hanya salah
satu klien**; API-nya sama-sama terbuka untuk klien lain. Yang relevan untuk
kita, semuanya **profile-scoped** lewat parameter/`body.profile` +
`_config_profile_scope`:

| Keperluan | Endpoint |
|---|---|
| Provisioning profil | `GET/POST /api/profiles`, `PATCH/DELETE /api/profiles/{name}`, `GET/POST /api/profiles/active` |
| SOUL white-label, model, deskripsi | `GET/PUT /api/profiles/{name}/soul`, `PUT .../model`, `PUT .../description` |
| Device pairing + QR | `POST /api/messaging/whatsapp/onboarding/start` → `{pairing_id, status, qr_payload, expires_at, account_phone}`; `GET .../{pairing_id}`; `POST .../{pairing_id}/apply`; `DELETE .../{pairing_id}` |
| Pairing pengguna | `GET /api/pairing`, `POST /api/pairing/approve` (`request_id` **atau** `code`; lockout → 429), `/revoke`, `/clear-pending` |
| Konfigurasi & env | `GET/PUT /api/config`, `/api/config/raw`, `/api/config/schema`, `GET/PUT/DELETE /api/env`, `POST /api/env/reveal` |
| Kendali gateway | `POST /api/gateway/start|stop|restart|drain` |
| Pemantauan | `GET /api/status`, `/api/health`, `/api/system/stats`, `/api/logs`, `/api/messaging/platforms` (+ `POST .../{id}/test`), `/api/sessions`, `/api/analytics/usage`, `/api/analytics/models` |
| Skill & cron per profil | `/api/skills`, `/api/skills/toggle`, `/api/cron/jobs...` |
| MCP server per profil | `GET/POST/PUT /api/mcp/servers`, `/{name}/enabled`, `/{name}/test` |

`apply` pada onboarding WhatsApp bukan sekadar penanda: ia menulis
`WHATSAPP_MODE`, `WHATSAPP_DM_POLICY=pairing`, `WHATSAPP_ALLOWED_USERS`,
`WHATSAPP_ENABLED=true`, menyalakan platform, lalu **me-restart gateway**.

**Dua hal yang harus dibaca bersamaan dengan tabel di atas:**

1. **Autentikasinya belum untuk mesin.** `_require_token` hanya menerima
   `_SESSION_TOKEN` ephemeral yang disuntikkan ke HTML SPA (bind loopback), atau
   menyerah pada gate cookie OAuth/password (bind non-loopback). Seam bearer
   generik memang ada (`hermes_cli/dashboard_auth/token_auth.py`) — tetapi
   satu-satunya rute yang terdaftar adalah `/api/gateway/drain`, lewat
   `plugins/dashboard_auth/drain/__init__.py:280`. Jadi panggilan
   server-ke-server **belum mungkin tanpa satu plugin `dashboard_auth` baru**
   (provider + `register_token_route` per path). Plugin drain itu contoh kerja
   yang lengkap, termasuk gerbang entropi rahasia.
2. **Port yang sama adalah permukaan eksekusi kode.** Di situ juga hidup
   `/api/fs/write-text`, `/api/files/upload`, `/api/tools/terminal/*`,
   `/api/git/*`, dan `/api/profiles/{name}/open-terminal`. Token ke dashboard
   sama dengan RCE di host Hermes. Karena itu pemakaian dari pihak kita wajib
   **daftar-putih per path** (seam-nya memang exact-match, cocok), scope
   terpisah dari drain, dan bind loopback di balik proxy — bukan token umum yang
   dipegang kode layar.

### 1.5 Tiga permukaan HTTP, jangan dicampur

| Permukaan | Isi | Auth | Catatan |
|---|---|---|---|
| **Bridge Baileys** (`scripts/whatsapp-bridge/bridge.js`, `127.0.0.1:<port>`) | kirim pesan/media/poll/lokasi, `/health`, `/messages` | **tidak ada** | satu bridge = satu nomor = satu port → alamat milik profil (§5). Jalur pengiriman tenant hari ini. |
| **Dashboard** (`hermes_cli/web_server.py`) | profil, SOUL, pairing, QR, config/env, gateway, monitoring, MCP, **juga fs/terminal/git** | token SPA ephemeral / cookie gate | control plane. §1.4. |
| **api_server** (`gateway/platforms/api_server.py`, `:8642`) | `/v1/chat/completions`, `/v1/runs`, `/health`, prefiks `/p/<profil>/` | `API_SERVER_KEY` | hanya *mengobrol* dengan agent. Tidak ada primitif messaging. `platforms.api_server.enabled` masih `false`. |

### 1.6 MCP di Hermes: dua arah, keduanya bukan konfigurasi

- **Hermes sebagai MCP server** — `mcp_serve.py` (`hermes mcp serve`) memuat
  tepat 10 tool dan semuanya *messaging*: `conversations_list`,
  `conversation_get`, `messages_read`, `attachments_fetch`, `events_poll`,
  `events_wait`, `messages_send`, `channels_list`, `permissions_list_open`,
  `permissions_respond`. **Nol tool untuk profil, config, atau pairing.**
  Transportnya **stdio saja** (`server.run_stdio_async()`, baris 1029), jadi
  memakainya dari Laravel berarti men-spawn proses di host Hermes dengan akses ke
  home Hermes — objeksi yang sama dengan menjalankan CLI (§8). `messages_send`
  juga tidak ter-scope profil: ia mengikuti `HERMES_HOME`/profil aktif proses itu.
- **Hermes sebagai MCP client** — mendukung server **remote**:
  `hermes mcp add <name> --url <endpoint>`, atau `POST /api/mcp/servers` yang
  profile-scoped, dengan bearer token (`_save_bearer_auth_token`), filter
  `tools.include`/`exclude`, dan toggle `enabled`. Ini jalur yang sesuai arah
  D-68/D-70: **kita** tool provider, Hermes yang memanggil.

Kesimpulan praktis: "bikin MCP ke Hermes untuk setting-setting" tidak bisa
dipenuhi MCP — konfigurasi hidup di dashboard API (§1.4). MCP berguna untuk arah
sebaliknya (menaikkan T-17b dari REST bernama-`mcp_*` menjadi MCP server nyata).

---

## 2. Yang sudah ada vs yang harus dibangun

| Kebutuhan kita | Ada hari ini? | Bentuknya |
|---|---|---|
| Mengobrol dengan agent lewat HTTP | **Ada** | `api_server` `/v1/chat/completions`, `/v1/runs` |
| Mengalamatkan profil per tenant | **Ada** | prefiks `/p/<profil>/` + `gateway.multiplex_profiles` |
| Health check node | **Ada** | `GET /health`, `/health/detailed` |
| Auth ke node | **Ada** | `API_SERVER_KEY` |
| Approval run dari luar | **Ada** | `POST /v1/runs/{id}/approval` |
| **Kirim WhatsApp dari luar** | **Ada** (koreksi §1.3) | `POST /send` di bridge Baileys, loopback, tanpa auth. Dipakai T-69. |
| **Mulai sesi WA + ambil QR + status** | **Ada** (koreksi §1.4) | `POST /api/messaging/whatsapp/onboarding/start` → `qr_payload`; status via `GET .../{pairing_id}` dan `/health` bridge |
| **Pairing pengguna dari luar** | **Ada** (koreksi §1.4) | `GET /api/pairing`, `POST /api/pairing/approve|revoke|clear-pending` — HTTP, bukan hanya CLI |
| **Auth server-ke-server ke dashboard** | **TIDAK ADA** | satu-satunya token route: `/api/gateway/drain`. Perlu plugin `dashboard_auth` baru. |
| Tool MCP untuk konfigurasi | **TIDAK ADA** | `hermes mcp serve` hanya messaging (§1.6) |
| Kirim lewat Cloud API resmi | **Ada, tapi terpaku Meta** | `GRAPH_API_BASE` hardcoded |

## 3. Pairing pengguna (lapisan 2) — sudah lengkap di Hermes

CLI: `hermes pairing list | approve <platform> <request-id|code> | revoke <platform> <user_id> | clear-pending`.
Penyimpanan per profil: `platforms/pairing/<platform>-pending.json`,
`<platform>-approved.json`, `_rate_limits.json` (memuat kunci `_lockout:<platform>`).
Alur: orang asing japri → bot kirim kode → masuk `pending` → operator menyetujui
lewat request-id **atau** kode yang direlai pengguna → masuk `approved` →
dikenali otomatis pada pesan berikutnya. Ada rate limit dan lockout per platform.

Isi `whatsapp-approved.json` saat ini: satu nomor, `6282136888005`, label `bot`.

Ini **bentuk yang sama** dengan T-51 kita (kode sekali pakai, kedaluwarsa, bisa
dicabut). D-70 memutuskan Hermes yang memegang; T-77 menjadikan T-51 antarmuka
di atasnya. Permukaan HTTP-nya **ada** — `/api/pairing*` di §1.4, membungkus
`PairingStore` yang sama. Yang menahan T-77 tinggal autentikasi mesin (§1.4
butir 1), bukan ketiadaan endpoint.

## 4. Status platform pada instalasi ini

Dari `gateway_state.json` (stempel 2026-08-20, jadi bisa basi):

```
telegram        connected
whatsapp        fatal   whatsapp_not_paired  "pair from the dashboard or run `hermes whatsapp`"
whatsapp_cloud  fatal   whatsapp_cloud_unconfigured
webhook         connected
api_server      disconnected   (platforms.api_server.enabled = false)
```

Sesi Baileys **ada** di `platforms/whatsapp/session/` (`creds.json`, ratusan
`pre-key-*.json`, `device-list-6282136888005.json`), sehingga status
`not_paired` itu entah basi atau sesinya kedaluwarsa. T-61 akan memastikannya.

## 5. Isolasi profil (fondasi multi-tenant)

`profiles/<nama>/` adalah rumah lengkap: `SOUL.md`, `config.yaml`, `platforms/`,
`pairing/`, `sessions/`, `memories/`, `gateway_state.json`, `gateway.pid`,
`state.db` sendiri-sendiri. Jadi **satu tenant = satu profil = satu soul + satu
sesi WhatsApp + satu daftar pengguna disetujui + satu gateway**, sesuai
`COMMERCIAL_AND_AI_AGENTIC_SPEC.md` dan `hermes_nodes.max_capacity`.

Karena setiap profil membawa bridge-nya sendiri di **port sendiri**, alamat itu
disimpan per profil: `hermes_profiles.api_url` (T-81), nullable dan jatuh kembali
ke `hermes_nodes.api_url`. Pembagiannya: **node = host atau klaster**, **profil =
satu nomor pada satu port**. Menaruh alamat hanya di node memaksa satu baris node
per nomor, yang membuat `max_capacity` — "berapa profil yang muat pada satu host" —
kehilangan arti.

Status profil juga tidak diketik tangan: `bos:hermes-profile-status` membacanya
dari `GET /health` bridge masing-masing dan menulis `paired`/`unpaired`. Ingat
bahwa endpoint itu menjawab **200 walau WhatsApp terputus**, jadi keputusannya
diambil dari field `status`, bukan dari kode HTTP.

## 6. Pekerjaan sisi Hermes yang sekarang menjadi prasyarat

Daftar ini **menyusut dari empat menjadi dua** setelah §1.3/§1.4 dibaca
sungguhan. Yang dulu tercatat sebagai "endpoint kirim pesan" (butir 1),
"endpoint sesi WhatsApp + QR" (butir 2), dan "endpoint pairing pengguna"
(butir 3) **semuanya sudah ada**; T-69 dan T-81 bahkan sudah memakai yang
pertama. Yang tersisa:

1. **Auth server-ke-server untuk dashboard API** (memblokir T-72, T-77, dan
   setiap layar pantau profil). Bentuk terkecilnya: satu plugin
   `dashboard_auth` bergaya `plugins/dashboard_auth/drain` yang mendaftarkan
   provider bearer + `register_token_route()` **hanya** untuk path yang kita
   pakai. Tanpa itu satu-satunya cara masuk adalah token SPA ephemeral atau
   login cookie — keduanya bukan jalur mesin.
2. **Override base URL Cloud API** (memblokir T-71 jalur resmi): `GRAPH_API_BASE`
   dijadikan konfigurasi.

Keduanya kecil secara kode tetapi ada di repo lain, jadi tetap perlu keputusan
Bos: ditambal/di-plugin lokal (utang pemeliharaan) atau diusulkan ke upstream
(menunggu). Catatan penting untuk butir 1: menambah plugin **tidak** boleh
berarti membuka seluruh dashboard API — daftar-putih per path dan alasannya ada
di §1.4 butir 2.

## 7. Armada: tiga hal berbeda yang mudah tertukar

Pertanyaan "satu Hermes mengelola banyak Hermes, dan pusatnya kita kendalikan"
menyentuh tiga mekanisme yang berbeda. Membedakannya penting karena hanya dua
yang menjadi milik kita.

### 7.1 Satu Hermes, banyak **profil** (sudah ada, terkendali penuh)

`gateway.multiplex_profiles` + prefiks `/p/<profil>/` membuat satu listener
melayani banyak profil, dan `profiles/<nama>/` adalah rumah terisolasi (§5).
Ini **bukan** "banyak Hermes": satu proses, satu host. Batas yang harus disadari:
satu bridge WhatsApp = satu nomor = **satu port** (T-81), setiap profil punya
`state.db` sendiri, dan satu proses gateway jatuh berarti seluruh tenant di host
itu jatuh bersamaan. `hermes_nodes.max_capacity` bawaan kita **100** adalah angka
yang belum pernah diuji terhadap kenyataan ini.

**Hermes mengenal empat mode gateway, dan ia melaporkannya sendiri.**
`/api/status` mengembalikan `gateway_mode` berisi salah satu dari:

| Mode | Arti | Konsekuensi multi-tenant |
|---|---|---|
| `multiplex` | satu gateway default melayani beberapa profil (`profiles_to_serve(True)`) | termurah, tetapi tenant **berbagi satu proses**: satu GIL, satu jatah memori, satu restart |
| `multiple` | gateway independen **per profil** | isolasi per tenant; biayanya satu proses Python per tenant |
| `single` | satu gateway hidup | satu profil aktif |
| `none` | tidak ada yang jalan | — |

Bersama itu, tiap gateway yang hidup dilaporkan sebagai
`{"profile", "ports", "served_profiles"?}` — jadi **port yang dipakai tiap profil
bisa dibaca dari luar**, tidak perlu ditebak atau dicatat tangan.

**Batas kerja yang nyata, dari `hermes_cli/config_defaults.py`.** Tidak ada satu
angka "maksimal tenant"; yang ada empat batas berbeda, dan ketiga yang pertama
berlaku **per proses gateway** — artinya di mode `multiplex` batas itu **dibagi
bersama oleh semua tenant pada proses itu**:

- **`max_live_sessions: 16`** — batas LRU lunak atas sesi in-memory. Melebihi itu,
  gateway **menggusur** sesi ter-lama yang **detached** (tanpa klien hidup); sesi
  itu dipulihkan dari disk saat dibuka lagi, jadi ini **bukan** kehilangan data,
  tetapi ia **adalah** langit-langit "berapa banyak yang benar-benar bekerja
  serentak". `0`/`null` mematikannya.
- **`max_concurrent_sessions: None`** — batas global sesi chat aktif lintas CLI,
  TUI/dashboard, dan messaging. Bawaannya **tanpa batas**, jadi ia knop yang
  tersedia, bukan perlindungan yang sudah menyala.
- **`gateway.api_server.max_concurrent_runs`** — batas run agent yang dibagi
  seluruh endpoint pelayan agent; melebihinya dijawab respons "concurrency
  limited". `0` mematikan batas.
- **`agent.restart_drain_timeout: 0`** dengan kontrak yang dinyatakan eksplisit:
  "if you restart the gateway, in-flight work stops". Plus
  `agent.gateway_timeout: 1800` (idle) dan `agent.max_turns: 500`.

Gabungan dua fakta terakhir adalah alasan paling kuat untuk **tidak** menumpuk
banyak tenant pada satu proses: di mode `multiplex`, restart gateway — yang antara
lain **dipicu sendiri** oleh `POST /api/messaging/whatsapp/onboarding/{id}/apply`
— menghentikan pekerjaan yang sedang jalan. Apakah restart itu benar-benar
menjatuhkan seluruh `served_profiles` atau hanya profil yang diminta **belum
diverifikasi** (`_spawn_gateway_restart(profile)` menerima nama profil, tetapi di
mode multiplex profil itu dilayani proses default). Ini harus diuji sebelum dua
tenant berbagi satu proses — lihat T-89.

**Rekomendasi yang mengikuti dari fakta di atas:** tenant berbayar dijalankan
dengan gateway **per profil** (`multiple`), bukan `multiplex`. Biayanya satu
proses per tenant, dan itulah yang membuat "banyak host" menjadi kebutuhan
infrastruktur, bukan kemewahan. `multiplex` tetap masuk akal untuk profil internal
dan demo. Keputusan mana yang dipakai per host adalah bagian **Q-14**.

### 7.2 Banyak host Hermes, pusatnya **Agentic BOS** (jalur D-72)

Inilah yang sudah masuk antrean: `hermes_nodes` + `HermesControlPlaneClient`
(T-82) memanggil dashboard API tiap host. Yang menjadi "pusat" adalah **produk
kita**, bukan sebuah Hermes. Pemetaan tenant, kuota, dan paket memang sudah hidup
di sisi kita (D-37, `max_capacity`, D-52), jadi menaruh kendali armada di situ
tidak memindahkan aturan bisnis ke mesin — persis batas yang D-69 tegakkan untuk
skill.

### 7.3 Hermes pusat sebagai orkestrator — **ada, tetapi pusatnya bukan kita**

Hermes memang punya orkestrator armada. Namanya **NAS**, dan ia milik Nous:

- `hermes_cli/gateway_enroll.py` — `hermes gateway enroll` mendaftarkan gateway
  self-hosted ke **relay connector**: token Nous Portal dari `~/.hermes/auth.json`
  membuktikan **org Nous mana** yang memiliki pemanggil, connector menurunkan
  tenant otoritatif lewat `GET /api/oauth/account` (**tidak pernah** dari apa yang
  diakui gateway), lalu mengembalikan `GATEWAY_RELAY_ID`/`_SECRET`/`_DELIVERY_KEY`.
  Dokumentasinya menyebut **EXPERIMENTAL: skema auth relay dapat berubah tanpa
  siklus deprecation.**
- Instalasi *managed/hosted* **tidak** self-enroll: "the orchestrator (NAS) mints
  the secret directly and stamps it into the container env", dan perintahnya
  menolak jalan di bawah `is_managed()`.
- `hermes_cli/dashboard_register.py` — klien OAuth dashboard didaftarkan ke
  `portal.nousresearch.com/api/oauth/self-hosted-client`, dimiliki org pemanggil.

Konsekuensinya untuk kita: memakai jalur ini berarti tenant kita terdaftar di
bawah **org Nous**, jalur kendali melewati connector mereka, onboarding menyentuh
merek Nous (bertabrakan dengan D-68), dan kontraknya sendiri dinyatakan
eksperimental. Jadi "Hermes pusat" **tidak** memberi kita pusat — ia memberi Nous
pusatnya. Ini bukan alasan teknis semata: armada adalah tempat kuota dan paket
ditegakkan, dan itu milik produk kita.

### 7.4 Dua mekanisme Hermes yang **benar-benar** berarti "kita kendalikan"

| Mekanisme | Isi | Batas yang harus jujur |
|---|---|---|
| **Managed scope** (`hermes_cli/managed_scope.py`) | Direktori config/env yang **menang atas** `~/.hermes/config.yaml` dan `.env` milik pengguna, per-leaf-key. Resolusinya: `$HERMES_MANAGED_DIR` (override deployment, **tidak** pernah ditulis ke .env mana pun) lalu `/etc/hermes`. | (a) "v1 enforcement is filesystem permissions only", **POSIX-first**; di Windows direktorinya hanya ditunjuk env var, jadi kendalinya **konvensi, bukan penjagaan**. (b) Bacaannya **fail-open** — **diuji, bukan dugaan** (T-88, §7.4a): berkas managed yang rusak dicatat keras (`IGNORING this managed file. Admin policy ... NOT being applied. Fix and restart.`) lalu `load_managed_config()` mengembalikan `{}`. Kebijakan kita berhenti berlaku tanpa satu galat pun ke pemanggil. Kalau dipakai sebagai penjaga, ia **wajib** dipantau dari sisi kita (`GET /api/status`/`/api/config`, T-84), bukan diandalkan pada keberadaan berkas. |
| **Profile distribution** (`hermes_cli/main.py`, cmd `profile install/update/info`) | Profil dipaket sebagai **repo git** + `distribution.yaml`; `hermes profile install <git-url>#<ref>`, `update`, `info`. | **Kontrak `update` dibaca dari keluaran CLI-nya sendiri** (T-88, §7.4b): file dist-owned **ditimpa** (`SOUL.md`, `skills/`, `cron/`, `mcp.json`); `config.yaml` **dipertahankan** kecuali `--force-config`; dan `memories, sessions, auth.json, .env` **tidak pernah disentuh**. Ini menjawab tiga pertanyaan T-85 vs D-69: template profil tenant **bisa** menjadi artefak repo, dan penyesuaian per tenant (`config.yaml`, kredensial, riwayat) aman selama `--force-config` tidak dipakai. Belum dijalankan penuh di sini — lihat catatan di bawah. |

#### 7.4a Bukti uji T-88 (dijalankan pada instalasi ini, bukan dugaan)

Uji terhadap sistem luar, jadi buktinya transkrip perintah, bukan test PHPUnit.
Dijalankan lewat interpreter Hermes sendiri (`venv\Scripts\python.exe`) di
`%LOCALAPPDATA%\hermes\hermes-agent`, dengan `$HERMES_MANAGED_DIR` menunjuk
direktori sementara yang dihapus setelah uji. **Managed scope resolve di Windows** —
mematahkan keraguan "POSIX-first mungkin tidak jalan di sini":

```
MANAGED_DIR=C:\Users\...\Temp\hermes-managed-t88
MANAGED_CFG={"gateway": {"log_level": "MANAGED_WINS"}}
```

**(a) Menang per-leaf, bukan mengganti seluruh cabang.** User config punya dua leaf
di bawah `gateway` + satu key tak terkait; managed hanya memaksa `log_level`:

```
input : {'gateway': {'log_level': 'USER_VALUE', 'other_key': 'USER_KEEP'}, 'unrelated': 'KEEP2'}
overlay: {"gateway": {"log_level": "MANAGED_WINS", "other_key": "USER_KEEP"}, "unrelated": "KEEP2"}
```

`log_level` menang; `other_key` dan `unrelated` utuh. Jadi managed scope bisa
memaku **satu** nilai tanpa mengambil alih sisa konfigurasi tenant — itu yang
membuatnya berguna sebagai penjaga selektif.

**(b) Fail-open terbukti, dan ini bahayanya.** `config.yaml` managed dirusak
(YAML tak lengkap), cache di-invalidate, lalu dibaca ulang:

```
managed scope: failed to parse ...config.yaml: ... IGNORING this managed file.
Admin policy from this file is NOT being applied. Fix and restart.
MALFORMED_CFG={}
```

Ia berteriak di log, tetapi **`load_managed_config()` tetap mengembalikan `{}`** dan
resolusi config lanjut tanpa kebijakan kita. Konsekuensinya untuk kita keras: satu
salah-ketik pada berkas managed **mematikan seluruh penjagaan** di node itu tanpa
menghentikan apa pun. Karena itu managed scope **tidak boleh** dianggap penjagaan
yang berdiri sendiri; ia harus dipasangkan dengan pemeriksaan "nilai yang kita paksa
masih aktif?" dari `GET /api/status` (T-84).

**Distribution: kontraknya dibaca, siklus install/update penuh sengaja tidak
dijalankan** terhadap agen Hermes yang hidup — ia akan menimpa berkas dist-owned
profil nyata, aksi yang sulit ditarik kembali pada instalasi produktif. Yang
dipastikan adalah **kontrak `update` dari keluaran CLI-nya sendiri** (lihat tabel
§7.4). Menjalankan siklus penuh layak dilakukan pada profil uji yang dibuang, bukan
pada `%LOCALAPPDATA%\hermes` yang sedang dipakai.

Kesimpulan yang dicatat supaya tidak ditemukan ulang: **hub = Agentic BOS**
(§7.2), **kendali konfigurasi = managed scope + distribution** (§7.4), dan
**relay/NAS tidak dipakai** (§7.3). Satu bentuk "Hermes pusat" yang tetap masuk
akal adalah **profil operator internal** untuk diagnosa armada — dikecualikan
white-label (D-68) dan tanpa tool tulis ke node tenant; itu memantau, bukan
mengendalikan.

## 8. Yang belum dibaca dan masih perlu dipastikan

Supaya tidak ada yang mengira dokumen ini lebih lengkap dari kenyataannya:

- `hermes_cli/send_cmd.py` — apakah `hermes send` bisa menjadi jalur sementara,
  dan dengan argumen apa. Catatan: menjalankan CLI dari Laravel berarti
  memberi aplikasi web akses ke home Hermes yang memuat kredensial plaintext,
  jadi ini bukan jalur produk.
- `gateway/delivery.py`, `delivery_ledger.py` — apakah ada primitif kirim yang
  layak dibungkus endpoint.
- Dokumentasi kirimdev: bentuk payload webhook, nama header HMAC, dan apakah
  benar-benar sepadan dengan `X-Hub-Signature-256` milik Meta.
- **Kestabilan kontrak dashboard API.** Rutenya tidak berversi dan sebagian
  besar masih di dalam `web_server.py` (sisanya baru diekstrak "verbatim" ke
  `web_routers/`). Pembaruan Hermes bisa memindahkan atau mengubahnya tanpa
  peringatan. Belum diperiksa: apakah ada janji kompatibilitas apa pun untuk
  `/api/*`. Sampai itu jelas, anggap ini utang pemeliharaan dan kurung
  pemakaiannya dalam satu kelas klien + test kontrak terhadap node hidup.
- Apakah `POST /api/messaging/platforms/{id}/test` benar-benar mengirim pesan
  uji atau hanya memeriksa konfigurasi — belum dibaca, jangan diandalkan
  sebagai jalur kirim.
- **Portabilitas antar mesin: jalurnya jelas, hasilnya belum dibuktikan.**
  `hermes backup` mem-zip **seluruh `~/.hermes/`** dan `hermes import` menimpanya di
  mesin baru; `hermes profile export/import` melayani satu profil. Yang dikecualikan
  memang wajar — `hermes-agent`, `node_modules`, venv plugin/MCP, `__pycache__`,
  `.cache`, `backups`, plus `checkpoints` yang kodenya sendiri sebut
  session-hash-keyed sehingga **tidak port** ke mesin lain. Yang **belum dibuktikan**:
  (a) apakah sesi Baileys di `platforms/whatsapp/session/` tetap hidup setelah pindah
  mesin, atau tenant terpaksa scan QR ulang; (b) apakah `auth.json` (OAuth penyedia
  per profil) masih sah di mesin berbeda. Keduanya wajib diuji dengan **profil
  internal** sebelum ada tenant di host kedua (T-106 butir g). Satu aturan yang sudah
  pasti dan tidak perlu diuji: **jangan pernah** dua instance hidup dengan creds
  WhatsApp yang sama — WhatsApp menganggapnya konflik dan dapat memutus sesi.
- **Data memory provider hidup di luar HERMES_HOME.** Hindsight mode
  `local_embedded` menaruh datanya di `~/.hindsight/` (jejaknya
  `~/.hindsight/profiles/<profil>.log`), jadi ingatan bertumbuh **tidak ikut**
  `hermes backup`. Bila Q-16 dijawab "ya", ini alasan tambahan memilih
  `local_external`: ingatannya hidup sebagai layanan tersendiri sehingga pindah
  server bukan peristiwa.
- ~~**Managed scope di Windows belum diuji.**~~ **DIUJI (T-88, §7.4a):** resolve di
  Windows, menang per-leaf, dan **fail-open** saat berkasnya rusak (mengembalikan
  `{}` sambil berteriak di log). Kesimpulan: "kita kendalikan" lewat managed scope
  hanya berarti penjagaan **bila** disertai pemantauan `GET /api/status` — berkas
  sendirian bukan jaminan.
- **Profile distribution: kontraknya dibaca, siklus penuh belum dijalankan.**
  Kontrak `update` sudah dipastikan dari keluaran CLI Hermes (T-88, §7.4:
  dist-owned ditimpa; `config.yaml` dipertahankan tanpa `--force-config`;
  memori/sesi/auth/.env tidak disentuh). Yang **belum** dilakukan adalah siklus
  `install` → ubah repo → `update` sungguhan, karena itu menimpa berkas pada agen
  Hermes yang hidup — layak diuji pada profil buang, bukan pada `%LOCALAPPDATA%\hermes`
  yang dipakai. Cukup untuk memutuskan T-85: template profil tenant boleh jadi
  artefak repo (D-69).
- **Relay connector / NAS sengaja tidak ditelusuri lebih jauh.** Keputusan
  §7.3 adalah tidak memakainya; berkas `gateway_enroll.py` dan
  `dashboard_register.py` dibaca hanya sampai cukup untuk memastikan pusatnya
  memang Nous. Bila kelak dipertimbangkan ulang, mulai dari
  `docs/connector-gateway-auth-design.md` di repo connector.
- **Q-10**: apakah WABA pelanggan berada di bawah portfolio kita (menentukan
  D-71 bisa dijalankan).
