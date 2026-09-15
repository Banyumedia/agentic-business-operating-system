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
| T-07 | Design token `--erp-*` + Settings tab + theme toggle | T-03 | Q-03 (**dijawab default**: turunkan dari palet Tailwind v4, buktikan AA dengan test) | — | `resources/css/app.css`, `app/Livewire/Settings.php`, `resources/views/livewire/settings.blade.php`, `app/Services/ThemeRegistry.php` | (a) `app.css` `@theme` mendeklarasikan **36 token** sesuai `UX_UI_SPEC.md` §7; (b) `tests/Unit/ThemeContrastTest` membuktikan 11 pasangan kontras ≥ 4.5:1 untuk tema default; (c) `/app/settings` merender `role="tablist"`, 6 `role="tab"` dengan `aria-selected`/`aria-controls`/`tabindex` sesuai §4.0; (d) tab aktif dari `?tab=` query; (e) toggle tema menyimpan pilihan `auto/light/dark` di `localStorage` **dan** `<html data-theme>`; (f) Pint + build hijau. | `READY` |
| T-05 | Dashboard *Midnight Command* | T-07 | — | — | `app/Livewire/Dashboard.php`, `resources/views/livewire/dashboard.blade.php`, `routes/web.php` (tambah `/app/dashboard`) | (a) route `/app/dashboard` → 200 dan menjadi tujuan default setelah login nanti; (b) memakai **hanya** token `--erp-*`, tidak ada `bg-gray-*` hardcoded (grep = 0); (c) zona universal: 3 kartu KPI dummy + 1 kartu "Laporan AI"; (d) zona industri: merender kartu sesuai `UX_UI_SPEC.md` §5.1 berdasarkan `business_preset` (dummy `agency` untuk sekarang); (e) `main#main-content` + skip-link ada; (f) test feature `assertSee` untuk setiap kartu. | `BLOCKED` (T-07) |
| T-06 | Komponen tabel responsif → card di mobile | T-07 | — | — | `resources/views/components/data-table.blade.php`, ganti tabel dummy di `dummy-module.blade.php` | (a) komponen Blade `<x-data-table :rows :columns>`; (b) `<table class="hidden md:table">` + `<div class="md:hidden">` card list dengan data sama; (c) `data-table` memakai token `--erp-*`; (d) semua `href="#"` di `dummy-module.blade.php` dihapus (grep = 0); (e) test feature mengecek kedua varian dirender. | `BLOCKED` (T-07) |

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

## Fase 3b: Migrasi Domain

