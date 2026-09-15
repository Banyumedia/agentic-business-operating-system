# DECISIONS LOG — KONSOLIDASI DISKUSI PRD AGENTIC BOS
## Single Source of Truth Seluruh Keputusan Diskusi

**Tanggal konsolidasi:** 2026-09-12
**Sumber:** Diskusi PRD session ini + `_docs/modules/bos-*.md` (pricing, WA tiering, infra, modul 14, modul 15)

Status: `LOCKED` = keputusan final Bos. `OPEN` = menunggu diskusi lanjutan.

---

## Keputusan LOCKED

| ID | Keputusan | Detail |
|---|---|---|
| D-01 | **6 Preset bisnis awal** | Agency, F&B, Apotek, Event Organizer, Kontraktor, Persewaan. Langsung 6 preset, onboarding 1-klik + override per company. Matriks flag di `INDUSTRY_PRESETS.md`. |
| D-02 | **Model config = 1B (Preset + Override)** | Bukan toggle manual murni. Company pilih preset industri → default flags load → bisa override via `module_settings` (Pengaturan > Fitur Bisnis). |
| D-03 | **Tax mode per Business Identity** | Semua identity bisa pilih: jenis buku `taxable` / `non_taxable`. Jika taxable → pilihan `price_includes_tax` (harga sudah termasuk PPN atau belum). Ini adalah konfigurasi dasar semua transaksi; formula DPP inclusive = total / 1.11. Di `REQUIREMENTS.md` §1. |
| D-04 | **NalarPesan ≠ Agentic BOS — dua layanan berbeda** | NalarPesan = Mini POS front-facing, tujuan utama memudahkan klien order + pemilik kelola bisnis, nilai plus CRM WhatsApp (pesan via WA; opsi channel lain tetap ada). BOS = back-office ERP + Karyawan AI. Terhubung via webhook bridge idempotent. |
| D-05 | **Membership & pricing DINAMIS, tidak hardcode** | Harga, jumlah paket, kuota ditentukan belakangan via riset pasar. Simulasi 3 tier di `COMMERCIAL_AND_AI_AGENTIC_SPEC.md` hanya placeholder, edit manual via Super Admin. Struktur canonical: `membership_plans`, `company_memberships`, dan `token_ledger_entries`. |
| D-06 | **Arsitektur Multi-Node dan Tenant Hybrid untuk 500+ klien** | SaaS reguler memakai database bersama dengan isolasi `company_id` yang fail-closed; Enterprise dapat memakai database/VPS dedicated melalui `TenantProvisioner`. 1 Node Hermes (Process Manager) maksimal memegang ~100 sesi Klien. Laravel menyimpan *Node Registry* dan melakukan *Load Balancing* ke Node yang masih kosong. (Mencegah Single Point of Failure akibat *Memory Leak*). |
| D-07 | **Model AI per-tenant, masing-masing pilih sendiri** | Tenant dapat memilih model/provider sendiri dari katalog yang disediakan platform. |
| D-08 | **Topup token otomatis via QRIS** | Pembayaran QRIS → webhook callback (gateway payment) → kuota token ter-update otomatis tanpa konfirmasi admin. Align Modul 15 (webhook HMAC fail-closed sudah live; production perlu set `PAYMENT_WEBHOOK_SECRET`). |
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
| D-20 | **UX Visual Redesign: Midnight Command + Operator Grid Hybrid** | Varian prototype terpilih: **A (Midnight Command)** untuk `/dashboard` & shell eksekutif (dark-first, rail sidebar ikon, command palette `Ctrl K`, omzet hero, KPI sparkline, feed agentic); **C (Operator Grid)** untuk halaman operasional `/operations/*` (telemetri kas/kuota/sync di topbar, tabel padat, log live, monospace angka). Sprint terisolasi di `project-control/sprint-ux-visual-overhaul/`. |
| D-21 | **Autentikasi Tradisional (Web Login)** | Menggunakan Email dan Password standar untuk akses masuk ke aplikasi Web (SaaS). WhatsApp secara eksklusif hanya digunakan sebagai saluran komunikasi asisten AI (Hermes), bukan sebagai gerbang otorisasi Web. |
| D-22 | **Bring Your Own Storage (BYOS) via Google Drive** | Semua lampiran fisik (*file* foto, nota, kontrak) di-upload dan disimpan di Google Drive milik Klien melalui integrasi API. Sistem SaaS hanya menyimpan *URL link* ke dalam satu tabel *Polymorphic* bernama `attachments`. Menghemat biaya *server storage* secara drastis. |
| D-23 | **SaaS Billing via Proactive WA Invoice** | Penagihan biaya langganan aplikasi tidak memotong kartu kredit otomatis. Sebaliknya, Asisten AI (Hermes) secara proaktif membuat tagihan (*Invoice*) dan mengirimkan *Link* Pembayaran (QRIS/VA) ke WA Bos pada H-3 sebelum masa tenggang. Begitu dibayar, masa aktif *Membership* otomatis bertambah. |

---

## Keputusan OPEN

*Semua keputusan arsitektur utama telah disahkan (LOCKED) oleh Bos.*

---

## Peta Dokumen PRD (`project-control/operating-system-prd/`)

1. `00-DECISIONS.md` — dokumen ini (konsolidasi semua keputusan)
2. `PRD.md` — ringkasan eksekutif, visi, pilar
3. `INDUSTRY_PRESETS.md` — matriks 6 preset + feature flags + menu dinamis
4. `DATA_MODEL.md` — skema tabel (clean canonical)
5. `REQUIREMENTS.md` — spesifikasi fungsional per modul (termasuk formula pajak)
6. `COMMERCIAL_AND_AI_AGENTIC_SPEC.md` — membership dinamis, arsitektur 500 klien, white-label AI
7. `UX_UI_SPEC.md` — filosofi UI, onboarding pasca-wawancara, tab-based settings, dashboard adaptif per industri
8. `EXECUTION_PLAN.md` — task queue fase 1–6 untuk autonomous agent
