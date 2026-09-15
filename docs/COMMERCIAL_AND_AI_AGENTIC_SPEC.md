# COMMERCIAL & AI AGENTIC SPECIFICATION
## Arsitektur Jasa Karyawan AI, Membership SaaS & Integrasi Ekosistem

**Versi:** 1.0.0-PROD  
**Konteks Bisnis:** Fondasi **Agentic Business Operating System (BOS)** (nama warisan "ERP Prime" tidak dipakai lagi, D-30) — penjualan jasa **Karyawan AI (Hermes Engine)** yang dibungkus dengan UI ERP modern dan membership bertingkat.

---

## 1. Pemisahan Tegas Dua Produk Ekosistem

```
┌────────────────────────────────────────────────────────┐
│               NALARPESAN (Mini POS & CRM WA)            │
│  Tujuan: Memudahkan pelanggan pesan via WA / Web QR     │
│  Fokus : Order Intake, Menu Catalog, Scan Meja, QRIS    │
└──────────────────────────┬─────────────────────────────┘
                           │ Webhook Bridge (Idempotent)
                           ▼
┌────────────────────────────────────────────────────────┐
│               AGENTIC BOS (Back-office + AI Karyawan)  │
│  Tujuan: Back-office, Akuntansi, Kontrol AI Multi-Grup │
│  Fokus : SOP Otomatis, Laporan Keuangan, Approval Bos  │
└────────────────────────────────────────────────────────┘
```

1. **NalarPesan:**
   - Aplikasi Mini POS & CRM interaksi pelanggan depan (*front-facing*).
   - Nilai lebih utama: Pesan langsung via WhatsApp tanpa install aplikasi, kumpul nomor WA otomatis untuk database CRM.
2. **Agentic BOS:**
   - Sistem operasi internal bisnis (*back-office & intelligence*).
   - Nilai lebih utama: **Karyawan AI (Hermes)** yang membaca data ERP, menjalankan SOP, dan melapor ke Bos di WhatsApp.

---

## 2. Arsitektur Membership & Pricing Dinamis (Non-Hardcode)

Sistem membership **TIDAK DI-HARDCODE** di kode program. Tabel `company_memberships` dan `membership_plans` menyimpan parameter kuota dan harga yang dapat diubah kapan saja oleh Platform Super Admin. Setiap perubahan saldo dicatat di `token_ledger_entries`; `current_token_balance` hanya cache operasional yang direkonsiliasi dengan ledger.

### 2.1 Skema Data Membership (`membership_plans`)

| Field | Tipe Data | Keterangan |
|---|---|---|
| `id` | BIGINT PK | Auto increment |
| `name` | VARCHAR(64) | Contoh: Starter, Growth, Pro, Enterprise |
| `slug` | VARCHAR(64) UNIQUE | `starter`, `growth`, `pro`, `enterprise` (panjang mengikuti `DATA_MODEL.md` §11.1) |
| `monthly_price` | DECIMAL(18,2) | Biaya langganan per bulan (dinamis) |
| `annual_price` | DECIMAL(18,2) | Biaya langganan per tahun (diskon) |
| `max_wa_groups` | INT | Batas grup WhatsApp yang boleh di-invite bot |
| `monthly_token_quota`| BIGINT | Kuota base token AI per bulan |
| `allowed_presets` | JSON | Daftar preset bisnis yang boleh dipilih |
| `features` | JSON | Feature flags yang terbuka untuk paket ini |
| `is_active` | BOOLEAN | Status aktif katalog |

### 2.2 Ekonomi Token & Base Token Multiplier