| ID | Task | Depends On | Decisions | File Target | Acceptance | State |
|---|---|---|---|---|---|---|
| T-08 | Migration + model `business_presets` + seeder **7 preset** | T-00a | — | `database/migrations/`, `app/Models/BusinessPreset.php`, `database/seeders/BusinessPresetSeeder.php` | 7 baris; `default_features` = map dari `INDUSTRY_PRESETS.md` §1 termasuk `finance.*`/`hr.*`; `custom` = semua `false` kecuali `system.ai_agent`, `finance.cashbook`; test hitung 7 dan spot-check 3 flag. | `BLOCKED` |
| T-09 | ~~Tambah kolom `business_preset`~~ → **digabung ke T-00a** (kolom dibuat langsung di `CREATE companies`). | — | — | — | Tidak ada pekerjaan terpisah. | `DONE (merged)` |
| T-10 | Migration `membership_plans`, `company_memberships` | T-00a | — | migration + model | Sesuai §11.1–11.2; `slug VARCHAR(64)`; test isolasi tenant. | `BLOCKED` |
| T-10a | Migration `token_ledger_entries` | T-10 | — | migration + model + `TokenLedgerService` | §11.3; `idempotency_key` unik; service `credit()/debit()` transaksional yang memperbarui `current_token_balance` **dan** menulis ledger dalam satu `DB::transaction`; test: dua panggilan dengan key sama → saldo berubah **sekali**. | `BLOCKED` |
| T-10b | Migration `hermes_nodes`, `hermes_profiles` | T-00a | Q-04 (**default**: satu per company) | migration + model | §12; `*_secret_reference` bukan plaintext; test `UNIQUE(company_id)`. | `BLOCKED` |
| T-13 | Migration CRM `crm_contacts`, `crm_deals`, `crm_activity_logs` | T-00a | Q-05 (**default**: kode netral + label ID) | migration + model | §3.1–3.3. **Tidak ada `crm_leads` untuk di-drop** — tabel itu tidak ada. Stage default `new/qualified/proposal/negotiation/won/lost`. Test tenant A/B. | `BLOCKED` |
| T-11 | Migration akuntansi `chart_of_accounts`, `accounting_journals`, `accounting_journal_lines` | T-13 (FK `deal_id`) | — | migration + model + `JournalService` | §4.1–4.3 **dengan `company_id` di lines** (D-26); service `post()` menolak jurnal tidak balance (Σdebit ≠ Σkredit → exception); test balance + tenant A/B. | `BLOCKED` |
| T-12 | Migration `invoices` (topup + subscription) | T-10 | — | migration + model | §2.3 dengan kolom `type`, `period_*`, `company_membership_id`; test transisi status: `pending→paid` ok, `paid→paid` no-op. | `BLOCKED` |
| T-14 | Migration `attachments` polimorfik | T-00a | — | migration + model + trait `HasAttachments` | §13.1; test morph ke `Company` dan tenant A/B. | `BLOCKED` |
| T-14b | Migration domain industri: `pharmacy_*`, `pos_*`, `rental_*`, `eo_*`, `contractor_retentions`, `hr_*`, `ai_reminders`, `support_tickets`, `ai_model_pricings` | T-13, T-14 | — | migration + model | §2.2, 2.4, 5, 6, 7, 8, 9, 10. Boleh dipecah per modul bila terlalu besar. Semua punya `company_id` + test A/B. | `BLOCKED` |

**Urutan FK wajib:** T-00a → T-00b → T-08 → T-10 → T-10a → T-13 → T-11 → T-12 →
T-14 → T-14b. T-10b dan T-00c bebas setelah T-00a. Semua migration **serial**.

---

## Fase 4: Model, Middleware, dan Arsitektur AI

| ID | Task | Depends On | Decisions | Gate | File Target | Acceptance | State |
|---|---|---|---|---|---|---|---|
| T-15 | `Company::feature()` + `hasAnyFeature()` | T-00b, T-08 | — | — | `app/Models/Company.php`, `app/Services/FeatureResolver.php` | Resolusi: override `module_settings[features]` → default preset. Test: A override `crm.leads=false` tidak mempengaruhi B; key tak dikenal → `false`; cache per-request. | `BLOCKED` |
| T-03b | Refactor `DynamicMenuRegistry` ke bentuk flag-aware `INDUSTRY_PRESETS.md` §2.1 | T-15 | — | — | `app/Services/DynamicMenuRegistry.php`, `app/Livewire/Sidebar.php`, `app/Livewire/Lobby.php`, test terkait | Registry menerima `Company`; modul/item dengan `visible=false` → nol DOM; Lobby hanya menampilkan modul `visible`; test lama diperbarui, test baru: preset `fnb` **tidak** menampilkan `hrd.payroll`. Ikon emoji → nama Lucide. | `BLOCKED` |
| T-16 | Middleware `EnsureFeatureEnabled:{flag}` + `EnsureCompanyAccess` | T-15, T-00c | — | — | `app/Http/Middleware/`, `bootstrap/app.php`, `routes/web.php` | Flag off → 403; on → 200; user tanpa akses company → 403; modul tak dikenal → 404 (mengganti perilaku 200 saat ini). Test 4 kasus. | `BLOCKED` |
| T-19 | Webhook payment gateway | T-10a, T-12 | Q-01 (**default**: Midtrans) | `HUMAN:SECRET` → **fake diizinkan** | `routes/api.php` (buat via `php artisan install:api`), `app/Http/Controllers/Api/PaymentWebhookController.php`, `app/Services/Payment/MidtransSignatureVerifier.php` | Signature `SHA512(order_id+status_code+gross_amount+server_key)`; salah → 403; `order_id` tak dikenal → 404; `settlement` → invoice `paid` + ledger credit dalam 1 transaksi; replay → 200 no-op tanpa ledger baru; test 5 kasus dengan `MIDTRANS_SERVER_KEY=test`. | `BLOCKED` |
| T-17 | API Master Bot (BOS Care) | T-14b (`support_tickets`), T-10a, T-19 | Q-01 | `HUMAN:SECRET` → fake | `routes/api.php`, `app/Http/Controllers/Api/MasterBot/*`, `app/Http/Middleware/AuthenticateMasterBot.php` | Auth: header `X-Master-Bot-Key` dicocokkan `hash_equals` dengan `config('services.master_bot.key')`; endpoint `POST /api/master/tickets`, `GET /api/master/companies/{id}/token-balance`, `POST /api/master/companies/{id}/topup-invoice`; semua wajib `company_id` dan ditolak 403 bila user WA tidak memiliki company itu; test 6 kasus. | `BLOCKED` |
| T-18 | `billing:check-expiring` | T-12, T-17 | — | `HUMAN:SECRET` → fake | `app/Console/Commands/BillingCheckExpiring.php`, `app/Contracts/HermesNodeClient.php`, `app/Services/Hermes/FakeHermesNodeClient.php`, `routes/console.php` | Membership `expires_at` = hari+3 → buat invoice `subscription` `pending` + panggil `HermesNodeClient::sendWhatsApp()`; idempoten per hari (tidak buat invoice ganda); dijadwalkan harian; test dengan `Carbon::setTestNow` + fake client merekam panggilan. | `BLOCKED` |
| T-20 | Scout + Universal Search nyata | T-13, T-12, T-15 | Q-02 (**default**: driver `database`) | — | `composer require laravel/scout`, `config/scout.php`, `Searchable` di `CrmContact`, `Invoice`; `app/Livewire/CommandPalette.php` | Hasil dari DB nyata, **scoped `company_id`**; hasil dummy dan semua `href="#"` dihapus; test: user company A tidak melihat kontak company B. | `BLOCKED` |

