# Runbook: Klien Pertama & Nomor CS

Urutan konkret untuk dua hal: **nomor CS untuk Agentic BOS** dan **satu klien
percobaan**. Ditulis setelah T-69/T-70/T-80/T-81, jadi semua langkah di sisi
Laravel sudah ada perintahnya.

Yang dipakai: bridge WhatsApp Hermes (`bawaan`, Baileys). Jalur resmi Meta manual
ada di `RUNBOOK_WABA_MANUAL.md` dan **tidak** dibutuhkan untuk percobaan ini.

---

## Fakta yang menentukan bentuk runbook ini

**Satu bridge = satu nomor = satu port.** Bridge mendengarkan di port **3000**
(bawaan) dan menyimpan sesinya di satu direktori. Dua nomor berarti **dua bridge
di dua port berbeda**, masing-masing di profil Hermes sendiri. Sejak T-81 alamat
bridge disimpan **per profil** (`hermes_profiles.api_url`), jadi satu baris node
bisa memegang banyak bridge dan `max_capacity` kembali bermakna. Kolom itu
nullable: profil yang dibiarkan kosong memakai `hermes_nodes.api_url` seperti
sebelumnya.

**Bridge tidak punya autentikasi.** Tidak ada token di port 3000. Karena itu
`api_secret_reference` diisi literal `none`, dan `HermesNodeClient` hanya
mengizinkannya untuk alamat loopback. Port itu **tidak boleh** terekspos keluar.

---

## 1. Nomor CS untuk Agentic BOS

Nomor ini milik kita, melayani siapa pun yang bertanya tentang layanan Agentic
BOS, dan **tidak boleh** menyentuh data tenant.

**1.1 Profil Hermes** — buat profil terpisah (jangan pakai profil dev), nyalakan
platform `whatsapp` di `config.yaml` profil itu, dan **beri port bridge sendiri**
supaya tidak bertabrakan dengan bridge lain.

**1.2 Pairing** — `hermes whatsapp` pada profil itu, scan QR dengan nomor CS.

**1.3 Allowed users** — nomor CS melayani publik, jadi allowlist harus dibuka,
bukan diisi satu nomor. Perhatikan: kalau `WHATSAPP_ALLOWED_USERS` kosong, bridge
jatuh ke **mode self-chat** dan menolak semua orang dengan alasan
`self_chat_mode_rejects_non_self`. Itu keadaan bot dev sekarang.

**1.4 Daftarkan di Agentic BOS**

```powershell
# Satu baris node untuk host ini, dipakai bersama semua bridge di mesin yang sama.
# Lewat /admin/hermes-nodes, atau langsung:
#   name=Host Lokal, api_url=http://127.0.0.1:3000,
#   api_secret_reference=none, status=active

php artisan bos:hermes-profile --platform --owner=<id-super-admin> `
    --node=<id-node> --type=addon --label="Bot CS Agentic BOS" `
    --api-url=http://127.0.0.1:3001
```

`--api-url` adalah port bridge CS dari langkah 1.1. Kosongkan hanya bila bridge
itu memang memakai alamat node.

`--type=addon` membuatnya baca-saja untuk publik dan tidak melayani grup.
`--platform` membuatnya sah tanpa `billing_addon_id` dan memastikan lajur tenant
tidak bisa memakainya.

**1.5 Verifikasi**

```powershell
php artisan bos:hermes-ping
```

Harus `OK`. Kalau `Node hidup tetapi WhatsApp disconnected`, pairing-nya belum
jadi — bukan masalah konfigurasi Agentic BOS.

**Yang belum ada dan perlu Anda putuskan:** bot CS akan ditanya oleh **calon**
pelanggan yang belum punya company. MasterBot API menolak pemanggil tanpa company
(403), jadi tiket tidak bisa dibuat untuk mereka. Penangkapan prospek belum ada di
sistem — tercatat sebagai **Q-09**.

---

## 2. Klien pertama

**2.1 Tenant ada di Agentic BOS** — lewat onboarding seperti biasa. Catat
`company_id` dan pastikan preset-nya punya `system.ai_agent`, karena
`AuthenticateTenantBot` menolak tanpa itu.

**2.2 Profil Hermes untuk tenant** — profil sendiri, port bridge sendiri, sesi
WhatsApp sendiri.

**2.3 Pairing** — `hermes whatsapp` pada profil tenant, scan dengan nomor tenant.

**2.4 Allowed users** — isi nomor pemilik usaha. Nomor itu juga harus ada di
`users.wa_number` dengan `wa_is_verified = true`, kalau tidak lapisan otorisasi
kita menolaknya (D-66) walau Hermes meloloskannya.

**2.5 Daftarkan di Agentic BOS**

```powershell
# Node yang sama dengan 1.4 - yang berbeda hanya port bridge-nya.
php artisan bos:hermes-profile --company=<company_id> --node=<id-node> `
    --api-url=http://127.0.0.1:3002
```

Perintah itu mencetak `secret reference`. **Itulah token** yang dipakai bot untuk
memanggil TenantBot API. Pasang di sisi Hermes; ia tidak ditulis ke log.

**2.6 Sisi Hermes: beri bot akses ke API kita.** Bot memanggil kita sebagai tool,
bukan sebaliknya:

```
Authorization: Bearer <secret reference dari 2.5>
X-Caller-Wa-Number: <nomor pemilik usaha>