Karena harga modal setiap otak AI (misal: Llama 3 vs GPT-4o) berbeda-beda, sistem BOS menggunakan mekanisme **Base Token Multiplier** agar Bos (Klien) tidak pusing dengan konversi dolar.
1. **Mata Uang BOS Token:** 1 Token BOS setara dengan 1 Token Input dari model AI termurah di pasaran (misal: Llama-3-8b). Ini disebut *Multiplier 1x*.
2. **Pemotongan Otomatis:** Semua paket bebas memilih model apa pun (D-15/D-28). Jika Bos memilih model pintar (misal GPT-4o) yang harga modalnya 10x lipat lebih mahal, maka setiap pemakaian akan mencatat debit idempoten pada `token_ledger_entries` lalu memperbarui cache saldo perusahaan (`current_token_balance`) dalam transaksi yang sama. Token Output (jawaban AI) yang secara struktur biaya lebih mahal dari Input juga memiliki *multiplier* tersendiri (misal 3x lipat dari Input).
3. Tabel rahasia `ai_model_pricings` di *Backend* menyimpan rasio perkalian ini sehingga harga *SaaS Provider* terlindung dari kerugian akibat lonjakan harga AI.

### 2.3 Simulasi Paket (Dapat Diedit Manual di Super Admin)

> *Catatan: Angka di bawah adalah simulasi awal untuk riset pasar, bukan hardcode.*

| Parameter | Simulasi: Starter | Simulasi: Pro / Growth | Simulasi: Enterprise |
|---|---|---|---|
| **Target Bisnis** | Usaha Rintisan / 1 Cabang | Bisnis Berkembang / 2-5 Cabang | Korporasi / Jaringan Cabang |
| **Harga Acuan** | Rp 500.000 – Rp 1.000.000 / bln | Rp 2.000.000 – Rp 3.500.000 / bln | Rp 10.000.000+ / bln |
| **Karyawan AI (Hermes)** | 1 Asisten (Grup Kasir/Admin) | 3 Asisten (Kasir, Gudang, Keuangan) | Unlimited Asisten & Grup Khusus |
| **DM Bos (Personal CFO)**| Ringkasan Mingguan | Full Analisis Finansial Realtime + Approval | Kustom Model & Fine-tuning |
| **Kuota Base Token** | 500.000 token / bln | 3.000.000 token / bln | 15.000.000+ token / bln |
| **Topup Add-on Token** | QRIS Mandiri via Dashboard | QRIS Mandiri via Dashboard | Invoicing Korporat |
| **WhatsApp Engine** | Hermes Terpusat (White-label) | Hermes Terpusat (White-label) | Dedicated Hermes Terpusat (White-label) |
| **Infrastruktur** | Shared Multi-Tenant DB | Shared Multi-Tenant DB | Dedicated VPS / On-Premise |

---

## 3. Rekomendasi Arsitektur Multi-Tenant untuk Target 500 Klien

Untuk mencapai skala 500 klien aktif secara stabil, hemat biaya server, dan mudah di-maintain:

### 3.1 Model Hibrida 2-Tier

```
[ Klien 1 - 480 (SaaS Multi-Tenant) ]
  ├── 1 Cluster Database ERP (MySQL / MariaDB dengan Tenant Isolation via `company_id` scoping)
  ├── Multi-Node Hermes Cluster (Horizontal Scaling)
  │   ├── Hermes Node 01 (Max 100 Sesi)
  │   ├── Hermes Node 02 (Max 100 Sesi)
  │   └── Hermes Node 03 (Max 100 Sesi)
  └── Profil Direktori Terisolasi (disebar sesuai Node alokasi): `~/.hermes/profiles/{tenant_slug}/`
      ├── SOUL.md (Identitas bisnis & SOP hasil generate form)
      ├── config.yaml (Whitelist tools: hanya MCP ERP, terminal/shell OFF)
      └── state.db (Histori chat terisolasi per tenant)

[ Klien Enterprise 1 - 20 (Dedicated Private Cloud) ]
  └── 1 VPS Khusus per Klien (Docker Compose: NalarPesan + Dedicated DB via TenantProvisioner + Hermes Dedicated)
```

### 3.2 Alur Provisioning Otomatis Saat Onboarding Klien Baru

