# EXECUTION PLAN & TASK QUEUE
## Panduan Autopilot Terkendali untuk Autonomous AI Agent

Status PRD dapat mengizinkan eksekusi otonom, tetapi tidak menggantikan
verifikasi target repository, konflik kontrak, atau approval berisiko. Agent
pelaksana wajib membaca seluruh dokumen PRD pendukung dan menyelesaikan Fase 0
sebelum menulis source code. `00-DECISIONS.md` mengalahkan dokumen lain bila
terjadi konflik.

## Fase 0: Discovery, Rekonsiliasi, dan Review Gate

| ID | Task | Bukti Wajib | Status Keluar |
|---|---|---|---|
| G-01 | Verifikasi canonical repository, branch, worktree, remote, dan baseline test | `git status`, `git remote -v`, commit HEAD, struktur aplikasi, toolchain | Target repo disahkan atau BLOCKED |
| G-02 | Rekonsiliasi PRD, keputusan locked, requirements, data model, preset, dan UX dengan codebase aktual | `PRD_RECONCILIATION.md`, daftar konflik, keputusan yang berlaku, file/route/tabel existing, gap terdeduplikasi | Tidak ada konflik implementasi yang belum diputuskan |
| G-03 | Pecah scope menjadi task atomik beserta dependency, file scope, invariants, acceptance criteria, dan test plan | Task queue yang dapat diverifikasi | Hanya task `READY` yang boleh ditulis |
| G-04 | Review arsitektur, tenant isolation, authorization bot, token/billing, dan risiko migrasi | Finding berbukti atau verdict no-finding | Risiko approval-gated dipisahkan dari task coding |

**G-01 resolved (2026-09-16):** repository canonical adalah
`D:\PROJECTS\agentic-bos`. Path ini berisi aplikasi Laravel aktif (`artisan`,
`composer.json`, `app/`, `routes/`, dan `tests/`). Semua task harus berjalan
dari root tersebut dan tetap memverifikasi branch, worktree, remote, serta
baseline test sebelum writer dimulai.

**Baseline terverifikasi (2026-09-16):** Laravel 13.32.0, PHP 8.3.30,
Livewire 4.4, Tailwind 4.3, `php artisan test` → **2 passed**. Detail dan bukti
ada di `PRD_RECONCILIATION.md`.

### Blocker Aktif Sebelum Fase 3

| ID | Blocker | Dampak | Butuh |
|---|---|---|---|
| B-01 | `.env` memakai SQLite, sedangkan `DATA_MODEL.md` memakai DDL MySQL (`ENUM`, `AUTO_INCREMENT`) | Semua migration Fase 3 akan gagal bila ditulis sebagai DDL MySQL mentah | **TERSELESAIKAN 2026-09-16.** Semua migration wajib memakai Laravel Schema Builder portabel; lihat `DATA_MODEL.md` §0. Dev/test SQLite, paritas MySQL diverifikasi sebelum release |
| B-02 | Workspace bukan Git repository (`.git` tidak ada) | Tidak ada `git diff`, tidak ada baseline SHA, tidak ada gate commit, writer paralel via worktree tidak mungkin | **TERSELESAIKAN 2026-09-16.** `git init` dijalankan, baseline commit `7151104` dibuat, `.gitignore` diverifikasi mengecualikan `.env`/`vendor`/`node_modules`/`auth.json` |

Fase 1 dan 2 (UI) **tidak** diblokir oleh B-01 dan boleh dilanjutkan.


## Strategi Eksekusi

Strategi frontend-first adalah hipotesis delivery, bukan izin untuk membuat
dummy UI yang mengunci kontrak data keliru. Setiap task UI harus terlebih dahulu
memastikan route, feature flag, state, dan kontrak aksesibilitasnya; setiap task
backend harus memastikan tabel/model/guard yang sudah ada agar tidak membuat
duplikasi.

---

## Fase 1: Kerangka Inti Navigasi & UI (Livewire v4 — SEBAGIAN SUDAH ADA)

> Diverifikasi 2026-09-16 terhadap kode nyata. Jangan menulis ulang komponen
> yang sudah berjalan. Lihat `PRD_RECONCILIATION.md` untuk bukti.

