# EXECUTION PLAN & TASK QUEUE
## Panduan Autopilot Terkendali untuk Autonomous AI Agent

Status PRD mengizinkan eksekusi otonom, tetapi tidak menggantikan verifikasi
kontrak, dependency, atau approval berisiko. `00-DECISIONS.md` mengalahkan dokumen
lain bila terjadi konflik. State pekerjaan aktual selalu dibaca dari
`AUTOPILOT_STATUS.md`, bukan dari ingatan sesi.

---

## 0. Kontrak State Task (dibaca mesin)

Setiap task memiliki tepat satu state. Agent **hanya boleh menulis source** pada
task berstatus `READY`.

| State | Definisi mekanis |
|---|---|
| `DONE` | Semua acceptance criteria terbukti dengan output command nyata; evidence tercatat di `AUTOPILOT_STATUS.md`. |
| `READY` | **Semua** syarat berikut terpenuhi: (1) semua task di kolom *Depends On* berstatus `DONE`; (2) tidak ada keputusan `Q-xx` OPEN di kolom *Decisions*; (3) tidak butuh secret/service eksternal, **atau** mode fake/stub diizinkan secara eksplisit; (4) tidak melewati gate `HUMAN` yang belum diberikan; (5) acceptance criteria dapat dibuktikan dengan command di §0.2. |
| `BLOCKED` | Salah satu syarat READY gagal. Wajib mencatat **syarat mana** yang gagal. |
| `PARTIAL` | Sudah mulai dikerjakan, sebagian acceptance terbukti, sisanya belum. Tidak boleh ditinggalkan tanpa catatan di `AUTOPILOT_STATUS.md`. |

Agent **tidak boleh** mengubah state `BLOCKED` menjadi `READY` dengan menebak
jawaban keputusan OPEN. Ia harus melompat ke task `READY` lain yang independen.

### 0.1 Gate

| Gate | Arti | Siapa yang membuka |
|---|---|---|
| `HUMAN:UI-LOCK` | Bos menyetujui hasil visual Fase 1–2 sebelum backend dimulai. | Bos |
| `HUMAN:SECRET` | Butuh kredensial nyata (payment gateway, Hermes node, Google OAuth). Dev boleh memakai **fake/stub** yang ditandai jelas. | Bos |
| `HUMAN:DEPLOY` | Semua deploy/production. | Bos |
| `HUMAN:COMMIT` | Commit lokal. **Sudah dibuka** untuk seluruh pekerjaan lokal di repo ini (2026-09-16). Push tetap butuh approval terpisah. | Bos ✅ |

### 0.2 Command Verifikasi per Tipe Task

Semua path relatif ke `D:\PROJECTS\agentic-bos`. Agent wajib menjalankan **semua**
command untuk tipe task yang relevan dan menempelkan hasilnya sebagai evidence.

| Tipe | Command wajib | Tambahan bila relevan |
|---|---|---|
| **UI** (Livewire/Blade/CSS) | `php artisan test --filter=<TestClass>` → `php artisan test` → `vendor/bin/pint --test` → `npm run build` | Assert DOM: `role`, `aria-*`, tidak ada `href="#"`; assert token `--erp-*` dipakai bila UI baru. |
| **Migration/Model** | `php artisan migrate:fresh --seed` (SQLite) → `php artisan test` → `vendor/bin/pint --test` | Test isolasi tenant A/B untuk setiap tabel baru (`company_id`). |
| **Service/Middleware** | `php artisan test --filter=<TestClass>` → `php artisan test` → `vendor/bin/pint --test` | Test negatif (403/422/replay) wajib untuk auth, payment, saldo. |
| **API/Webhook** | Sama dengan Service + test signature invalid → 401/403, replay → no-op | Fake gateway di test; **jangan** panggil endpoint nyata. |
| **Docs-only** | `git diff --check` | Tidak perlu test runtime; catat eksplisit. |

Pelanggaran Pint pada file yang **disentuh** task wajib diperbaiki dalam task
yang sama. Pelanggaran di file lain dicatat, tidak diperbaiki diam-diam.

### 0.3 Kebijakan Gagal

