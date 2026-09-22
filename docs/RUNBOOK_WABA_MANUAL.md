# Runbook: Menyiapkan WABA Resmi secara Manual (T-79)

Untuk menempatkan **satu tenant** pada jalur WhatsApp **resmi** (Meta Cloud API),
dikerjakan dengan tangan. Tidak ada kode baru di Agentic BOS dan tidak ada
tambalan di Hermes — adaptor `whatsapp_cloud` Hermes sudah lengkap.

Dasar keputusan: **D-70 (amandemen)** jalur resmi dikerjakan manual dan memakai
Meta langsung, bukan kirimdev; **D-71** Struktur B, nomor milik klien tetapi WABA
di bawah portfolio kita dan pembayaran lewat kita.

> **Sebelum mulai, sadari satu hal.** Sejak pesan pertama terkirim, tagihan Meta
> masuk ke metode pembayaran **kita**. Meter dan pemutusan otomatis (T-74, T-75)
> **belum ada** — yang menjaga hanya disiplin manual. Ambang kapan itu berhenti
> memadai belum ditentukan (Q-13). Jangan menaruh banyak tenant di jalur ini
> sebelum meter mendarat.

---

## 0. Kapan memakai jalur ini

| | Bawaan (Baileys) | Resmi (Cloud API) |
|---|---|---|
| Cara pasang | scan QR, 5 menit | manual, butuh verifikasi bisnis Meta |
| Risiko nomor diblokir | **ada** | tidak |
| Biaya per pesan | tidak ada | ada, ditagih ke kita (D-71) |
| Cocok untuk | bot operasional internal | **bot CS** yang nomornya disebar publik |

Aturannya (D-70): jalur resmi **hanya untuk bot CS**. Bot operasional internal
tetap bawaan, karena penggunanya internal dan risikonya sudah terkelola.

## 1. Daftar prasyarat

Kumpulkan semuanya **sebelum** menyentuh Hermes. Setengah jalan lalu berhenti
menunggu verifikasi Meta adalah cara tercepat membuat sesi setengah jadi yang
membingungkan.

**Sisi Meta**

- [ ] Business Portfolio (Business Manager) milik **kita**, bukan tenant — Struktur B
- [ ] Verifikasi bisnis selesai
- [ ] Metode pembayaran aktif atas nama kita
- [ ] WhatsApp Business Account (WABA) di dalam portfolio itu
- [ ] Nomor telepon tenant, dan **nomor itu tidak sedang aktif di aplikasi
      WhatsApp biasa maupun WhatsApp Business**. Kalau masih aktif, akun lamanya
      harus dihapus lebih dulu dan riwayat chat-nya hilang. Sampaikan ini ke
      tenant **sebelum** mereka menyerahkan nomornya.
- [ ] Nomor bisa menerima SMS atau panggilan untuk verifikasi
- [ ] App (Meta App) dengan produk WhatsApp ditambahkan
- [ ] **System User** dengan token permanen, bukan token sementara dari panel
      pengujian yang mati dalam 24 jam. Izin yang dibutuhkan:
      `whatsapp_business_messaging` dan `whatsapp_business_management`.
- [ ] App Secret (untuk HMAC) dan Verify Token (string pilihan kita sendiri)

Nama menu di konsol Meta berubah dari waktu ke waktu; yang stabil adalah
konsepnya dan nilai-nilai yang perlu dibawa keluar.

**Sisi kita**

- [ ] Profil Hermes milik tenant itu sudah ada (`profiles/<tenant>/`)
- [ ] URL publik **HTTPS** untuk webhook. Adaptor mewajibkan ini; Meta tidak mau
      mengirim ke HTTP. Di PC pengembangan jalurnya sudah ada lewat cloudflared.
- [ ] Baris `hermes_nodes` untuk node yang menjalankan profil itu (butuh T-70
      bila mau dari UI; sebelum itu lewat basis data)

**Nilai yang harus dicatat sebelum lanjut**

```
PHONE_NUMBER_ID   = ...   (bukan nomor teleponnya, tapi ID-nya di Graph)
ACCESS_TOKEN      = ...   (System User, permanen)
APP_SECRET        = ...
VERIFY_TOKEN      = ...   (kita tentukan sendiri)
WABA_ID           = ...   (opsional, untuk analitik)
```