| ID | Task | File Target | Status Aktual & Acceptance Criteria |
|---|---|---|---|
| T-01 | Setup Laravel, Livewire v4, dan TailwindCSS v4 | `package.json`, `composer.json` | **SELESAI.** Laravel 13.32, Livewire 4.4, Tailwind 4.3, Vite 8. Build tersedia. |
| T-02 | Komponen UI: *Lobby* & *App Switcher* | `app/Livewire/Lobby.php`, `resources/views/livewire/lobby.blade.php` | **SELESAI 2026-09-16.** Setiap kartu menautkan `route('app.module', slug)` dengan `wire:navigate`, key `route` yang dead payload diganti `slug`, ditambah `aria-label` dan focus ring. Bukti: `tests/Feature/LobbyNavigationTest.php` (4 test, termasuk larangan `href="#"`). |
| T-03 | Komponen UI: *Dynamic Sidebar* (Terisolasi) | `app/Services/DynamicMenuRegistry.php`, `app/Livewire/Sidebar.php`, `resources/views/livewire/sidebar.blade.php` | **SELESAI 2026-09-16.** Menu dipindahkan ke `DynamicMenuRegistry` untuk 6 modul, aksen warna per modul, dan modul tak dikenal kini merender nol item menu (zero-bloat) alih-alih placeholder. Bukti: `tests/Unit/DynamicMenuRegistryTest.php` (7 test) + `tests/Feature/ModuleSidebarTest.php` (4 test). |
| T-04 | Komponen UI: *Universal Search (Ctrl+K)* | `app/Livewire/CommandPalette.php`, `resources/views/livewire/command-palette.blade.php` | **SELESAI (dummy).** Modal Alpine + `wire:model.live.debounce` merender hasil dummy. Integrasi Scout ditangani T-20. |

> Catatan runtime: Alpine.js **tidak** dipasang terpisah; ia dibundel Livewire v4.
> Menambahkan paket `alpinejs` akan menyebabkan dua instans Alpine dan merusak
> reaktivitas Livewire.

## Fase 2: Tampilan Halaman Operasional (Mobile-First)

| ID | Task | File Target | Acceptance Criteria |
|---|---|---|---|
| T-05 | UI: Dashboard *Midnight Command* | `app/Livewire/`, `resources/views/livewire/` | Tampilan tema gelap elegan, terdapat grafik dummy dan kotak laporan AI. |
| T-06 | UI: Tabel Operasional (Card Layout di HP) | `resources/views/components/` | Membangun struktur tabel (misal Jurnal Keuangan) yang otomatis berubah menjadi kotak tumpuk (*Card Layout*) jika dibuka di HP. |
| T-07 | UI: *Settings Tab* & *Theme Toggle* | `app/Livewire/`, `resources/css/app.css` | Tab navigasi mulus tanpa reload penuh sesuai kontrak WAI-ARIA di `UX_UI_SPEC.md`. Token `--erp-*` dideklarasikan dalam blok `@theme` Tailwind v4. |

## Fase 3: Fondasi Database & Migrasi (Backend)

*Hanya dieksekusi setelah Fase 1 & 2 disetujui (LOCKED) oleh Bos.*

| ID | Task | File Target | Acceptance Criteria |
|---|---|---|---|
| T-08 | Buat model `BusinessPreset` + seeder 6 preset | `app/Models/BusinessPreset.php`, `database/seeders/*.php` | Seeder jalan. 6 preset + custom terdaftar di DB. |
| T-09 | Tambah kolom `business_preset` ke tabel `companies` | migration | Kolom enum default `custom`. Migration berjalan bersih. |
| T-10 | Migration `membership_plans` dan `company_memberships` | migration | Membership canonical, quota, dan cache saldo terbentuk dengan isolasi tenant. |
| T-10a | Migration `token_ledger_entries` | migration | Kredit/debit token auditabel, idempotency key unik, dan relasi membership valid. |
| T-10b | Migration `hermes_nodes` dan `hermes_profiles` | migration | Registry node/profile white-label terbentuk tanpa menyimpan secret plaintext. |
| T-11 | Migration Modul Keuangan Inti (Double-Entry) | migration | Pembuatan tabel `chart_of_accounts`, `accounting_journals`, dan `accounting_journal_lines`. |
| T-12 | Migration tabel `invoices` | migration | Tabel pelacakan tagihan Top-up Token dan Subscription bulanan dengan relasi `company_id`, `order_id` unik, dan state payment canonical. |
| T-13 | Migration Refactoring CRM (Drop `crm_leads`, buat `crm_contacts`, `crm_deals`) | migration | Arsitektur Hubspot/Pipedrive style berhasil terbangun. |
| T-14 | Migration `attachments` (Google Drive BYOS) | migration | Pembuatan tabel lampiran polimorfik tersentralisasi untuk menyimpan *link* Cloud Storage. |

