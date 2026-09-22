# Runbook: Klien Pertama & Nomor CS

Urutan konkret untuk dua hal: **nomor CS untuk Agentic BOS** dan **satu klien
percobaan**. Ditulis setelah T-69/T-70/T-80, jadi semua langkah di sisi Laravel
sudah ada perintahnya.

Yang dipakai: bridge WhatsApp Hermes (`bawaan`, Baileys). Jalur resmi Meta manual
ada di `RUNBOOK_WABA_MANUAL.md` dan **tidak** dibutuhkan untuk percobaan ini.

---

## Fakta yang menentukan bentuk runbook ini

**Satu bridge = satu nomor = satu port.** Bridge mendengarkan di port **3000**
(bawaan) dan menyimpan sesinya di satu direktori. Dua nomor berarti **dua bridge
di dua port berbeda**, masing-masing di profil Hermes sendiri. Karena
`hermes_nodes` menyimpan satu `api_url`, untuk sekarang **satu baris node per
bridge**. Itu memang bukan maksud asli kolom `max_capacity`, dan tidak akan
berskala — dicatat di §6 sebagai utang.

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
# node untuk bridge CS (ganti portnya sesuai 1.1)
# lewat /admin/hermes-nodes, atau langsung:
#   name=Bridge CS, api_url=http://127.0.0.1:3001,
#   api_secret_reference=none, status=active

php artisan bos:hermes-profile --platform --owner=<id-super-admin> `
    --node=<id-node-cs> --type=addon --label="Bot CS Agentic BOS"
```

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
# node untuk bridge tenant
#   name=Bridge <tenant>, api_url=http://127.0.0.1:3002,
#   api_secret_reference=none, status=active

php artisan bos:hermes-profile --company=<company_id> --node=<id-node-tenant>
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

**2.7 Ubah status profil menjadi siap.** `HermesNodeClient` menolak profil
`unpaired`; setelah QR berhasil, ubah `hermes_profiles.status` menjadi `paired`.
Belum ada UI untuk ini — masih lewat basis data, dan itu utang yang dicatat di §6.

**2.8 Verifikasi berurutan, dari yang tidak mengganggu siapa pun**

| Langkah | Perintah / aksi | Yang diharapkan |
|---|---|---|
| Node hidup | `php artisan bos:hermes-ping` | `OK` untuk node tenant |
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
4. Kita tidak bisa mengirim? `hermes_profiles.status` masih `unpaired`, atau
   `api_secret_reference` bukan `none` padahal bridge tidak punya autentikasi.

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

- **Satu baris `hermes_nodes` per bridge** adalah penyalahgunaan model: `max_capacity`
  jadi tidak bermakna. Yang benar: alamat bridge disimpan **per profil**, bukan per
  node. Layak jadi task tersendiri sebelum tenant ketiga.
- **`hermes_profiles.status` diubah lewat basis data.** Belum ada UI maupun
  perintah, dan tiga kata dipakai untuk keadaan siap yang sama (`paired`,
  `connected`, `active`) tanpa ada yang memvalidasinya.
- **Pairing masih manual di terminal.** Layar QR untuk tenant (T-72) menunggu
  endpoint sesi di Hermes (H-02).