## 2. Konfigurasi di profil Hermes

Adaptor membaca kredensial lewat **secret scope per profil**, bukan `os.getenv`
global. Jadi nilainya masuk ke `.env` milik profil tenant itu, bukan ke `.env`
akar. Menaruhnya di akar akan membuat profil sekunder membaca nilai profil lain
atau kosong sama sekali.

`%LOCALAPPDATA%\hermes\profiles\<tenant>\.env`

```
WHATSAPP_CLOUD_PHONE_NUMBER_ID=...
WHATSAPP_CLOUD_ACCESS_TOKEN=...
WHATSAPP_CLOUD_APP_SECRET=...
WHATSAPP_CLOUD_VERIFY_TOKEN=...
WHATSAPP_CLOUD_WABA_ID=...
WHATSAPP_CLOUD_WEBHOOK_PORT=8091
WHATSAPP_CLOUD_WEBHOOK_PATH=/whatsapp/webhook
```

Bawaan yang berlaku bila tidak diisi: host tidak di-pin (dual-stack IPv4+IPv6),
port **8090**, path `/whatsapp/webhook`, versi API **v20.0**.

> **Wajib diperhatikan: bentrok port.** Bawaannya 8090 untuk semua profil. Dua
> profil yang sama-sama menjalankan webhook Cloud API akan bertabrakan.
> **Belum diverifikasi** bagaimana Hermes menanganinya — apakah gagal bersuara
> atau diam. Karena itu: beri **port berbeda per profil** sejak tenant pertama,
> dan catat pemetaannya di tabel di §7. Tenant kedua tidak boleh masuk sebelum
> perilaku ini dipastikan.

Lalu nyalakan platform di `profiles/<tenant>/config.yaml`:

```yaml
platforms:
  whatsapp_cloud:
    enabled: true
```

Adaptor resmi adalah **pelengkap** Baileys, bukan penggantinya. Keduanya bisa
tercatat pada profil yang sama, tetapi untuk bot CS pilih satu supaya tidak ada
keraguan nomor mana yang menjawab.

Restart gateway profil itu setelah perubahan.

## 3. Daftarkan webhook di Meta

Callback URL yang didaftarkan ke Meta = URL publik HTTPS + path webhook:

```
https://<domain-publik>/whatsapp/webhook
```

- Verify Token: nilai yang sama dengan `WHATSAPP_CLOUD_VERIFY_TOKEN`
- Meta akan melakukan handshake `hub.verify_token`; adaptor sudah menanganinya
- Berlangganan minimal field **`messages`**

Reverse proxy harus meneruskan ke host:port webhook profil itu, dan **tidak
boleh mengubah badan permintaan**. Verifikasi HMAC dilakukan atas **raw body**,
jadi proxy yang mem-parse lalu menyusun ulang JSON akan merusak tanda tangan dan
setiap pesan masuk akan ditolak.

## 4. Verifikasi — langkah yang bisa dijalankan orang lain

Urutannya sengaja dari yang tidak mengirim pesan ke siapa pun.

**4.1 Platform hidup.** Baca `profiles/<tenant>/gateway_state.json`, cari kunci
`platforms.whatsapp_cloud`. Yang diharapkan `"state": "connected"`. Bila
`"fatal"`, `error_code` menyebut sebabnya — mis. `whatsapp_cloud_unconfigured`
berarti `PHONE_NUMBER_ID` atau `ACCESS_TOKEN` tidak terbaca pada scope profil itu.

**4.2 Node terjangkau dari Agentic BOS.**

```powershell
php artisan bos:hermes-ping
```

**4.3 Handshake webhook.** Panggil callback URL dengan `hub.verify_token` yang
**salah**. Yang diharapkan: ditolak. Kalau diterima, verify token tidak terpasang
dan siapa pun bisa mendaftarkan diri sebagai sumber webhook.

**4.4 Tanda tangan ditolak saat salah.** Kirim POST ke callback URL dengan
`X-Hub-Signature-256` sembarang. Yang diharapkan: ditolak, dan **tidak ada**
jejak pesan masuk di log. Ini test paling penting di runbook ini — kalau lolos,
siapa pun bisa menyuntikkan pesan palsu ke bot tenant.

