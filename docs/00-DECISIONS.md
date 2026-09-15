# DECISIONS LOG — KONSOLIDASI DISKUSI PRD AGENTIC BOS
## Single Source of Truth Seluruh Keputusan Diskusi

**Tanggal konsolidasi:** 2026-09-12 (revisi 2026-09-16)
**Sumber:** Diskusi PRD + dokumen modul warisan ERP Prime (tidak ada di repo ini; hanya keputusannya yang dibawa)
**Lokasi dokumen:** `D:\PROJECTS\agentic-bos\docs\`

Status: `LOCKED` = keputusan final Bos. `OPEN` = menunggu diskusi lanjutan.

---

## Keputusan LOCKED

| ID | Keputusan | Detail |
|---|---|---|
| D-01 | **6 Preset bisnis awal, arsitektur untuk ≥50** | Agency, F&B, Apotek, Event Organizer, Kontraktor, Persewaan sebagai **preset pertama**, bukan batas. Onboarding 1-klik + override per company. Matriks di `INDUSTRY_PRESETS.md`. Sejak D-31, preset adalah **data** sehingga preset ke-7..50 ditambah tanpa kode. |
| D-02 | **Model config = 1B (Preset + Override)** | Bukan toggle manual murni. Company pilih preset industri → `definition` preset dimuat (kapabilitas, terminologi, workflow, widget) → bisa override via `module_settings` (Pengaturan > Fitur Bisnis). |
| D-03 | **Tax mode per Business Identity** | Semua identity bisa pilih: jenis buku `taxable` / `non_taxable`. Jika taxable → pilihan `price_includes_tax` (harga sudah termasuk PPN atau belum). Ini adalah konfigurasi dasar semua transaksi; formula DPP inclusive = total / 1.11. Di `REQUIREMENTS.md` §1. |
| D-04 | **NalarPesan ≠ Agentic BOS — dua layanan berbeda** | NalarPesan = Mini POS front-facing, tujuan utama memudahkan klien order + pemilik kelola bisnis, nilai plus CRM WhatsApp (pesan via WA; opsi channel lain tetap ada). BOS = back-office ERP + Karyawan AI. Terhubung via webhook bridge idempotent. |
| D-05 | **Membership & pricing DINAMIS, tidak hardcode** | Harga, jumlah paket, kuota ditentukan belakangan via riset pasar. Simulasi 3 tier di `COMMERCIAL_AND_AI_AGENTIC_SPEC.md` hanya placeholder, edit manual via Super Admin. Struktur canonical: `membership_plans`, `company_memberships`, dan `token_ledger_entries`. |
| D-06 | **Arsitektur Multi-Node dan Tenant Hybrid untuk 500+ klien** | SaaS reguler memakai database bersama dengan isolasi `company_id` yang fail-closed; Enterprise dapat memakai database/VPS dedicated melalui `TenantProvisioner`. 1 Node Hermes (Process Manager) maksimal memegang ~100 sesi Klien. Laravel menyimpan *Node Registry* dan melakukan *Load Balancing* ke Node yang masih kosong. (Mencegah Single Point of Failure akibat *Memory Leak*). |
| D-07 | **Model AI per-tenant, masing-masing pilih sendiri** | Tenant dapat memilih model/provider sendiri dari katalog yang disediakan platform. |
| D-08 | **Topup token otomatis via QRIS** | Pembayaran QRIS → webhook callback payment gateway → kuota token ter-update otomatis tanpa konfirmasi admin. Webhook wajib fail-closed (validasi signature, idempotency, transaksi atomik). **Belum ada implementasi di repo ini** — dibangun pada T-19. |
| D-09 | **White-label 100% + anti-jailbreak** | Klien tidak bisa edit raw SOUL.md/tools. SOP via form terstruktur (max ~300 char) → service rakit jadi SOUL.md baku. Toolset tenant whitelist: hanya MCP ERP; terminal/file/code-exec OFF. Hilangkan semua jejak Hermes/Nous dari respons. |
| D-10 | **Role grup WA + kuota per paket** | Grup dikunci role: Kasir (dilarang lihat laba-rugi/gaji), Gudang (dilarang kasir/keuangan), Keuangan. Kuota `max_wa_groups` dari membership plan. Bind `group_jid` permanen. Onboarding `/role` command di grup. |
| D-11 | **DM Bos = Personal Assistant** | Nomor owner verified → akses full read data keuangan tenant (omzet, cashflow, margin), analisis bebas konteks, aksi finansial wajib 2-step confirmation ("YA 1234"). |
| D-12 | **Sidebar & form dinamis (zero bloat)** | Menu level 1/2 dari `DynamicMenuRegistry` — fitur mati tidak dirender sama sekali. Route di-guard middleware `EnsureFeatureEnabled` (403). Form field kondisional per feature flag (contoh: PIC hanya Agency/EO/Kontraktor). |
| D-13 | **Deployment NalarPesan = Hybrid Sharded** | Fase 1 (0–50 klien) 1–2 shard shared + route key `tenant_slug→shard_id` di DB sejak awal. Fase 2 scale-out tambah node (1 shard = 25–50 klien, node 8–16GB RAM). Nomor Official Meta Cloud API = jalur terpusat (bebas ban-risk). Klien spam-problem/high-volume → eject dedicated VPS. |
| D-14 | **Token AI disediakan platform (key pusat)** | Tenant pilih model dari katalog platform, memakai key pusat Nalarin. Biaya model dihitung ke metering token per tenant (bukan BYO-key). Margin dari selisih harga token. |
| D-15 | **Semua tenant bebas pilih model premium** | Tidak ada pembatasan model per paket membership. Konsekuensi alami: model mahal membakar token lebih cepat → habis lebih cepat → mendorong topup. Self-balancing tanpa aturan ekstra. |

---

## Keputusan LOCKED — UX/UI (diskusi 2026-09-12)

| ID | Keputusan | Detail |
|---|---|---|
| U-01 | **Onboarding preset via wawancara/form — bukan wizard paksa** | Setting preset bisnis dipilih SETELAH wawancara dengan AI atau human (sales/onboarding call), atau setelah user isi form onboarding. Tidak ada wizard paksaan saat login pertama. Detail mekanisme wawancara: OPEN (lihat Q-05). |
| U-02 | **Halaman pengaturan = satu halaman friendly dengan tab-tab** | Buka satu halaman, navigasi via tab. Struktur tab DINAMIS dari tab registry (tidak hardcode) — tab muncul sesuai role user & feature flag tenant. Super Admin lihat lebih banyak tab, owner tenant lihat tab operasional saja. |
| U-03 | **Dashboard per industri = card khusus kontekstual** | Masing-masing industri punya card/widget khusus: Apotek = Antrean Resep Hari Ini + Stok Expiry Dekat; Rental = Unit Tersewa + Booking Hari Ini + Jatuh Tempo Pengembalian; F&B = Meja Aktif + Open Bill; Kontraktor = Progres Proyek + Retensi Tertahan; EO = Event Mendatang; Agency = Pipeline Deal + Timesheet. |
| U-04 | **Sidebar zero-bloat: tidak dipakai = tidak muncul** | Modul/fitur yang tidak diaktifkan tenant SAMA SEKALI tidak dirender (bukan disabled/greyed-out, tanpa badge "Upgrade"). |
| U-05 | **Topup token satu tempat, subtle — bukan CTA jualan** | Topup hanya muncul di satu tempat (dalam konteks tab Penggunaan & Paket), didesain natural/integrated seperti informasi kuota, BUKAN banner/tombol CTA mencolok yang terlihat jualan token. |
| U-06 | **Dynamic Theme & Mode Gelap/Terang (Non-Hardcode)** | Dukungan ganti skema warna branding tenant (katalog tema aman WCAG AA) + toggle mode terang/gelap personal per user tanpa hardcode CSS, berbasis 36 token `--erp-*`. |
| U-07 | **Universal Search (Ctrl+K) via Laravel Scout** | *Command Palette* berfungsi sebagai mesin pencari global (*Database/Meilisearch*). Mencari kontak, tagihan, dan navigasi lintas modul dalam satu ketikan, dengan *scoping* ketat per `company_id`. |
| U-08 | **App Switcher Terisolasi (Odoo-Style)** | *Sidebar* tidak dijadikan satu (*Unified*). Saat menekan ikon 9-titik, Klien memilih Modul (misal: HRD). Setelah diklik, layar dan *Sidebar* murni hanya menampilkan menu HRD. Sangat fokus, bersih, dan bebas *bloatware*. |
| D-16 | **Urutan Eksekusi Dimulai dari UI Kernel & Dynamic Sidebar (Opsi B)** | Bongkar sidebar bloated dan bersihkan antarmuka terlebih dahulu via `DynamicMenuRegistry` & middleware `EnsureFeatureEnabled`. UI memakai Laravel 13 + Livewire v4 + Blade + Tailwind v4 + Vite (Alpine ikut dari bundel Livewire, bukan paket terpisah); modul tersembunyi jika dimatikan. |
| D-17 | **Mode Pajak Campuran / Add-on Berbayar / Enterprise** | Konfigurasi dasar adalah satu mode pada `BusinessIdentity`: Non-PKP atau PKP dengan harga inklusif/eksklusif. Override inklusif/eksklusif per item/transaksi atau multi-tax rate hanya dijadikan add-on berbayar/paket Enterprise setelah kontraknya ditetapkan. |
| D-18 | **Data Model Decoupled Modular (Opsi A) — Warisan Paving Dipertahankan** | Tabel alur rantai pasok kompleks peninggalan pabrik (SPH, PO bertingkat, DO, Jurnal Manufaktur) tetap dipertahankan untuk klien pengusaha besar/korporat, namun dibungkus fitur flags sehingga sama sekali tidak muncul pada tenant UMKM/ritel. |
| D-19 | **Granular Config Per-Module Per-Company** | Setiap modul memiliki skema konfigurasi JSON independen per tenant (tabel `module_settings` dengan field `module_name`, `company_id`, `settings_json`). Contoh CRM: Company A bisa aktifkan pipeline + deal stage custom, Company B hanya aktifkan phonebook kontak + auto-sync WhatsApp tanpa form rumit. |
| D-20 | **UX Visual Redesign: Midnight Command + Operator Grid Hybrid** | Varian prototype terpilih: **A (Midnight Command)** untuk dashboard & shell eksekutif (dark-first, rail sidebar ikon, command palette `Ctrl K`, omzet hero, KPI sparkline, feed agentic); **C (Operator Grid)** untuk halaman operasional (telemetri kas/kuota/sync di topbar, tabel padat, log live, monospace angka). Semua path mengikuti skema `/app/{module}/...` (lihat D-24). |
| D-21 | **Autentikasi Tradisional (Web Login)** | Menggunakan Email dan Password standar untuk akses masuk ke aplikasi Web (SaaS). WhatsApp secara eksklusif hanya digunakan sebagai saluran komunikasi asisten AI (Hermes), bukan sebagai gerbang otorisasi Web. |
| D-22 | **Bring Your Own Storage (BYOS) via Google Drive** | Semua lampiran fisik (*file* foto, nota, kontrak) di-upload dan disimpan di Google Drive milik Klien melalui integrasi API. Sistem SaaS hanya menyimpan *URL link* ke dalam satu tabel *Polymorphic* bernama `attachments`. Menghemat biaya *server storage* secara drastis. |
| D-23 | **SaaS Billing via Proactive WA Invoice** | Penagihan biaya langganan aplikasi tidak memotong kartu kredit otomatis. Sebaliknya, Asisten AI (Hermes) secara proaktif membuat tagihan (*Invoice*) dan mengirimkan *Link* Pembayaran (QRIS/VA) ke WA Bos pada H-3 sebelum masa tenggang. Begitu dibayar, masa aktif *Membership* otomatis bertambah. |
| D-24 | **Skema Routing Tunggal `/app/{module}/{path?}`** (terverifikasi dari kode 2026-09-16) | Seluruh halaman aplikasi berada di bawah route bernama `app.module` dengan URL `/app/{module}/{path?}`. Lobby App Switcher di `/`. Contoh: `/app/settings`, `/app/accounting/reports`. Tidak ada `/dashboard`, `/settings`, atau `/operations/*` di level root. Registry menu menyimpan **URL path**, bukan nama route Laravel per fitur. Dokumen lama yang memakai skema lain (INDUSTRY_PRESETS §2 versi awal, UX_UI_SPEC) telah dikoreksi. |
| D-25 | **Bentuk `module_settings` mengikuti D-19** | Satu baris per `(company_id, module_name)` dengan kolom `settings_json`. Feature flag disimpan di modul `features` sebagai `settings_json = {"crm.leads": true, ...}`. Tidak ada baris key-value per flag. |
| D-26 | **Kolom `company_id` wajib di SEMUA tabel bisnis** | Termasuk tabel anak seperti `accounting_journal_lines`, `crm_activity_logs`, `eo_vendors`. Tidak boleh mengandalkan join ke parent untuk isolasi tenant. |
| D-27 | **Konfirmasi 2-langkah memakai kode tiket** | Format balasan Bos adalah `YA <kode-tiket>`, bukan `YA` polos, agar tidak dapat diputar ulang (replay). Kode tiket = 4-6 digit yang dikirim sistem bersama Kartu Persetujuan. Menyelaraskan REQUIREMENTS §3.4 dan COMMERCIAL §4 dengan D-11. |
| D-28 | **Model AI tidak dibatasi paket** | Menegaskan D-15. Semua paket dapat memilih model apa pun dari katalog. Perbedaan hanya pada kecepatan habisnya token via `ai_model_pricings`. COMMERCIAL §2.2 tidak boleh dibaca sebagai pembatasan per paket. |
| D-29 | **Foto/lampiran mengikuti BYOS (D-22) tanpa kecuali** | `pharmacy_prescriptions.image_path` dihapus; foto resep disimpan sebagai baris `attachments` polimorfik. |
| D-30 | **Product naming canonical** | Produk front-facing: **NalarPesan**. Produk back-office: **Agentic BOS**. Istilah "ERP Prime" dan "ERP Nalarin" adalah nama warisan dan tidak dipakai di kode/UI baru. |
| D-31 | **Composable Capability Architecture — "industri = data, kapabilitas = kode"** (2026-09-16) | Target produk adalah **puluhan jenis bisnis** (≥50), bukan 6. Karena itu: (a) feature flag menyatakan **kapabilitas generik** lintas industri (`projects`, `bookings`, `inventory.batch_expiry`), **bukan** nama industri (`ops.contractor_spk`); (b) sebuah **preset industri adalah data** — satu baris `business_presets.definition` berisi kapabilitas aktif, terminologi, workflow default, dan susunan widget dashboard — bukan kode; (c) **alur bisnis** didefinisikan sebagai data di `workflow_definitions` dan dieksekusi satu `WorkflowEngine`; (d) **terminologi UI** diambil dari `terminology_map` preset/company via helper `term()`, tidak di-hardcode di Blade; (e) entitas yang variatif memakai kolom `attributes JSON` + `type`, dan tabel domain khusus **hanya** dibuat bila ada aturan bisnis yang tidak bisa direpresentasikan sebagai data (FEFO, retensi, double-entry, regulasi). Menambah industri baru **tidak boleh** memerlukan migration atau komponen Blade baru. Detail: `INDUSTRY_PRESETS.md` §0–§3, `DATA_MODEL.md` §1.5–1.7. |
| D-32 | **Katalog kapabilitas v1 dikunci (18 modul)** | `contacts`, `deals`, `projects`, `projects.progress_billing`, `scheduling`, `bookings`, `bookings.deposit`, `inventory`, `inventory.batch_expiry`, `inventory.bom`, `pos`, `pos.tables`, `quotations`, `milestone_billing`, `approval_flow`, `timesheet`, `finance.cashbook`, `finance.accounting`, `hr.employees`, `hr.payroll`, `system.ai_agent`. Menambah kapabilitas baru = keputusan arsitektur (butuh Bos), menambah preset baru = data (tidak butuh Bos). |
| D-33 | **Dua tier cakupan industri** | **Tier A (komposisi murni, target ≥80% jenis bisnis):** preset dibentuk hanya dari kapabilitas D-32 + terminologi + workflow + widget — nol kode. **Tier B (modul domain khusus):** industri dengan aturan yang tidak bisa jadi data (farmasi/obat keras, konstruksi/retensi-opname, manufaktur/BOM bertingkat) mendapat modul kode yang dibangun **sekali** dan dipakai industri serumpun. 6 preset awal: Agency, F&B, EO, Persewaan = Tier A; Apotek, Kontraktor = Tier A + 1 modul Tier B masing-masing. |

---

## Keputusan OPEN

Keputusan berikut **belum diputuskan Bos** dan memblokir task tertentu. Agent
autopilot **tidak boleh menebak** item ini. Bila task bergantung pada item OPEN,
tandai task `BLOCKED` dan lanjutkan task lain yang independen. Setiap item
menyertakan **rekomendasi default** agar Bos cukup menjawab "setuju" atau memilih.

| ID | Pertanyaan | Memblokir | Rekomendasi default |
|---|---|---|---|
| Q-01 | Payment gateway: **Midtrans** atau **Xendit**? Menentukan algoritma signature webhook, format `order_id`, dan vocabulary status. | T-17, T-18, T-19 | **Midtrans.** Signature = `SHA512(order_id + status_code + gross_amount + ServerKey)`, header/body standar Midtrans notification. Env: `MIDTRANS_SERVER_KEY`, `MIDTRANS_IS_PRODUCTION`. |
| Q-02 | Driver Laravel Scout: **`database`** (nol infra, cukup untuk 500 tenant awal) atau **Meilisearch** (butuh service tambahan)? | T-20 | **`database`** untuk fase ini. Migrasi ke Meilisearch bila terbukti lambat. |
| Q-03 | Palet warna & nilai hex untuk 5 tema (`Prime Default`, `Clean Ledger`, `Ocean Blue`, `Brass Amber`, `Rose`) dan daftar lengkap 36 token `--erp-*`. | T-07, semua UI setelahnya | Agent boleh menurunkan palet dari Tailwind v4 default (slate/emerald/sky/amber/rose) **asal** setiap pasangan teks/latar lolos WCAG AA ≥ 4.5:1 dan dibuktikan dengan test unit penghitung kontras. Daftar 36 token didefinisikan di UX_UI_SPEC §7 (ditambahkan 2026-09-16). |
| Q-04 | `hermes_profiles`: **satu per company** (PRD/DATA_MODEL) atau **satu per owner yang memiliki banyak company** (COMMERCIAL §3.2)? Menentukan `UNIQUE(company_id)`. | T-10b, provisioning | **Satu per company.** Owner multi-company mendapat beberapa profile; lebih sederhana untuk isolasi dan kuota. COMMERCIAL §3.2 akan dikoreksi setelah Bos setuju. |
| Q-05 | Nama tahap CRM default untuk preset Agency: `Lead Baru → Pitch/SPH → Negosiasi → Won/Lost` (REQUIREMENTS) vs `new → in_progress → won → lost` (DATA_MODEL). | T-13 | Simpan **kode** `new/qualified/proposal/negotiation/won/lost` di kolom `stage` (bahasa netral), tampilkan **label** Indonesia di UI. Stage custom per company via `module_settings`. |
| Q-06 | Daftar vendor string yang harus nol di UI untuk audit white-label T-22. | T-22 | `Hermes`, `Nous`, `Nous Research`, `Laravel` (di UI tenant, bukan di source), `Midtrans` (di UI tenant), `laravel/laravel` (di `composer.json name`). |
| Q-07 | Mekanisme wawancara onboarding (U-01): form web, AI WA, atau human call sebagai default fase ini? | Onboarding flow | **Form web sederhana** dulu; AI WA interview menyusul setelah Hermes node API tersedia. |
| Q-08 | Konteks tenant aktif: `users.current_company_id` (kolom) vs session vs subdomain? | T-15, T-16, T-20 | **Kolom `users.current_company_id`** + middleware yang membacanya, karena D-21 memakai login tradisional dan user bisa punya banyak company. |

Semua keputusan arsitektur utama selain Q-01..Q-08 telah LOCKED.

---

## Peta Dokumen PRD (`D:\PROJECTS\agentic-bos\docs\`)

1. `00-DECISIONS.md` — dokumen ini (konsolidasi semua keputusan; tie-breaker)
2. `PRD.md` — ringkasan eksekutif, visi, pilar
3. `INDUSTRY_PRESETS.md` — matriks 6 preset + feature flags + menu dinamis
4. `DATA_MODEL.md` — skema tabel canonical + kontrak migration portabel
5. `REQUIREMENTS.md` — spesifikasi fungsional per modul (termasuk formula pajak)
6. `COMMERCIAL_AND_AI_AGENTIC_SPEC.md` — membership dinamis, arsitektur 500 klien, white-label AI
7. `UX_UI_SPEC.md` — filosofi UI, onboarding, tab-based settings, dashboard adaptif, design token
8. `EXECUTION_PLAN.md` — task queue Fase 0–5, dependency graph, definisi READY
9. `PRD_RECONCILIATION.md` — log audit konflik dan resolusinya
10. `AUTOPILOT_STATUS.md` — state pekerjaan aktual untuk resume agent
