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

### 1.3 Baileys adalah subprocess Node, bukan servis HTTP

`plugins/platforms/whatsapp/adapter.py` menjalankan **bridge Node.js sebagai
subprocess** (jejaknya `%LOCALAPPDATA%\hermes\whatsapp\bridge.log`). QR muncul di
CLI atau dashboard. Tidak ada endpoint HTTP untuk memulai sesi, mengambil QR,
atau membaca status.

**Konsekuensi:** T-72 (layar pairing QR di web kita) **wajib** didahului
pekerjaan di sisi Hermes. Tidak ada jalan memutar.

---

## 2. Yang sudah ada vs yang harus dibangun

| Kebutuhan kita | Ada hari ini? | Bentuknya |
|---|---|---|
| Mengobrol dengan agent lewat HTTP | **Ada** | `api_server` `/v1/chat/completions`, `/v1/runs` |
| Mengalamatkan profil per tenant | **Ada** | prefiks `/p/<profil>/` + `gateway.multiplex_profiles` |
| Health check node | **Ada** | `GET /health`, `/health/detailed` |
| Auth ke node | **Ada** | `API_SERVER_KEY` |
| Approval run dari luar | **Ada** | `POST /v1/runs/{id}/approval` |
| **Kirim WhatsApp dari luar** | **TIDAK ADA** | perlu endpoint baru di Hermes, atau `hermes send` (CLI) |
| **Mulai sesi WA + ambil QR + status** | **TIDAK ADA** | perlu endpoint baru di Hermes |
| **Pairing pengguna dari luar** | **TIDAK ADA** (CLI saja) | `hermes pairing list\|approve\|revoke\|clear-pending`, `gateway.pairing.PairingStore` |
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
di atasnya. Yang belum ada: permukaan HTTP-nya.

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

## 6. Pekerjaan sisi Hermes yang sekarang menjadi prasyarat

Ketiganya memblokir task di Fase 10 dan **bukan** pekerjaan Laravel:

1. **Endpoint kirim pesan** (memblokir T-69, T-71). Minimal: kirim teks ke satu
   nomor pada satu profil, mengembalikan konfirmasi terkirim yang bisa dibedakan
   dari "diterima tapi gagal".
2. **Endpoint sesi WhatsApp** (memblokir T-72): mulai sesi, ambil QR, baca
   status, logout.
3. **Endpoint pairing pengguna** (memblokir T-77): list, approve, revoke —
   membungkus `PairingStore` yang sudah ada.
4. **Override base URL Cloud API** (memblokir T-71 jalur resmi): satu konstanta
   dijadikan konfigurasi.

Keempatnya kecil secara kode, tetapi semuanya di repo lain dan perlu keputusan
Bos: ditambal lokal (utang pemeliharaan) atau diusulkan ke upstream (menunggu).

## 7. Yang belum dibaca dan masih perlu dipastikan

Supaya tidak ada yang mengira dokumen ini lebih lengkap dari kenyataannya:

- `hermes_cli/send_cmd.py` — apakah `hermes send` bisa menjadi jalur sementara,
  dan dengan argumen apa. Catatan: menjalankan CLI dari Laravel berarti
  memberi aplikasi web akses ke home Hermes yang memuat kredensial plaintext,
  jadi ini bukan jalur produk.
- `gateway/delivery.py`, `delivery_ledger.py` — apakah ada primitif kirim yang
  layak dibungkus endpoint.
- `hermes_cli/web_server.py` + `web_routers/` — tempat paling wajar menambahkan
  router pairing/whatsapp; `web_routers/` sekarang hanya `cron, git, mcp,
  profiles, sessions, skills, tools`.
- Dokumentasi kirimdev: bentuk payload webhook, nama header HMAC, dan apakah
  benar-benar sepadan dengan `X-Hub-Signature-256` milik Meta.
- **Q-10**: apakah WABA pelanggan berada di bawah portfolio kita (menentukan
  D-71 bisa dijalankan).