---

## Fase 5: UAT, QA, dan Peluncuran

| ID | Task | Depends On | Gate | Acceptance | State |
|---|---|---|---|---|---|
| T-21 | Full regression hijau | semua Fase 4 | — | `php artisan test` exit 0; `vendor/bin/pint --test` bersih. | `BLOCKED` |
| T-21b | **Paritas MySQL** (baru, dari B-01) | T-21 | `HUMAN:SECRET` (koneksi MySQL lokal) | `migrate:fresh --seed` + `php artisan test` hijau pada `DB_CONNECTION=mysql`; catat perbedaan perilaku `enum`/`json`/`decimal` bila ada. | `BLOCKED` |
| T-22 | Audit white-label & tenant isolation | T-21 | — | Q-06 daftar string: grep `Hermes|Nous|Nous Research|laravel/laravel` pada `resources/views`, `public/`, `composer.json name` = 0 hasil di UI tenant; semua test tenant A/B hijau. | `BLOCKED` |
| T-23 | Build + smoke 6 tenant dogfood | T-22 | `HUMAN:DEPLOY` | Seeder `DogfoodTenantSeeder` (6 company, 1 per preset); `npm run build`; login-as tiap owner → `/app/dashboard` 200 dan hanya modul preset yang tampil. **Bagian "live" butuh gate deploy.** | `BLOCKED` |

---

## Dependency Graph (ringkas)

```
T-01 ─┬─ T-02
      ├─ T-03 ──────────────────────────────────────── T-03b
      ├─ T-04 ──────────────────────────────── T-20     │
      └─ T-07 ─┬─ T-05                          │        │
               └─ T-06                          │        │
                    │                           │        │
              [HUMAN:UI-LOCK]                   │        │
                    │                           │        │
T-00a ─┬─ T-00b ─── T-08 ─── T-15 ──────────────┴────────┘
       ├─ T-00c ─────────────── T-16
       ├─ T-10 ─┬─ T-10a ─┬─ T-19 ─── T-17 ─── T-18
       │        └─ T-12 ──┘
       ├─ T-10b
       ├─ T-13 ─── T-11
       └─ T-14 ─── T-14b
                                          T-21 ─ T-21b ─ T-22 ─ T-23
```

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