Ketika klien membeli paket membership:
1. **DB ERP:** Buat record `Company`, `BusinessIdentity` (Tax mode), dan `User` owner.
2. **Membership:** Pasang `CompanyMembership` aktif dengan kuota grup & token sesuai paket. Kredit awal dicatat pada `token_ledger_entries` dan cache saldo diperbarui dalam transaksi yang sama.
3. **Preset Bisnis:** Jalankan seeder preset (misal: `pharmacy`) → load default feature flags.
4. **Hermes Profile (Node Load Balancing & Provisioning):**
   - Laravel akan mencari `hermes_nodes` yang masih aktif dan jumlah profilnya < `max_capacity`.
   - Jalankan `php artisan tenant:provision-ai-profile {slug} --owner-id={user_id} --node-id={node_id}`
   - Sistem mengirim perintah API ke Node terpilih untuk membuat folder `~/.hermes/profiles/{slug}/` di host node tersebut.
   - Mengisi `SOUL.md` baku yang berisi identitas Bos dan *daftar dinamis seluruh ID perusahaan* yang dimilikinya.
   - Mengunci `config.yaml` dengan whitelist tool MCP tingkat *User* (`mcp_erp_user_{user_id}`) yang mana setiap *tool*-nya mewajibkan parameter `company_id`.
   - Menyimpan referensi secret (bukan plaintext) beserta `node_id` ke tabel `hermes_profiles`. Satu profile per **company** (Q-04 default); owner dengan banyak company memiliki beberapa profile.
5. **WhatsApp QR:** Tampilkan QR pairing (diambil dari API Hermes) di menu `/settings/ai-agent` untuk ditautkan oleh owner.
6. **AI Onboarding Interview (Universal Business Adaptation):**
   - Segera setelah Bos melakukan *scan* QR, Asisten AI mengirim pesan sapaan otomatis ke WA Bos: *"Halo Bos! Bisnis Bapak/Ibu bergerak di bidang apa? Apakah butuh struk kasir, atau sekadar pencatatan utang-piutang?"*
   - Berdasarkan jawaban Bos, AI akan merakit *Custom Preset* (Preset ke-7) di *background*. AI mengeksekusi *tool* untuk men-*toggle* (menghidup-matikan) fitur-fitur modular di ERP.
   - Saat Bos membuka aplikasi Web BOS, menu di *sidebar* sudah terpersonalisasi secara presisi hanya menampilkan fitur yang relevan dengan usahanya.

---

## 4. Keamanan, Guardrail & White-Label Karyawan AI

Untuk memastikan agen WA tidak liar dan tidak dapat di-*jailbreak* oleh staf atau pelanggan, BOS menerapkan 6 lapis pengaman:

1. **Lapis 1: Chat-Driven Management (Two-Way Sync)**
   - Bos memiliki kuasa penuh mengatur operasional (contoh: mengubah batas diskon, jam buka, hingga gaya bahasa) murni lewat *chat* WA, layaknya memberikan instruksi kepada asisten manusia.
   - Bot dibekali alat `mcp_update_company_settings`. Saat Bos membuat aturan lisan di WA, bot akan menggunakan API ini untuk menulis dan mengunci aturan tersebut ke dalam tabel `module_settings` ERP secara seketika.
   - Aturan lisan dari Bos ini otomatis menjadi Lapis 3 (Hard-Limit) di sisi *backend*, yang **mengekang ketat staf/bawahannya** tanpa membatasi fleksibilitas si Bos itu sendiri. Klien/Bos dilarang menyentuh konfigurasi mesin *Hermes* (Anti-Jailbreak difokuskan pada perlindungan mesin).

2. **Lapis 2: Role-Based Tool Whitelisting & Guard Kuota**
   - Bot menolak diundang ke grup melebihi kuota `max_wa_groups`.
   - Autentikasi ketat: Bot mencocokkan nomor pengirim pesan dengan kolom `wa_number` di tabel `users`. Jika belum terdaftar, bot mengabaikan.
   - Agen hanya diberikan akses ke tool API (MCP) yang sesuai dengan peran staf yang memanggilnya. Di grup Kasir, bot tidak memiliki tool untuk mengakses laporan laba rugi.