**4.5 Pesan masuk.** Dari nomor Anda sendiri, kirim pesan ke nomor tenant. Yang
diharapkan: bot menjawab, dan jawabannya **tidak menyebut** Hermes, Nous,
nousresearch, atau jalur berkas apa pun (D-68).

**4.6 Pesan keluar di luar jendela 24 jam.** Tunggu lebih dari 24 jam sejak pesan
terakhir pelanggan, lalu coba kirim pesan biasa. Yang diharapkan: adaptor jatuh
ke template, bukan gagal diam-diam. Kalau template belum disetujui Meta,
kegagalannya harus terlihat.

**4.7 Duplikat.** Kirim ulang webhook yang sama (Meta melakukan ini saat retry).
Yang diharapkan: bot **tidak** menjawab dua kali — proteksi replay `wamid`
menahannya.

## 5. Yang wajib disampaikan ke tenant, apa adanya

- Layar persetujuan Meta **akan menampilkan** platform yang diberi wewenang.
  Jalur resmi tidak bisa disembunyikan sepenuhnya, dan itu memang sifatnya.
- Nomor mereka akan berada di WABA **kita** (D-71). Memindahkannya keluar nanti
  merepotkan. Ini harus terdengar dari kita lebih dulu, bukan ditemukan mereka
  saat ingin pergi.
- Kalau nomor itu masih aktif di WhatsApp biasa, akun lamanya harus dihapus dan
  **riwayat chat-nya hilang**.
- Ada biaya per pesan, dan biaya itu mengalir lewat kita.

## 6. Biaya

Tarif Meta Indonesia per pesan: **marketing Rp586,33**, **utility dan service
Rp356,65**. Sejak **1 Oktober 2026** setiap nomor mendapat **1.000 service
message gratis per bulan**.

"Service" adalah balasan biasa dalam 24 jam setelah pelanggan mengirim pesan —
dan itulah mayoritas percakapan CS. Jadi untuk tenant kecil, kuota gratis itu
menutup sebagian besar pemakaian. Yang mahal adalah broadcast marketing.

Selama meter belum ada, catat manual di tabel §7 dan tinjau **tiap bulan**, bukan
tiap kuartal.

## 7. Catatan per tenant (diisi saat menyiapkan)

| Tenant | Profil Hermes | Nomor | PHONE_NUMBER_ID | Port webhook | Tanggal aktif | Peninjauan biaya terakhir |
|---|---|---|---|---|---|---|
| | | | | | | |

Kolom port sengaja ada: itu satu-satunya nilai yang **wajib** berbeda antar
tenant dan tidak ada yang mengingatkan bila kembar.

## 8. Menghentikan tenant dari jalur resmi

1. Matikan `platforms.whatsapp_cloud.enabled` di `config.yaml` profil, restart.
2. Hapus langganan webhook di Meta.
3. Cabut token System User bila khusus tenant itu.
4. Bila tenant ingin membawa nomornya, prosesnya lewat Meta dan **merepotkan** —
   jadwalkan, jangan janjikan seketika.
5. Tutup tagihan berjalan sebelum WABA dilepas; tagihan Meta tidak berhenti
   hanya karena kita mematikan platform di Hermes.

## 9. Yang belum diverifikasi

Ditulis supaya tidak ada yang menganggap runbook ini lebih matang dari
kenyataannya:

- **Bentrok port `8090` antar profil** — perilaku Hermes belum dipastikan (§2).
  Ini penghalang untuk tenant kedua, bukan tenant pertama.
- Apakah Baileys dan Cloud API benar-benar bisa aktif **bersamaan** pada satu
  profil tanpa saling mengganggu. Docstring adaptor menyebutnya pelengkap, tetapi
  belum diuji.
- Apakah perubahan `.env` profil cukup dengan restart gateway, atau butuh
  restart proses Hermes penuh.
- Langkah persis di konsol Meta; yang dijamin benar di runbook ini adalah nama
  env var dan nilai yang perlu dibawa keluar, karena keduanya dibaca dari kode
  adaptor.
- Semua verifikasi di §4 **belum pernah dijalankan** — runbook ini ditulis dari
  pembacaan kode adaptor, bukan dari satu tenant yang sudah berhasil dipasang.
  Tenant pertama adalah ujinya, dan §4.4 adalah langkah yang paling tidak boleh
  dilewati.