GET  /api/bot/tenant/capabilities?company_id=<id>
GET  /api/bot/tenant/context?company_id=<id>
GET  /api/bot/tenant/document-standards?company_id=<id>
POST /api/bot/tenant/contacts
POST /api/bot/tenant/deals
POST /api/bot/tenant/destructive-action
```

**2.7 Segarkan status profil.** `HermesNodeClient` menolak profil `unpaired`.
Setelah QR berhasil, statusnya **tidak** diketik tangan — ia dibaca dari bridge:

```powershell
php artisan bos:hermes-profile-status
```

Atau tekan **Segarkan Status Profil** di `/admin/hermes-nodes`. Penjadwal juga
menjalankannya tiap sepuluh menit, jadi nomor yang lepas sendiri akan turun ke
`unpaired` tanpa perlu ada yang menyadarinya lebih dulu. Profil yang tetap
`unpaired` berarti bridge-nya menjawab selain `connected` — itu masalah pairing,
bukan masalah basis data.

**2.8 Verifikasi berurutan, dari yang tidak mengganggu siapa pun**

| Langkah | Perintah / aksi | Yang diharapkan |
|---|---|---|
| Node hidup | `php artisan bos:hermes-ping` | `OK` untuk node tenant |
| Profil siap | `php artisan bos:hermes-profile-status` | status `paired` untuk profil tenant |
| Bot mengenali usaha | bot memanggil `/capabilities` | 200, daftar kapabilitas tenant itu |
| Isolasi tenant | panggil dengan `company_id` tenant **lain** | **403** |
| Bot menjawab japri owner | owner japri nomor tenant | dijawab |
| Japri orang asing | nomor lain japri | **ditolak** (D-66) |
| Kita bisa mengirim | jalankan pengingat piutang | pesan masuk ke WhatsApp owner |

Langkah "isolasi tenant" adalah yang paling tidak boleh dilewati. Kalau ia lolos,
satu tenant bisa membaca data tenant lain, dan itu kerusakan yang tidak bisa
ditarik kembali setelah terjadi.

---

## 3. Kalau bot membisu

Urutan diagnosis, dari yang paling sering:

1. `curl http://127.0.0.1:<port>/health` → `status` bukan `connected`? pairing
   kedaluwarsa, scan ulang.
2. Log bridge memuat `self_chat_mode_rejects_non_self`? allowed-users belum diisi,
   jadi bridge jatuh ke mode self-chat dan menolak semua orang.
3. Bot dijawab tetapi tidak tahu data usaha? token `Bearer` salah, atau profil
   belum ditautkan ke company, atau preset tenant tidak punya `system.ai_agent`.
4. Kita tidak bisa mengirim? jalankan `bos:hermes-profile-status`. Kalau ia
   melaporkan `unpaired`, masalahnya di bridge. Kalau ia melaporkan "tanpa alamat
   bridge", profil itu belum diberi `api_url` dan node-nya pun tidak punya alamat.
   Kemungkinan lain: `api_secret_reference` bukan `none` padahal bridge tidak punya
   autentikasi.

## 4. Cacat yang sudah diketahui di instalasi ini

- **`link-preview-js` hilang** di `hermes-agent\scripts\whatsapp-bridge\`. Setiap
  pesan yang memuat tautan menghasilkan `url generation failed`. Perbaikannya
  `npm install` di direktori itu.
- **Bridge tanpa autentikasi** di port 3000. Wajar untuk loopback, tetapi port itu
  tidak boleh pernah terekspos ke jaringan.

## 5. Yang belum diverifikasi

- Seluruh runbook ini **belum pernah dijalankan** dari awal sampai akhir. Ia
  disusun dari kontrak yang dibaca di kode, dan klien pertama adalah ujinya.
- Perilaku **dua bridge di dua port** pada satu mesin: belum diuji. Bridge punya
  logika membunuh proses yang mendengarkan di portnya, jadi port yang kembar
  berisiko saling mematikan.
- Apakah profil Hermes bisa menjalankan Baileys **dan** Cloud API sekaligus.

## 6. Utang yang dicatat, bukan disembunyikan

Dua utang yang semula tercatat di sini **sudah dibayar oleh T-81**: alamat bridge
sekarang milik profil (`hermes_profiles.api_url`), dan status profil diturunkan
dari bridge lewat `bos:hermes-profile-status` serta tombol di panel admin.

Yang masih terbuka:

- **Kosakata status masih permisif di sisi baca.** `hermes.delivery.ready_statuses`
  tetap menerima `paired`, `connected`, dan `active` supaya baris lama tidak
  mendadak berhenti mengirim. Sisi tulis hanya pernah menulis `paired`/`unpaired`,
  jadi sinonim itu akan habis sendiri; membersihkannya sekarang berarti memigrasi
  baris yang belum tentu ada.
- **Pairing masih manual di terminal.** Layar QR untuk tenant (T-72) menunggu
  endpoint sesi di Hermes (H-02).
- **Allowlist Hermes di luar jangkauan tenant.** Siapa yang boleh bicara dengan bot
  ditentukan di dua gerbang bertumpuk: allowlist di sisi Hermes, lalu otorisasi
  kita (D-66). Gerbang pertama hanya bisa disetel dari sisi Hermes, jadi untuk
  klien pertama **hanya nomor owner yang jalan**. Mengundang staf (T-51) baru
  berguna setelah H-03.