3. **Lapis 3: Server-Side Validation & Cross-Company Security (Hard-Limit)**
   - Karena 1 bot melayani banyak perusahaan, setiap *Tool API* mewajibkan parameter `company_id`.
   - Jika bot diretas dan mencoba mengirim `company_id` milik orang lain, **API Backend BOS akan menolaknya** dengan `403 Forbidden` (Backend mengecek relasi kepemilikan `user_id` dengan `company_id`).
   - API juga secara ketat memvalidasi payload request agen terhadap pengaturan `max_discount` atau limit operasional milik perusahaan terkait.

4. **Lapis 4: Human-in-the-Loop (Persetujuan Bos)**
   - Untuk tindakan destruktif atau berisiko tinggi (seperti pengeluaran kas besar atau void transaksi), bot diwajibkan melakukan *2-step confirmation*.
   - Bot tidak mengeksekusi secara mandiri, melainkan mengirimkan "Kartu Persetujuan" berisi **kode tiket** ke DM WhatsApp Bos: *"Staf meminta eksekusi berisiko (Void). Balas `YA 4821` untuk menyetujui atau `TIDAK 4821` untuk menolak."*
   - Tindakan tereksekusi hanya jika Bos membalas `YA <kode>` yang cocok dan belum dipakai (D-11/D-27). `YA` polos ditolak.

5. **Lapis 5: Pembersihan Jejak Engine (White-Label 100%):**
   - Menghilangkan nama Hermes, Nous Research, atau framework open-source di respons teks, pesan error, dan metadata API.
   - Respon bot murni memposisikan diri sebagai *"Asisten Digital [Nama Bisnis Tenant]"*.

6. **Lapis 6: Arsitektur Jembatan Barcode (QR Code Bridge):**
   - Karena mesin *Hermes* sudah terintegrasi secara *native* dengan *WhatsApp Client* (bisa memproduksi QR Code mandiri), BOS tidak lagi membutuhkan *software* WA Gateway terpisah.
   - **Alur Penyambungan:** Klien HANYA boleh mengakses Web BOS (Laravel). Saat Klien membuka halaman "Hubungkan WA", *Backend* Laravel akan memanggil API rahasia ke mesin Hermes untuk "meminta" gambar QR Code.
   - Hermes memproduksi QR, lalu Laravel menampilkannya di Web BOS. Begitu Klien men-scan QR tersebut dari Web BOS, WA-nya langsung tersambung kuat dengan mesin Hermes di *backend*. (Menjaga ilusi *White-Label* tetap utuh di mata klien).

## 5. Orkestrasi Modul CRM Pintar (Contacts & Deals)
Dalam konteks CRM B2B/Jasa, agen memiliki kecerdasan hierarkis untuk membedakan "Orang" (Kontak) dan "Proyek" (Deals):
- **Pencatatan Orang:** *"Bos, ada prospek baru nama Budi WA 0812"* → Agen memanggil `mcp_create_crm_contact`.
- **Pencatatan Proyek/Peluang Uang:** *"Tadi si Budi deal bikin website harga 10 juta"* → Agen memanggil `mcp_create_crm_deal(contact_id: X, title: 'Website', deal_value: 10000000)`.
- **Pencatatan Log Histori Ganda:** *"Saya barusan nekat telpon Budi bahas diskon website"* → Agen memanggil `mcp_log_crm_activity(contact_id: X, deal_id: Y, type: 'call')`. Log ini akan menempel di profil Pak Budi sekaligus di profil Proyek Website.

---