1. Percobaan yang sama maksimal **2×**. Percobaan ketiga wajib mengubah strategi
   atau menandai task `BLOCKED` dengan error persis.
2. Migration yang gagal di tengah: jalankan `php artisan migrate:fresh` pada
   SQLite dev (aman, disposable) sebelum mencoba lagi. **Jangan** pernah
   `migrate:fresh` di database non-dev.
3. Bila satu task `BLOCKED`, lanjutkan task `READY` lain yang **tidak** berbagi
   file target dengan task yang gagal.
4. Setiap perubahan state task → update `AUTOPILOT_STATUS.md` **sebelum** pindah.

---

## Fase 0: Discovery, Rekonsiliasi, dan Review Gate — DONE

| ID | Task | Status | Evidence |
|---|---|---|---|
| G-01 | Verifikasi canonical repository, branch, worktree, baseline test | `DONE` | `D:\PROJECTS\agentic-bos`, branch `main`, Git initialized, **remote belum dikonfigurasi** (push memerlukan remote + approval). |
| G-02 | Rekonsiliasi PRD ↔ codebase | `DONE` | `PRD_RECONCILIATION.md` |
| G-03 | Pecah scope menjadi task atomik dengan dependency | `DONE` | Dokumen ini (revisi 2026-09-16) |
| G-04 | Review arsitektur, tenant isolation, billing, migrasi | `DONE` | Keputusan D-24..D-30 + Q-01..Q-08 di `00-DECISIONS.md` |

**Baseline saat ini** (bukan baseline awal): lihat `AUTOPILOT_STATUS.md` §Verified
Baseline. Baseline **awal** repo adalah 2 test; angka itu historis dan tidak
dipakai untuk regresi.

### Blocker yang Sudah Terselesaikan

| ID | Resolusi |
|---|---|
| B-01 | Migration wajib Laravel Schema Builder portabel (`DATA_MODEL.md` §0). Dev/test SQLite; paritas MySQL diverifikasi sebelum release via **T-21b**. |
| B-02 | `git init` selesai; `.gitignore` diverifikasi. Baseline `7151104`. |

---

## Fase 1: Kerangka Inti Navigasi & UI — DONE

> Dibangun dengan **Livewire v4** (terverifikasi dari `composer.json`). Alpine
> dibundel Livewire; **jangan** tambahkan paket `alpinejs`.

| ID | Task | Depends On | State | Evidence |
|---|---|---|---|---|
| T-01 | Setup Laravel 13 + Livewire 4 + Tailwind 4 + Vite | — | `DONE` | `composer.json`, `package.json`, `npm run build` sukses |
| T-02 | Lobby & App Switcher | T-01 | `DONE` | `tests/Feature/LobbyNavigationTest.php` (4 test). Kartu → `route('app.module', slug)`, tombol search punya handler + `aria-label`. |
| T-03 | Dynamic Sidebar (katalog statis) | T-01 | `DONE` | `tests/Unit/DynamicMenuRegistryTest.php` (7) + `tests/Feature/ModuleSidebarTest.php` (4). Modul tak dikenal → **nol DOM item** (zero-bloat). |
| T-04 | Universal Search `Ctrl+K` (dummy) | T-01 | `DONE (dummy)` | Modal Alpine + `wire:model.live`. Hasil dummy masih `href="#"` — diselesaikan T-20. |

