# PRD: Agentic Business Operating System (BOS)
## ERP Nalarin Multi-Business Multi-Tenant Platform

**Versi:** 1.0.1-PROD  
**Status:** APPROVED FOR AUTONOMOUS EXECUTION  
**Target Codebase:** `D:\PROJECTS\agentic-bos` (Laravel 13 / PHP 8.3 / Livewire v4 / Tailwind v4 / Vite; dev & test SQLite, produksi MySQL/MariaDB)  
**Tujuan Dokumen:** Ringkasan visi dan pilar produk untuk Autonomous AI Agents. Bila dokumen ini bertentangan dengan `00-DECISIONS.md`, **`00-DECISIONS.md` yang berlaku**.

---

## 1. Executive Summary & Visi Produk

**Agentic Business Operating System (BOS)** bukan sekadar ERP tradisional. Ini adalah *Sistem Operasional Hybrid (Web & WhatsApp)* dengan model bisnis **SaaS White-label Hermes** yang menyatukan:
1. **WhatsApp-First AI Agent (Asisten Bos):** Interaksi utama dan eksekusi harian dilakukan via WhatsApp. Bot WA dimasukkan ke dalam grup perusahaan (Keuangan, Produksi, HRD) untuk mengelola operasional secara otomatis berbasis peran (role-based).
2. **SaaS Hermes White-label & Token Economics:** Setiap perusahaan (tenant) akan mendapatkan 1 profile Hermes yang dikelola terpusat oleh BOS secara otomatis via API. Penggunaan fitur Agentic menggunakan sistem saldo *Token* yang dapat di-*topup* atau berlangganan.
3. **Dashboard Web Pendukung (Zero-Bloat):** Web UI minimalis berfungsi sebagai tempat setup konfigurasi, scan QR WhatsApp, pemantauan saldo token, manajemen *role* staf, dan eksekusi operasional yang terlalu kompleks untuk chat.
4. **Indonesian Tax & Regulatory Compliance:** Fleksibilitas penuh pembukuan pajak (taxable PKP vs non-taxable, tax-inclusive vs tax-exclusive).

---

## 2. Empat Pilar Arsitektur Inti

### Pilar 1: Hybrid Interface & Role-Based WhatsApp Group Management
- Bot WA (Asisten Bos) dimasukkan ke dalam grup-grup perusahaan (Keuangan, Produksi, HRD).
- Otorisasi dan pemberian perintah di grup WA dikenali dengan cara **mencocokkan nomor WhatsApp staf** yang terdaftar beserta *role*-nya di Web UI BOS.
- Bot hanya merespons perintah dari nomor yang terdaftar di database.

### Pilar 2: Hermes Integration & Token Economics
- BOS bertindak sebagai *White-label Wrapper* atas platform Hermes.
- Platform BOS secara otomatis mem-*provisioning* (via API) 1 profile/instance Hermes untuk setiap perusahaan (tenant).
- Proses login/scan QR WA dilakukan dengan aman di dalam Dashboard Web BOS.
- Setiap aktivitas/percakapan Agentic WA akan memotong saldo **Token** perusahaan yang dimanage secara tersentralisasi oleh sistem BOS.

### Pilar 3: Composable Capability & Zero-Bloat UI (D-31)
- Produk menargetkan **puluhan jenis bisnis**, bukan 6. Karena itu **industri adalah data, kapabilitas adalah kode**: ~20 modul kapabilitas generik (`contacts`, `projects`, `bookings`, `inventory`, `pos`, `finance.*`, `hr.*`, …) dikomposisi oleh **preset industri** yang berupa satu dokumen JSON (kapabilitas aktif + terminologi + alur/workflow + susunan dashboard).
- Menambah jenis bisnis baru = menambah satu file preset, **tanpa migration dan tanpa komponen UI baru**. Hanya ~20% industri dengan aturan domain unik (farmasi, konstruksi) memerlukan modul Tier B yang dibangun sekali untuk industri serumpun (D-33).
- Antarmuka web minimalis; menu, istilah UI (`term()`), alur (`WorkflowEngine`), dan widget dashboard semuanya mengikuti preset + override per company (`module_settings`).
- Item menu diuji terhadap `company->feature($capability)`. Jika kapabilitas mati, menu **hilang total dari DOM**.

### Pilar 4: Dual-Mode Tax & Financial Book
- Pemisahan dimensi: `Company` (Workspace) → `BusinessIdentity` (Brand/Kop/NPWP/**konfigurasi pajak dasar**).
- Sesuai D-03 dan D-17: satu mode pajak dasar per `BusinessIdentity` (`taxable`/`non_taxable`, `price_includes_tax`). Override per item/transaksi dan multi-rate hanya sebagai add-on Enterprise.

---

## 3. Batasan Produk (Non-Goals / Boundaries)

Untuk menjaga eksekusi agent tetap fokus dan selesai, fitur berikut **DILARANG / OUT OF SCOPE** untuk fase ini:
1. **(Dihapus)** *Aturan Dilarang Payroll dihapus berdasarkan revisi kebutuhan klien Kontraktor/Agensi (Membuka modul Gaji).*
2. **Dilarang Penggunaan Provider Hermes Mandiri (BYOH):** "Bring Your Own Hermes" dilarang. Semua koneksi agen WA klien harus dipusatkan melalui akun API Hermes terpusat milik platform BOS (White-label), dan dikendalikan saldo tokennya.
3. **Dilarang Direct Core Upstream Fork:** Jangan memodifikasi vendor framework atau package eksternal.
4. **Dilarang Auto-Journal Liar:** Semua jurnal akuntansi harus memiliki pemicu dokumen operasional yang sah.
5. **Arsitektur Tenant Hybrid Wajib:** Klien SaaS reguler memakai database bersama dengan isolasi `company_id` yang fail-closed. Klien Enterprise dapat memakai database dan node Hermes dedicated melalui `TenantProvisioner`. Jangan mencampur data tenant atau mengubah jalur tenancy tanpa migration, authorization, dan test isolasi yang eksplisit.

---

## 4. Struktur Dokumen PRD Pendukung

Agent pelaksana wajib membaca dokumen pendukung di folder `docs/` ini. Urutan prioritas bila terjadi konflik: **`00-DECISIONS.md` > `DATA_MODEL.md` (untuk bentuk data) > dokumen lain**.

1. `00-DECISIONS.md` — **tie-breaker.** Semua keputusan LOCKED dan OPEN.
2. `INDUSTRY_PRESETS.md` — Katalog kapabilitas (D-32), skema preset-sebagai-data, kamus terminologi, katalog widget & efek workflow, 6 preset awal + peta pasar 63 bisnis potensial (§7) dengan prioritas go-to-market.
3. `DATA_MODEL.md` — Skema database canonical: fondasi tenant, mesin komposisi (`workflow_definitions`), tabel kapabilitas generik, modul Tier B, kontrak migration portabel.
4. `REQUIREMENTS.md` — Spesifikasi fungsional mendalam per modul.
5. `UX_UI_SPEC.md` — Kontrak antarmuka, aksesibilitas, dan design token.
6. `COMMERCIAL_AND_AI_AGENTIC_SPEC.md` — Membership, token economics, white-label AI.
7. `EXECUTION_PLAN.md` — Task queue bertahap, dependency graph, dan definisi READY.
8. `PRD_RECONCILIATION.md` — Log audit konflik dan resolusinya.
9. `AUTOPILOT_STATUS.md` — State pekerjaan aktual yang dibaca agent saat resume.