## 6. Orkestrasi Modul Akuntansi (Finance / Cashbook)
Sistem *backend* menuntut pencatatan *Double-Entry* (Jurnal Debit/Kredit) yang ketat. Namun agen AI dilatih untuk menyembunyikan kerumitan ini dari Bos UMKM.
- **Pencatatan Simpel:** *"Bos: Tolong catat pengeluaran listrik 500rb hari ini."*
- **Translasi AI:** Agen memanggil `mcp_record_expense(amount: 500000, category: 'Listrik')`.
- **Eksekusi Backend:** API menerima *request* simpel tersebut dan mengubahnya menjadi Jurnal Akuntansi baku secara otomatis: Debit Beban Listrik 500rb, Kredit Kas 500rb. (Mendukung integrasi tanpa cela ke Pro Mode).
- **Laba-Rugi per Proyek (Project Costing):** Jika Bos menyebutkan *"pengeluaran semen 2jt untuk proyek kincir angin"*, agen wajib mengekstrak nama proyek tersebut dan menyematkan parameter `deal_id` ke dalam Jurnal Akuntansi. Hal ini memungkinkan laporan laba-rugi terpisah per proyek (sangat esensial untuk klien Kontraktor).

---

## 7. Orkestrasi Modul HRD & Asisten (Penggajian & Task)
Menjawab kebutuhan klien *Mid-Market* (Agensi Kreatif & Kontraktor):
- **Perhitungan Gaji (Payroll):** Agen memiliki kapabilitas membaca tabel `hr_employees` dan memicu kalkulasi `hr_payrolls` untuk pembuatan slip gaji.
- **Drafting Kontrak Hukum:** Otak LLM (Hermes) difungsikan murni sebagai *Think-Tank*. Bos dapat memerintahkan *"Buat kontrak PKWT 1 tahun untuk Budi, gaji 5 juta"*, dan agen akan memproduksi teks kontrak formal.
- **Manajemen Task (Reminders):** Bos dapat menugaskan *"Ingatkan saya tagih invoice besok jam 9 pagi"*. Agen memanggil `mcp_create_reminder(task: 'tagih invoice', datetime: 'besok 9 pagi')`. Server akan menyimpannya ke tabel `ai_reminders` dan menggunakan *Cron Job* untuk membangunkan agen agar menge-chat Bos pada waktu yang ditentukan.

---

## 8. Agen Pusat (Master Bot / BOS Care)

Selain "Bot Asisten" milik klien, platform SaaS BOS mengoperasikan satu **Master Bot (Agen Pusat)** khusus untuk pelayanan pelanggan.
Nomor WA terpusat ini (misal: "BOS Care") bertugas melayani seluruh Klien/Bos dari berbagai perusahaan yang terdaftar.

1. **Autentikasi Otomatis (Global):**
   - Saat menerima chat, Master Bot akan mengecek tabel `users` untuk mencari `wa_number`.
   - Bot langsung mengidentifikasi Klien beserta `company_id`-nya tanpa perlu proses *login* manual.

2. **Ticketing & Customer Service:**
   - Klien dapat melaporkan masalah (misal: "bot kasir saya tidak merespons").
   - Master Bot menggunakan *tool* `mcp_create_support_ticket` untuk mencatat masalah ke tabel `support_tickets` di tingkat platform.
   - **Resolution Push:** Saat tiket ditutup (*resolved*) oleh *developer* di dasbor internal, *webhook* memicu Master Bot untuk mengirim pesan WA proaktif ke Klien: *"Halo Bos, kendala kasir Anda sudah kami perbaiki."*

3. **Manajemen Tagihan & Kuota (Auto-Billing):**
    - Klien dapat mengecek sisa token. Bot memanggil `mcp_check_token_balance` untuk membaca `company_memberships`; mutasi saldo selalu direkonsiliasi dengan `token_ledger_entries`.
   - **Pembuatan Tagihan Dinamis:** Saat klien minta *Top Up*, bot memanggil `mcp_generate_topup_invoice(amount)`. *Backend* menembak API Midtrans/Xendit untuk merilis *Snap URL* (Link Pembayaran berisi QRIS/VA) dan menyerahkannya ke bot. Bot mengirimkan link tersebut ke WA klien.
   - **Auto Top-up (Webhook):** Setelah klien membayar, *Payment Gateway* mengirim *Webhook* ke *Backend*. Sistem memvalidasi transaksi, otomatis menambah saldo token, dan memicu Master Bot mengirim pesan konfirmasi: *"Lunas Bos! 2 Juta Token berhasil ditambahkan."*