> **Catatan jujur tentang T-03:** registry saat ini **statis** (`slug => items`)
> tanpa `Company`/flag. Ini sudah cukup untuk acceptance Fase 1 ("menu berganti
> per modul"). Bentuk flag-aware sesuai `INDUSTRY_PRESETS.md` §2.1 dikerjakan di
> **T-03b** setelah T-15.

---

## Fase 2: Tampilan Halaman Operasional (Mobile-First)

| ID | Task | Depends On | Decisions | Gate | File Target | Acceptance (dapat diuji) | State |
|---|---|---|---|---|---|---|---|
| T-07 | Design token `--erp-*` + Settings tab + theme toggle | T-03 | Q-03 (**dijawab default**: turunkan dari palet Tailwind v4, buktikan AA dengan test) | — | `resources/css/app.css`, `app/Livewire/Settings.php`, `resources/views/livewire/settings.blade.php`, `app/Services/ThemeRegistry.php` | (a) `app.css` `@theme` mendeklarasikan **36 token** sesuai `UX_UI_SPEC.md` §7; (b) `tests/Unit/ThemeContrastTest` membuktikan 11 pasangan kontras ≥ 4.5:1 untuk tema default; (c) `/app/settings` merender `role="tablist"`, 6 `role="tab"` dengan `aria-selected`/`aria-controls`/`tabindex` sesuai §4.0, tab dibaca dari **array registry** (bukan hardcode 6 `<button>`) agar U-02 "tab dinamis" terpenuhi saat role/flag hadir; (d) tab aktif dari `?tab=` query; (e) toggle tema menyimpan pilihan `auto/light/dark` di `localStorage` **dan** `<html data-theme>`; (f) Pint + build hijau. | `READY` |
| T-05 | Dashboard *Midnight Command* | T-07 | — | — | `app/Livewire/Dashboard.php`, `resources/views/livewire/dashboard.blade.php`, `routes/web.php` (tambah `/app/dashboard`) | (a) route `/app/dashboard` → 200 dan menjadi tujuan default setelah login nanti; (b) memakai **hanya** token `--erp-*`, tidak ada `bg-gray-*` hardcoded (grep = 0); (c) zona universal: 3 kartu KPI dummy + 1 kartu "Laporan AI"; (d) zona industri: **dirender oleh `DashboardComposer` stub** (T-08e nanti menggantinya) yang membaca susunan widget dari array preset dummy — **bukan** `@if($preset==='agency')` hardcode; widget dummy dibuat sebagai komponen terpisah per nama katalog (`INDUSTRY_PRESETS.md` §4) agar T-08e cukup mengganti sumber data; (e) `main#main-content` + skip-link ada; (f) test feature `assertSee` untuk setiap kartu; (g) **tidak ada literal istilah bisnis** di Blade — pakai placeholder `term()` stub yang mengembalikan default global (T-08c menggantinya). | `BLOCKED` (T-07) |
| T-06 | Komponen tabel responsif → card di mobile | T-07 | — | — | `resources/views/components/data-table.blade.php`, ganti tabel dummy di `dummy-module.blade.php` | (a) komponen Blade `<x-data-table :rows :columns>`; (b) `<table class="hidden md:table">` + `<div class="md:hidden">` card list dengan data sama; (c) `data-table` memakai token `--erp-*`; (d) semua `href="#"` di `dummy-module.blade.php` dihapus (grep = 0); (e) test feature mengecek kedua varian dirender; (f) header kolom menerima label dari caller, komponen **tidak** menyimpan istilah bisnis. | `BLOCKED` (T-07) |

**Urutan wajib Fase 2: T-07 → T-05 → T-06.** T-07 dulu karena T-05 dan T-06
bergantung pada token. Ketiganya menyentuh `app.css`/layout, jadi **serial**.

---

## ⛔ GATE `HUMAN:UI-LOCK`

Fase 3 dan seterusnya **tidak boleh dimulai** sebelum Bos menyatakan Fase 1–2
`LOCKED`. Ini keputusan bisnis (persetujuan visual), bukan pilihan teknis. Saat
autopilot tiba di sini, laporkan ringkasan Fase 2 dan **berhenti menunggu**.

---

## Fase 3a: Fondasi Tenant (BARU — sebelumnya hilang dari rencana)

> Audit 2026-09-16 menemukan tabel `companies`, `module_settings`,
> `business_identities` diasumsikan "sudah ada" padahal repo hanya punya
> `users/cache/jobs`. Tanpa Fase 3a, seluruh Fase 3–4 tidak bisa jalan.

| ID | Task | Depends On | Decisions | Gate | File Target | Acceptance | State |
|---|---|---|---|---|---|---|---|
| T-00a | Migration + model `companies`, `business_identities`; `ALTER users` (+`wa_number`, `wa_is_verified`, `current_company_id`) | — | Q-08 (**default**: kolom `current_company_id`) | `HUMAN:UI-LOCK` | `database/migrations/`, `app/Models/Company.php`, `app/Models/BusinessIdentity.php`, `app/Models/User.php` | Schema sesuai `DATA_MODEL.md` §1.1–1.2, 1.4 via Schema Builder; `migrate:fresh` hijau di SQLite; factory untuk `Company`; test relasi `Company→identities`, `User→companies`. | `BLOCKED` (gate) |
| T-00b | Migration + model `module_settings` (bentuk D-19/D-25) | T-00a | — | — | `database/migrations/`, `app/Models/ModuleSetting.php` | `UNIQUE(company_id, module_name)`; `settings_json` cast array; test tulis/baca modul `features`. | `BLOCKED` |
| T-00c | Auth scaffold minimal (login email+password, D-21) + middleware `SetCurrentCompany` | T-00a | — | — | `routes/web.php`, `app/Http/Middleware/SetCurrentCompany.php`, `resources/views/auth/` | Login → redirect `/app/dashboard`; guest → `/login`; `current_company_id` tersedia via `auth()->user()`; test guest 302, user 200. **Tanpa** Breeze/Jetstream — Livewire form sendiri agar tidak menambah dependency. | `BLOCKED` |

---

## Fase 3b: Mesin Komposisi (D-31 — WAJIB sebelum tabel domain apa pun)

> Inilah yang membuat industri ke-7..50 menjadi data, bukan kode. Tanpa fase
> ini, setiap tabel domain akan mengunci pola "1 industri = N tabel".

| ID | Task | Depends On | Decisions | File Target | Acceptance | State |
|---|---|---|---|---|---|---|
| T-08 | `business_presets` + `PresetDefinitionValidator` + seeder 7 preset dari JSON | T-00a | — | migration, `app/Models/BusinessPreset.php`, `app/Services/Preset/PresetDefinitionValidator.php`, `database/seeders/BusinessPresetSeeder.php`, `database/seeders/presets/{agency,fnb,pharmacy,eo,contractor,rental,custom}.json` | Skema `definition` sesuai `INDUSTRY_PRESETS.md` §2; validator menolak capability/term/effect/widget asing (test negatif per katalog); 7 baris terseed; `tier` benar; test bahwa `pharmacy.json` menyatakan dependensi Tier B lengkap. | `BLOCKED` (gate) |
| T-08b | `FeatureResolver` + `Company::feature()/hasAnyFeature()` | T-00b, T-08 | — | `app/Services/FeatureResolver.php`, `app/Models/Company.php` | Resolusi: `module_settings[features]` → `preset.definition.capabilities` → `false`. Test: A override tidak bocor ke B; key asing → `false`; cache per request; ganti `business_preset` company → resolusi berubah tanpa migration. | `BLOCKED` |
| T-08c | `TerminologyResolver` + helper `term()` | T-08b | — | `app/Services/TerminologyResolver.php`, `app/Support/helpers.php` (`term()`), Blade directive `@term` | Resolusi company → preset → default global (`INDUSTRY_PRESETS.md` §3); key tak dikenal → exception di dev, fallback key di prod; test: `rental` → `term('contact')='Penyewa'`, `pharmacy` → `'Pasien'`; override company menang. | `BLOCKED` |
| T-08d | `workflow_definitions` + `workflow_transitions_log` + `WorkflowEngine` + katalog efek | T-08b | — | migration ×2, `app/Services/Workflow/WorkflowEngine.php`, `app/Services/Workflow/Effects/*.php`, `app/Contracts/HasWorkflow.php` | `transition($model,$to,$actor)`: tolak transisi tak terdefinisi (exception), tolak role salah (403), `requires_approval` → buat tiket & tahan, jalankan `effects` dalam `DB::transaction`, tulis log. Materialisasi dari preset saat company dibuat. Test: 6 kasus + rollback bila efek gagal. Efek awal yang diimplementasi: `approval.request`, `notify.owner_wa` (via `HermesNodeClient` fake) — efek lain menyusul bersama kapabilitasnya. | `BLOCKED` |
| T-08e | `WidgetRegistry` + `DashboardComposer` | T-08b | — | `app/Services/Dashboard/WidgetRegistry.php`, `app/Services/Dashboard/DashboardComposer.php`, `app/Livewire/Widgets/*.php` (kerangka) | Registry memetakan nama widget → kapabilitas yang dibutuhkan (katalog §4); `compose(Company)` mengembalikan hanya widget yang kapabilitasnya `true`, urut sesuai `preset.definition.dashboard`; test: preset `rental` → `resources_status` ada, `expiring_batches` tidak. Widget nyata dibangun bertahap; T-05 memakai composer ini dengan widget dummy. | `BLOCKED` |
| T-03b | Refactor `DynamicMenuRegistry` ke bentuk flag-aware + `term()` (`INDUSTRY_PRESETS.md` §9) | T-08b, T-08c | — | `app/Services/DynamicMenuRegistry.php`, `app/Livewire/Sidebar.php`, `app/Livewire/Lobby.php`, test terkait | Registry per **kapabilitas** (bukan industri); `visible` closure per modul & item; label via `term()`; Lobby hanya menampilkan modul `visible`; test lama diperbarui; test baru: preset `fnb` **tidak** menampilkan HRD>Payroll, preset `klinik` (bukan 6 awal, di-seed hanya untuk test) menampilkan `Pasien`/`Janji Temu` **tanpa kode baru** → bukti D-31. Emoji → nama ikon Lucide. | `BLOCKED` |
| T-09 | ~~kolom `business_preset`~~ → digabung ke T-00a. | — | — | — | — | `DONE (merged)` |

**Urutan wajib Fase 3b: T-08 → T-08b → (T-08c ∥ T-08d ∥ T-08e read-only design boleh paralel, implementasi serial) → T-03b.**

---

## Fase 3c: Tabel Kapabilitas (generik — bukan per industri)

| ID | Task | Depends On | Decisions | File Target | Acceptance | State |
|---|---|---|---|---|---|---|
| T-13 | `contacts`, `deals`, `activity_logs` (§3) | T-08d | Q-05 (**default**: kode netral) | migration ×3 + model + `HasWorkflow` di `Deal` | `stage VARCHAR`, transisi via `WorkflowEngine`; `type` + `attributes`; test A/B; test bahwa preset `agency` dan `pharmacy` memakai tabel **yang sama** dengan `term()` berbeda. | `BLOCKED` |
| T-13b | `projects`, `project_milestones`, `project_assignments`, `project_vendors`, `timesheet_entries` (§5) | T-13 | — | migration ×5 + model | `Project` ber-workflow; milestone `trigger_type` valid; test: milestone `progress_pct` mencapai `trigger_value` → status `invoiced` via efek `invoice.create_*` (efek diimplementasi di task ini). | `BLOCKED` |
| T-13c | `resources`, `bookings`, `booking_incidents` (§6) + `BookingService` | T-13 | — | migration ×3 + model + service | **Invarian anti-double-booking** ditegakkan service + test (2 booking overlap → exception); `Booking` ber-workflow; efek `deposit.collect/settle`, `late_fee.compute` diimplementasi; test rundown EO = booking ber-`project_id`. | `BLOCKED` |
| T-13d | `items`, `item_batches`, `stock_movements`, `bom_lines` (§7) + `StockService` | T-00a | — | migration ×4 + model + service | Efek `stock.reserve/deduct`; FEFO: deduct mengambil batch `expires_on` terdekat (test dengan 3 batch); BOM: produce 1 produk → consume komponen sesuai `bom_lines`; test A/B. | `BLOCKED` |
| T-13e | `pos_shifts`, `orders`, `order_lines` (§8) + `OrderService` | T-13, T-13c, T-13d, T-11 | — | migration ×3 + model + service | `Order` ber-workflow; `dpp/tax/grand_total` dihitung `TaxRateService` dari `business_identity` (D-03, REQUIREMENTS §1); `external_ref` idempoten untuk webhook NalarPesan (D-04); `pos.tables`: `resource_id` meja + `fired_at` re-fire; efek `journal.post` dari order `paid`; test: tax inclusive vs exclusive, replay webhook no-op. | `BLOCKED` |
| T-13f | `employees`, `payrolls`, `ai_reminders` (§9) | T-00a | — | migration ×3 + model | `UNIQUE(company, employee, period)`; test A/B. | `BLOCKED` |
| T-11 | Akuntansi `chart_of_accounts`, `accounting_journals`, `accounting_journal_lines` (§4) + `JournalService` + `TaxRateService` | T-13b (FK `project_id`) | — | migration ×3 + model + 2 service | `company_id` di lines (D-26); `post()` menolak unbalance; `TaxRateService::calculateTax()` sesuai REQUIREMENTS §1.3 (test 4 skenario); template jurnal untuk cashbook (Debit beban / Kredit kas); test A/B. | `BLOCKED` |
| T-10 | `membership_plans`, `company_memberships` (§11.1–11.2) | T-00a | — | migration + model | Test A/B. | `BLOCKED` |
| T-10a | `token_ledger_entries` + `TokenLedgerService` (§11.3) | T-10 | — | migration + model + service | Idempoten via `idempotency_key`; saldo cache + ledger dalam 1 transaksi; test dua panggilan key sama → saldo berubah sekali. | `BLOCKED` |
| T-10b | `hermes_nodes`, `hermes_profiles` (§12) | T-00a | Q-04 (**default**: 1 per company) | migration + model | `*_secret_reference` bukan plaintext; `UNIQUE(company_id)`. | `BLOCKED` |
| T-12 | `invoices` (§2.3) | T-10, T-13b | — | migration + model | `type` topup/subscription; transisi status valid; `paid→paid` no-op. | `BLOCKED` |
| T-14 | `attachments` + trait `HasAttachments` (§13) | T-00a | — | migration + model + trait | Test morph ke `Contact`, `Prescription`; A/B. | `BLOCKED` |
| T-14b | **Tier B**: `prescriptions` + `retentions` (§10) + aturan domain | T-13, T-13b, T-13d, T-13e, T-14 | — | migration ×2 + model + `PrescriptionGuard`, `RetentionService` | Obat `drug_class ∈ {keras, psikotropika}` **ditolak** masuk `order_lines` tanpa `prescription_id` `verified` (test negatif); retensi dipotong otomatis dari invoice milestone bila `retention_pct>0`, `status=held`, tidak bisa `invoiced` sebelum `release_on` (test). | `BLOCKED` |

**Urutan FK wajib Fase 3c:** T-13 → T-13b → T-11 → T-13c → T-13d → T-13e →
T-13f → T-10 → T-10a → T-12 → T-14 → T-14b. T-10b bebas setelah T-00a. Semua
migration **serial**.

> **Catatan mengapa T-11 ada di tengah:** `orders` (T-13e) butuh `journal.post`
> dan `TaxRateService`; `journal_lines` butuh `projects`. Karena itu T-11 setelah
> T-13b, sebelum T-13e.

---

## Fase 4: Middleware, API, dan Integrasi AI

| ID | Task | Depends On | Decisions | Gate | File Target | Acceptance | State |
|---|---|---|---|---|---|---|---|
| T-15 | ~~`Company::feature()`~~ → **digabung ke T-08b**. | — | — | — | — | — | `DONE (merged)` |
| T-16 | Middleware `EnsureFeatureEnabled:{capability}` + `EnsureCompanyAccess` | T-08b, T-00c, T-03b | — | — | `app/Http/Middleware/`, `bootstrap/app.php`, `routes/web.php` | Kapabilitas off → 403; on → 200; user tanpa akses company → 403; modul tak dikenal → 404 (mengganti 200 saat ini). Route `/app/{module}` di-resolve ke kapabilitas via registry. Test 4 kasus. | `BLOCKED` |
| T-19 | Webhook payment gateway | T-10a, T-12 | Q-01 (**default**: Midtrans) | `HUMAN:SECRET` → fake | `routes/api.php` (`php artisan install:api`), `app/Http/Controllers/Api/PaymentWebhookController.php`, `app/Services/Payment/MidtransSignatureVerifier.php` | Signature `SHA512(order_id+status_code+gross_amount+server_key)`; salah → 403; `order_id` tak dikenal → 404; `settlement` → invoice `paid` + ledger credit 1 transaksi; replay → 200 no-op; test 5 kasus dengan `MIDTRANS_SERVER_KEY=test`. | `BLOCKED` |
| T-19b | Webhook NalarPesan → `orders` (D-04) | T-13e | — | `HUMAN:SECRET` → fake | `app/Http/Controllers/Api/NalarPesanWebhookController.php` | HMAC fail-closed; `external_ref` idempoten (replay → no-op); order masuk `stage='open'` dengan `resource_id` meja bila `pos.tables`; test 4 kasus. | `BLOCKED` |
| T-17 | API Master Bot (BOS Care) | T-10a, T-19, `support_tickets` (dipindah ke T-17 sendiri) | Q-01 | `HUMAN:SECRET` → fake | migration `support_tickets`, `routes/api.php`, `app/Http/Controllers/Api/MasterBot/*`, `app/Http/Middleware/AuthenticateMasterBot.php` | Header `X-Master-Bot-Key` via `hash_equals`; endpoint tiket, saldo, topup-invoice; semua wajib `company_id` & 403 bila WA user tidak memiliki company; test 6 kasus. | `BLOCKED` |
| T-17b | API Tenant Bot (MCP ERP) — `mcp_configure_modules`, `mcp_update_company_settings`, `mcp_create_contact`, `mcp_create_deal`, `mcp_record_expense`, `mcp_create_reminder` | T-08b, T-08c, T-13, T-11, T-13f | — | `HUMAN:SECRET` → fake | `routes/api.php`, `app/Http/Controllers/Api/TenantBot/*`, `app/Http/Middleware/AuthenticateTenantBot.php` | Auth per `hermes_profiles.webhook_secret_reference`; **setiap** tool wajib `company_id` & ditolak 403 bila caller `wa_number` bukan anggota company (COMMERCIAL §4 Lapis 3); `PUT /api/bot/settings|features` hanya untuk `wa_number` role owner (REQUIREMENTS §3.1–3.2); `mcp_configure_modules` menulis `module_settings[features|terminology]` → menu berubah tanpa kode (test dengan preset `custom`); aksi destruktif → `approval_flow` tiket `YA <kode>` (D-27). Test 8 kasus. | `BLOCKED` |
| T-18 | `billing:check-expiring` | T-12, T-17 | — | `HUMAN:SECRET` → fake | `app/Console/Commands/BillingCheckExpiring.php`, `app/Contracts/HermesNodeClient.php`, `app/Services/Hermes/FakeHermesNodeClient.php`, `routes/console.php` | H-3 → invoice `subscription` `pending` + `sendWhatsApp()`; idempoten per hari; dijadwalkan harian; test dengan `Carbon::setTestNow`. | `BLOCKED` |
| T-20 | Scout + Universal Search nyata | T-13, T-12, T-08b, T-08c | Q-02 (**default**: `database`) | — | `composer require laravel/scout`, `Searchable` di `Contact`, `Deal`, `Project`, `Invoice`, `Item`; `app/Livewire/CommandPalette.php` | Hasil dari DB, scoped `company_id`, label via `term()`; dummy & `href="#"` dihapus; test: A tidak melihat B; hasil menampilkan `Pasien` untuk preset `pharmacy`. | `BLOCKED` |

---

## Fase 5: UAT, QA, dan Peluncuran

| ID | Task | Depends On | Gate | Acceptance | State |
|---|---|---|---|---|---|
| T-21 | Full regression hijau | semua Fase 4 | — | `php artisan test` exit 0; `vendor/bin/pint --test` bersih. | `BLOCKED` |
| T-21b | **Paritas MySQL** (dari B-01) | T-21 | `HUMAN:SECRET` (koneksi MySQL lokal) | `migrate:fresh --seed` + `php artisan test` hijau pada `DB_CONNECTION=mysql`; catat perbedaan `json`/`decimal` bila ada. | `BLOCKED` |
| T-21c | **Bukti D-31: industri ke-7 tanpa kode** | T-21 | — | Tambah **hanya** `database/seeders/presets/klinik.json` + `salon.json` (`INDUSTRY_PRESETS.md` §7). Jalankan seeder. Buat company tiap preset. Assert: Lobby/sidebar menampilkan modul & `term()` yang benar, dashboard menampilkan widget yang benar, workflow `booking` klinik berjalan, `EnsureFeatureEnabled` memblokir modul yang off. **`git diff --stat` di luar `database/seeders/presets/` dan test harus kosong.** Bila ada perubahan kode lain → D-31 belum terpenuhi → `BLOCKED` dengan daftar hardcode yang ditemukan. | `BLOCKED` |
| T-22 | Audit white-label & tenant isolation | T-21 | — | Q-06: grep `Hermes|Nous|Nous Research|laravel/laravel` di `resources/views`, `public/`, `composer.json name` = 0 di UI tenant; **grep literal istilah** (`Klien`, `Pasien`, `Penyewa`, `Karyawan`) di Blade = 0 — semua via `term()`; semua test A/B hijau. | `BLOCKED` |
| T-23 | Build + smoke tenant dogfood | T-22, T-21c | `HUMAN:DEPLOY` | `DogfoodTenantSeeder` (8 company: 6 preset awal + klinik + salon); `npm run build`; login-as tiap owner → `/app/dashboard` 200 dan hanya modul preset yang tampil. **Bagian "live" butuh gate deploy.** | `BLOCKED` |

---

## Dependency Graph (ringkas)

```
T-01 ─┬─ T-02
      ├─ T-03 ─────────────────────────────────────────────── T-03b ─ T-16
      ├─ T-04 ──────────────────────────────────────── T-20     │
      └─ T-07 ─┬─ T-05                                  │       │
               └─ T-06                                  │       │
                    │                                   │       │
              [HUMAN:UI-LOCK]                           │       │
                    │                                   │       │
T-00a ─┬─ T-00b ─── T-08 ─── T-08b ─┬─ T-08c ───────────┼───────┘
       │                            ├─ T-08d ─┐         │
       │                            └─ T-08e  │         │
       ├─ T-00c                               │         │
       ├─ T-13d                               │         │
       ├─ T-13f                               │         │
       ├─ T-10 ─┬─ T-10a ─┬─ T-19 ─── T-17 ─── T-18      │
       │        └─ T-12 ──┘  (T-12 juga ← T-13b)        │
       ├─ T-10b                               │         │
       ├─ T-14 ─────────────────────────┐     │         │
       └─────────────── T-13 ◄──────────┼─────┘         │
                          └─ T-13b ─ T-11 ─ T-13c ─ T-13e ─ T-19b
                                                  └─ T-14b (Tier B)
                                                          T-17b
                                    T-21 ─┬─ T-21b
                                          ├─ T-21c ─┐
                                          └─ T-22 ──┴─ T-23
```

**Prinsip urutan:** fondasi tenant → **mesin komposisi** → tabel kapabilitas
generik → Tier B → API → bukti komposisi. Tabel domain **tidak boleh** dibuat
sebelum `WorkflowEngine`, `FeatureResolver`, `TerminologyResolver` ada, karena
tabel itu bergantung pada ketiganya.

---

## Aturan Eksekusi Autopilot Wajib

1. **Satu writer per worktree.** Tidak ada writer paralel sampai ada remote dan
   kebutuhan nyata.
2. **Review sebelum tulis.** Baca requirement + keputusan terkait task, audit kode
   existing, tetapkan invariant dan negative case, lalu review diff sendiri.
3. **Paralel hanya untuk lane read-only dan terisolasi.** Audit, pencarian, review
   UX/a11y, dan shard test (SQLite `:memory:` terisolasi per proses) boleh
   paralel. Writer, migration, dependency, deploy wajib serial.
4. **Rekonsiliasi setelah paralel.** Hasil subagent bukan verdict final.
5. **TDD sesuai kondisi nyata.** Bug terbukti → RED lalu GREEN. Perilaku sudah
   benar → characterization test, catat no-change.
6. **Commit lokal diizinkan** (gate `HUMAN:COMMIT` terbuka). Sebelum commit:
   `git log --oneline -3`, `git status --short`, pastikan tidak ada `.env`/secret.
   **Push, deploy, migrate non-dev** tetap butuh approval eksplisit.
7. **Laporan** di setiap batas fase dan saat `BLOCKED`: task, file, command +
   hasil, risiko, blocker, next `READY`.