## Fase 4: Model, Middleware, dan Arsitektur AI (Hermes)

| ID | Task | File Target | Acceptance Criteria |
|---|---|---|---|
| T-15 | Implementasikan `Company::feature()` + `hasAnyFeature()` | `app/Models/Company.php` | Test isolasi tenant. Override tenant A tidak bocor ke tenant B. |
| T-16 | Middleware `EnsureFeatureEnabled` & Middleware `CompanyId` Guard | `app/Http/Middleware/` | Test 403 saat fitur off, 200 saat on. Penjagaan lintas tenant. |
| T-17 | Pembuatan API Tool MCP khusus "Master Bot" (BOS Care) | `app/Http/Controllers/Api/` | Pembuatan endpoint pembuatan tiket, cek token, dan tagihan otomatis khusus untuk Master Bot platform. |
| T-18 | Cron Job Penagihan SaaS Otomatis | `app/Console/Commands/` | Command `billing:check-expiring` untuk mengecek Klien H-3 *expired*, cetak Invoice, dan kirim pesan WA Link Pembayaran via mesin Hermes. |
| T-19 | Webhook Controller Payment Gateway (Midtrans) | `app/Http/Controllers/Api/` | Validasi signature, tenant/invoice matching, idempotency event, state transition valid, lalu update invoice, token ledger, dan cache saldo dalam satu transaksi. Replay event tidak boleh mengubah saldo dua kali. |
| T-20 | Integrasi Laravel Scout & Universal Search (Backend) | `app/Models/` | Sambungkan UI `Ctrl+K` dengan mesin Scout sesungguhnya. |

## Fase 5: UAT, QA, dan Peluncuran Bertahap

| ID | Task | Acceptance Criteria |
|---|---|---|
| T-21 | Full regression suite green (semua unit & feature test hijau) | Seluruh test hijau. |
| T-22 | Audit white-label & tenant isolation (grep vendor string, test cross-tenant) | 0 leak, 0 vendor string di UI. |
| T-23 | Build production + smoke test live per tenant | `npm run build` + HTTP 200 untuk 6 preset tenant dogfood. |

---

## Aturan Eksekusi Autopilot Wajib
1. **Satu writer per worktree.** Jangan commit di worktree milik agent lain.
2. **Review sebelum tulis.** Untuk setiap task `READY`, baca requirement dan keputusan terkait, audit implementasi existing, tetapkan scope/invariant/negative case, lalu lakukan review mandiri atas diff sebelum task ditutup.
3. **Paralel hanya untuk lane read-only dan lane terisolasi.** Audit PRD, pencarian codebase, threat modeling, review UX/a11y, lint/build, dan test yang tidak berbagi state boleh paralel. Writer source, migration, dependency, dan deployment wajib serial. Catatan terverifikasi: `phpunit.xml` memakai SQLite `:memory:` yang terisolasi per proses, sehingga shard test paralel aman selama tidak menulis ke file/direktori output yang sama.
4. **Rekonsiliasi setelah paralel.** Hasil subagent bukan verdict final. Lead wajib memeriksa evidence, worktree aktual, dan konflik sebelum task writer berikutnya dimulai.
5. **TDD sesuai kondisi nyata.** Bug yang terbukti harus RED lalu GREEN. Bila audit membuktikan perilaku sudah benar, gunakan characterization test dan catat sebagai no-change, bukan membuat RED palsu.
6. **Selalu `git log --oneline -3`** sebelum commit untuk deteksi race. Jangan commit/push/deploy/migrate tanpa approval eksplisit yang relevan.
7. **Laporan berkala** setiap fase selesai, sertakan daftar task, file, verdict review independen, perintah aktual, hasil test/build, risiko, blocker, dan next task. HTTP status atau test count saja bukan bukti keseluruhan.
