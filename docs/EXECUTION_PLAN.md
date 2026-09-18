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
| G-04 | Review arsitektur, tenant isolation, billing, migrasi | `DONE` | Keputusan D-24..D-41 di `00-DECISIONS.md` (Q-01..Q-08 dijawab Bos → D-34..D-41) |

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

## Fase 2: Frontend-First - Aplikasi Utuh dari JSON (D-42)

> **Keputusan Bos 2026-09-16 (D-42):** seluruh layar dibangun dulu dengan data
> JSON, backend/database menyusul. Syarat mutlak agar tidak ditulis ulang:
> **JSON menggantikan TABEL, bukan LOGIKA.** Layar membaca lewat interface;
> Fase 3 hanya mengganti implementasi `Json*` dengan `Eloquent*`. Blade tidak
> berubah. Tidak ada nama industri atau istilah bisnis literal di `app/` dan
> `resources/` - keduanya hanya boleh ada di `database/presets/*.json`.

**Tata letak data sementara:**

| Nanti hidup di | Sekarang | Isi |
|---|---|---|
| `business_presets` | `database/presets/{slug}.json` | kapabilitas, terminologi, workflow, dashboard, menu (skema `INDUSTRY_PRESETS.md` §2) |
| Skema kolom tabel | `database/schemas/{entity}.schema.json` | field, tipe, wajib/opsional, `attributes` yang diizinkan - sumber migration Fase 3 |
| Tabel per company | `storage/app/json/{company_slug}/{entity}.json` | baris dummy per company; folder = isolasi tenant |
| Kode | tetap kode | validasi, `WorkflowEngine`, komponen, `term()`, resolver |

**Enam pola layar generik** (bukan satu Blade per kapabilitas):
`ListScreen` (list+detail+form) - `PipelineScreen` (kanban stage) - `CalendarScreen`
- `CashierScreen` - `LedgerScreen` - `Composer` (dashboard/settings/onboarding).
Layar "Pasien" (klinik) dan "Penyewa" (rental) adalah `ListScreen` yang sama
dengan `entity=contacts` dan `term()` berbeda.

**Preset Fase 2:** `bengkel`, `klinik`, `salon` (Tier A). Tier B
(`pharmacy.prescription`, `construction.retention`) **dikecualikan** dari Fase 2.

| ID | Task | Depends On | Decisions | File Target | Acceptance (dapat diuji) | State |
|---|---|---|---|---|---|---|
| T-07 | Design token `--erp-*` + tema + Settings shell | T-03 | D-36, D-43 (5 tema, per-usaha, palet A default locked) | `resources/css/app.css`, `app/Services/ThemeRegistry.php`, `app/Livewire/Settings.php` | (a) 36 token `@theme` sesuai UX §7 untuk **5 tema** (A/B/C/D/E, nilai di UX §7.3a); (b) `ThemeContrastTest` 11 pasangan >= 4.5:1 **per tema** (5x11 kasus); (c) `/app/settings` tab Tampilan `role=tablist`/kartu tema dari array registry, **bukan hardcode 5 kartu**; (d) **selektor tema adalah pengaturan per-USAHA (D-43)**: owner memilih 1 dari 5 tema, tersimpan server-side (`storage/app/json/{company}/settings.json` di Fase 2; `companies.theme` di Fase 3), diterapkan via `<html data-theme>` untuk **semua** staf company itu — bukan `localStorage` per-browser; test: user lain di company sama melihat tema yang sama setelah reload; **tidak ada** toggle gelap/terang personal per akun; (e) Pint + build hijau. | `DONE` |
| T-F1 | **Kontrak data**: interface + binding `.env` | T-01 | D-42 | `app/Contracts/{EntityRepository,PresetSource,CompanyContext}.php`, `app/Providers/DataSourceServiceProvider.php`, `config/datasource.php` | `DATA_SOURCE=json` mengikat `Json*`; `eloquent` mengikat class yang belum ada -> exception jelas. `EntityRepository::for(company, entity)->all()/find()/save()/query(filters)`. Test binding per env. | `DONE` |
| T-F2 | Skema kapabilitas JSON + validator | T-F1 | D-32 | `database/schemas/*.schema.json` (contacts, deals, projects, project_milestones, resources, bookings, items, item_batches, orders, order_lines, employees, cash_entries, invoices, quotations, timesheet_entries), `app/Services/Schema/EntitySchema.php`, `SchemaValidator` | Setiap entitas §1 punya skema; `attributes` hanya key yang dideklarasikan; `company_id` implisit dari folder. Test: baris tanpa field wajib ditolak; key `attributes` asing ditolak. Skema ini **adalah** sumber migration Fase 3 (T-13*). | `DONE` |
| T-F3 | `JsonEntityRepository` + `JsonCompanyContext` | T-F2 | D-41 | `app/Services/Json/JsonEntityRepository.php`, `JsonCompanyContext.php`, `storage/app/json/{bengkel-arka,klinik-sehat,salon-ayu}/*.json` | Baca/tulis/filter/sort/paginate; tulis atomik (temp+rename); **tidak bisa** membaca folder company lain (test: path traversal `../` ditolak). Company aktif dari `?company=` (dev) lalu session. | `DONE` |
| T-F4a | **Kontrak preset & QA readiness** (docs-only, hasil QA read-only OpenCode) | T-F3 | D-31, D-32, D-42, D-46 | `docs/INDUSTRY_PRESETS.md`, `docs/EXECUTION_PLAN.md`, `docs/DATA_MODEL.md`, `docs/AUTOPILOT_STATUS.md` | (a) §2 mendefinisikan bentuk `terminal`, urutan `stages[]` sebagai dasar arah transisi, dan `requires_note: true` untuk setiap transisi mundur; (b) seluruh dokumen menetapkan **satu path kanonik `database/presets/{slug}.json`**, dan T-08 dinyatakan membaca file yang sama tanpa folder salinan preset lain; (c) kontrak data T-F4 mewajibkan workflow hilir: bengkel punya lompatan `masuk -> pengerjaan` + rework dari `qc`, klinik dan salon punya workflow `bookings`, semuanya mendeklarasikan terminal; (d) state/referensi task basi yang terkait T-F4/T-08/T-21c diselaraskan tanpa mengubah keputusan arsitektur; (e) `git diff --check` hijau. | `DONE` |
| T-F4 | 3 preset JSON + `PresetDefinitionValidator` + `JsonPresetSource` | T-F4a | D-32, D-46 | `database/presets/{bengkel,klinik,salon}.json`, `app/Services/Preset/PresetDefinitionValidator.php`, `app/Services/Json/JsonPresetSource.php` | Skema §2 dipatuhi; **D-46 integritas alur:** validator menolak stage tak terjangkau, dead end (stage non-terminal tanpa transisi keluar), dan `terminal` yang tidak dideklarasikan (3 test negatif); transisi mundur wajib `requires_note: true`. Validator **menerima kunci Tier A + Tier B** (D-32/D-33 — menolak Tier B akan membuat preset apotek/kontraktor gagal validasi) dan menolak capability/term/widget/effect di luar katalog §1/§3/§4/§5 (test negatif per katalog). Tiga preset wajib memenuhi §6.1: bengkel `orders` memiliki lompatan `masuk -> pengerjaan`, jalur `qc -> siap_diambil`, dan rework `qc -> pengerjaan` dengan `requires_note: true`; klinik dan salon memiliki workflow `bookings`; semua workflow mendeklarasikan `terminal`. **Definisi dari T-08 dipindah ke sini; T-08 Fase 3 hanya menambah tabel + seeder dari file yang sama.** | `DONE` |
| T-F5 | `FeatureResolver` + `TerminologyResolver` + `term()` + `@term` | T-F4, T-F3 | — | `app/Services/FeatureResolver.php`, `TerminologyResolver.php`, `app/Support/helpers.php` | Resolusi override company (`storage/app/json/{c}/settings.json`) -> preset -> default global §3. Test: `klinik`->`term('contact')='Pasien'`, `bengkel`->`'Pelanggan'`; key asing -> exception di dev. **Menggantikan T-08b/T-08c Fase 3b**; Fase 3 hanya mengganti sumber. | `DONE` |
| T-F6 | `WorkflowEngine` (in-memory + log JSON) + efek `approval.request`, `notify.owner_wa` (fake) | T-F5 | — | `app/Services/Workflow/WorkflowEngine.php`, `Effects/*.php`, `app/Contracts/HasWorkflow.php` | Transisi dari `preset.workflows`; **transisi melompat diizinkan bila preset mendeklarasikannya** (mis. bengkel: `masuk -> pengerjaan` untuk servis kilat) - UI menawarkan hanya transisi sah dari stage saat ini, jadi "lompat tahap" adalah data, bukan kode; tolak transisi tak terdefinisi/role salah; transisi mundur tanpa catatan alasan ditolak (D-46); `requires_approval` menahan; log ke `{c}/workflow_log.json`. Test 6 kasus. **Menggantikan T-08d**; Fase 3 menambah tabel log + `DB::transaction`. | `DONE` |
| T-F7 | `DashboardComposer` + `WidgetRegistry` + widget katalog §4 (data dari repository) | T-F5 | — | `app/Services/Dashboard/*`, `app/Livewire/Widgets/*.php`, `app/Livewire/Dashboard.php`, `resources/views/livewire/dashboard.blade.php` | `/app/dashboard` merender zona universal (3 KPI + laporan asisten dari `{c}/assistant_report.json`) + widget sesuai `preset.dashboard`; widget menghitung dari repository (bukan angka dummy statis); **grep literal istilah = 0**; test render untuk 3 preset. Acuan visual: branch `mockup/ux-dummy` (bengkel). **Menggantikan T-05 dan T-08e.** | `DONE` |
| T-F8 | Sidebar flag-aware + `term()` + route `/app/{module}` -> pola layar | T-F5 | — | `app/Services/DynamicMenuRegistry.php`, `app/Livewire/Sidebar.php`, `Lobby.php`, `routes/web.php`, `app/Http/Middleware/EnsureFeatureEnabled.php` | Registry per kapabilitas (§9); modul off -> tidak ada DOM & 403; label via `term()`; route memetakan modul -> `{pola layar, entity}`. Test: `salon` tanpa `projects` -> menu & route hilang; `klinik` menampilkan `Pasien`. **Menggantikan T-03b dan bagian route T-16.** | `DONE` |
| T-F9 | `ListScreen` + `<x-data-table>` responsif + form dari skema | T-F8, T-F2 | — | `app/Livewire/Screens/ListScreen.php`, `resources/views/livewire/screens/list.blade.php`, `resources/views/components/data-table.blade.php`, `form-field.blade.php` | Kolom & form **digenerate dari `{entity}.schema.json`** + label `term()`; tabel desktop / card mobile; search, sort, paginate; create/edit/delete lewat repository; empty-state via `term()`. Test: entitas `contacts` untuk 3 preset; `employees` untuk `salon`. **Menggantikan T-06.** | `DONE` |
| T-F9b | **Data demo layak review** (permintaan Bos 2026-09-17, di luar rencana awal) | T-F9 | D-42 | `storage/app/json/{bengkel-arka,klinik-sehat,salon-ayu}/*.json`, `app/Services/Dashboard/WidgetRegistry.php` | 48 fixture terisi data operasional yang koheren (bukan stub 1 baris): minimal 8 `contacts`, 4 `employees`, 6 `bookings`, 8 `cash_entries` per company; seluruh baris lolos `SchemaValidator`; **integritas referensi diuji** (setiap FK non-null menunjuk baris yang ada); stage hanya memakai kode dari workflow preset; widget dashboard menghasilkan angka nyata. Data industri hanya di `storage/app/json/` (diizinkan D-42). | `DONE` |
| T-F10 | `PipelineScreen` (kanban) + `CalendarScreen` | T-F9, T-F6 | — | `app/Livewire/Screens/{PipelineScreen,CalendarScreen}.php` + Blade | Kolom kanban = stage dari `WorkflowEngine`; drag/pindah = `transition()` (ditolak -> toast, tidak berubah); kalender harian/mingguan untuk `bookings`/`scheduling` dengan **anti-double-booking** di repository (test overlap). Test: work-order `bengkel`, janji temu `klinik`, booking kursi `salon`. | `DONE` |
| T-F11 | `CashierScreen` + `LedgerScreen` | T-F9 | D-03, D-44, D-45 | `app/Livewire/Screens/{CashierScreen,LedgerScreen}.php`, `app/Services/TaxRateService.php` | Scope awal `084181f` + koreksi QA finansial T-F11R. | `DONE` |
| T-F11R | **Koreksi QA finansial T-F11** | T-F11 | D-03, D-44, D-45 | kontrak/adapter repository aggregate, `CashierScreen`, `BusinessIdentityStore`, `LedgerScreen`, dialog, test negatif | (a) checkout mengambil ulang item/harga dari repository dan allowlist metode bayar; (b) order+lines all-or-nothing dengan journal recovery, nomor UUID non-racy, replay token no-op; (c) identity company-scoped dan file/id/tax_mode hilang fail-closed; (d) invoice SaaS tidak dihitung sebagai ledger operasional atau diberi CRUD generik; route kanonik sementara fallback read-only; (e) total order = jumlah line setelah pembulatan dan cap aritmetika aman; (f) dialog simple memakai aksi merah + focus trap/restore; (g) decimal precision aman untuk integer besar; negative tests wajib. | `DONE` |
| T-F12 | Settings 6 tab (Profil, Identitas Usaha, Preset & Istilah, Alur, Asisten AI, Tampilan) + onboarding form (D-40) | T-F9, T-F7 | D-40 | `app/Livewire/Settings.php` + tab components, `app/Livewire/Onboarding.php` | Tab dari registry + role/flag; dropdown preset **dari `PresetSource`** (bukan hardcode 3); tab Istilah mengedit override -> `term()` berubah tanpa reload server; tab Alur menampilkan graf stage dari preset; onboarding membuat folder company baru + `settings.json`. | `DONE` |
| T-F13 | Universal Search dari repository | T-F9 | — | `app/Livewire/CommandPalette.php` | Hasil dari entitas yang kapabilitasnya aktif, label `term()`, `href` nyata (grep `href="#"` = 0), scoped company. **Menggantikan bagian UI T-20**; Scout menyusul Fase 3. | `DONE` |
| T-F13R | **Koreksi QA T-F13** (registry `timesheets`→`timesheet_entries`, catch generik di jalur search, negative test tenant stale-component) | T-F13 | D-31, D-42 | `app/Services/DynamicMenuRegistry.php`, `app/Livewire/CommandPalette.php`, `tests/Feature/CommandPaletteDataSearchTest.php` | (a) dua item registry (`projects/timesheet`, `hrd/attendance`) memakai entity `timesheet_entries` yang punya schema, bukan `timesheets`; (b) `/app/hrd/attendance` render 200 dibuktikan test acceptance dengan datasource terisolasi; (c) baris timesheet unik ditemukan Command Palette sebagai `type=Data` dengan `url` yang di-GET 200, diverifikasi dari `viewData('results')` bukan parsing `<a>` pertama; (d) `catch (Throwable)` generik di jalur repository `searchEntityData()` dihapus - schema hilang/JSON rusak melempar exception fail-visible, bukan hasil kosong; (e) negative test stale-component: company aktif berpindah A→B setelah `CommandPalette` mount, hasil hanya menampilkan data B; (f) komentar `MAX_PER_ENTITY`/`MAX_DATA_RESULTS` diperbaiki agar tidak mengklaim membatasi baris yang dipindai; (g) total hasil didokumentasikan jujur sebagai maks 16 (8 menu + 8 data, dua kuota terpisah). | `DONE` |
| T-F14 | **Uji anti-hardcode otomatis** | T-F7..T-F13, T-F13R | D-31 | `tests/Architecture/NoIndustryHardcodeTest.php`, `NoLiteralTermsTest.php`, `RenderAllPresetsTest.php` | (1) grep regex nama industri (`bengkel|klinik|salon|agency|apotek|pharmacy|rental|kontraktor|...`) di `app/` + `resources/` = 0; (2) grep istilah kamus §3 literal di Blade = 0; (3) **audit a11y**: setiap overlay/modal/drawer punya `role="dialog"`+`aria-modal`+`aria-labelledby`; setiap nav punya tepat satu `aria-current="page"`; tombol pembuka drawer punya `aria-expanded`+`aria-controls` (UX §6.10); (4) setiap route `/app/*` dirender untuk 3 preset -> 200/403 sesuai kapabilitas, tanpa exception. Test ini **permanen** dan berjalan di `php artisan test` selamanya. | `DONE` |
| T-F15 | **Bukti D-31 awal: `laundry.json`** | T-F14 | D-31 | `database/presets/laundry.json`, `storage/app/json/laundry-bersih/*.json` | Tambah preset + data dummy -> aplikasi lengkap (menu, dashboard, kanban `order`, kasir) muncul. **`git diff --stat` di luar `database/presets/`, `storage/app/json/`, dan test = kosong.** Bila tidak -> `BLOCKED` dengan daftar hardcode. | `DONE` |

**Urutan Fase 2:** T-07 -> T-F1 -> T-F2 -> T-F3 -> T-F4a -> T-F4 -> T-F5 -> (T-F6, T-F7,
T-F8) -> T-F9 -> (T-F10, T-F11, T-F12, T-F13) -> T-F13R -> T-F14 -> T-F15. Semua writer
serial; yang dalam kurung independen secara file tetapi tetap satu writer.

**Yang berubah di fase berikutnya karena D-42:**
- Scope inti T-05, T-06, T-03b, T-08b, T-08c, T-08d, T-08e, dan
  bagian UI T-16/T-20 **dipindahkan ke task Fase 2 terkait**; jangan dikerjakan
  ulang setelah task tujuan itu `DONE`. Adapter Eloquent, materialisasi, dan
  persistence database tetap menjadi scope residual Fase 3.
- T-08 (Fase 3b) menjadi: tabel `business_presets` + seeder yang membaca
  `database/presets/*.json` yang sudah ada + `EloquentPresetSource`.
- T-13* (Fase 3c) menjadi: migration **digenerate/diturunkan dari
  `database/schemas/*.schema.json`** + `EloquentEntityRepository` per entitas +
  `php artisan json:import` untuk memindahkan `storage/app/json` menjadi seeder
  demo. Acceptance tambahan setiap T-13*: `RenderAllPresetsTest` tetap hijau
  dengan `DATA_SOURCE=eloquent` **tanpa mengubah Blade**.
- T-21c tetap ada sebagai bukti akhir dengan database nyata.

---

## GATE `HUMAN:UI-LOCK`

Fase 3 **tidak boleh dimulai** sebelum Bos menyatakan Fase 2 `LOCKED` setelah
melihat **aplikasi utuh berjalan** untuk `bengkel`, `klinik`, `salon`, dan
`laundry` dengan `DATA_SOURCE=json`. Ini persetujuan visual + alur, bukan pilihan
teknis. Saat autopilot tiba di sini: laporkan ringkasan, URL tiap preset, hasil
T-F14/T-F15, dan **berhenti menunggu**.

---

## Fase 3a: Fondasi Tenant (BARU — sebelumnya hilang dari rencana)

> Audit 2026-09-16 menemukan tabel `companies`, `module_settings`,
> `business_identities` diasumsikan "sudah ada" padahal repo hanya punya
> `users/cache/jobs`. Tanpa Fase 3a, seluruh Fase 3–4 tidak bisa jalan.

| ID | Task | Depends On | Decisions | Gate | File Target | Acceptance | State |
|---|---|---|---|---|---|---|---|
| T-00a | Migration + model `companies`, `business_identities`; `ALTER users` (+`wa_number`, `wa_is_verified`, `current_company_id`) | — | D-41 (ex-Q-08, **locked**; default**: kolom `current_company_id`) | `HUMAN:UI-LOCK` | `database/migrations/`, `app/Models/Company.php`, `app/Models/BusinessIdentity.php`, `app/Models/User.php` | Schema sesuai `DATA_MODEL.md` §1.1–1.2, 1.4 via Schema Builder; `migrate:fresh` hijau di SQLite; factory untuk `Company`; test relasi `Company→identities`, `User→companies`. | `DONE` |
| T-00b | Migration + model `module_settings` (bentuk D-19/D-25) | T-00a | — | — | `database/migrations/`, `app/Models/ModuleSetting.php` | `UNIQUE(company_id, module_name)`; `settings_json` cast array; test tulis/baca modul `features`. | `READY` |
| T-00c | Auth scaffold minimal (login email+password, D-21) + middleware `SetCurrentCompany` | T-00a | — | — | `routes/web.php`, `app/Http/Middleware/SetCurrentCompany.php`, `resources/views/auth/` | Login → redirect `/app/dashboard`; guest → `/login`; `current_company_id` tersedia via `auth()->user()`; test guest 302, user 200. **Tanpa** Breeze/Jetstream — Livewire form sendiri agar tidak menambah dependency. | `READY` |

---

## Fase 3b: Mesin Komposisi (D-31 — WAJIB sebelum tabel domain apa pun)

> Inilah yang membuat industri ke-7..50 menjadi data, bukan kode. Tanpa fase
> ini, setiap tabel domain akan mengunci pola "1 industri = N tabel".

| ID | Task | Depends On | Decisions | File Target | Acceptance | State |
|---|---|---|---|---|---|---|
| T-08 | `business_presets` + seeder dari `database/presets/*.json` + `EloquentPresetSource` | T-00a, T-F4 | D-42 | migration, `app/Models/BusinessPreset.php`, `app/Services/Preset/PresetDefinitionValidator.php`, `database/seeders/BusinessPresetSeeder.php`, `database/presets/{agency,fnb,pharmacy,eo,contractor,rental,custom}.json` | Skema `definition` sesuai `INDUSTRY_PRESETS.md` §2; tujuh file tambahan dibuat di path kanonik yang sama dengan preset Fase 2; seeder membaca dan memvalidasi ulang seluruh `database/presets/*.json`; jumlah baris sama dengan jumlah file kanonik; `tier` benar; tidak ada salinan preset di folder seeder. | `DONE` |
| T-08b | Adapter Eloquent untuk `FeatureResolver` yang dibangun di T-F5; `Company::feature()` membaca `module_settings` via `EloquentCompanyContext` | T-00b, T-08 | D-42 | `app/Services/FeatureResolver.php`, `app/Models/Company.php` | Resolusi: `module_settings[features]` → `preset.definition.capabilities` → `false`. Test: A override tidak bocor ke B; key asing → `false`; cache per request; ganti `business_preset` company → resolusi berubah tanpa migration. | `DONE` |
| T-08c | Adapter Eloquent untuk `TerminologyResolver` yang dibangun di T-F5; override dari `module_settings[terminology]` | T-08b | D-42 | `app/Services/TerminologyResolver.php`, `app/Support/helpers.php` (`term()`), Blade directive `@term` | Resolusi company → preset → default global (`INDUSTRY_PRESETS.md` §3); key tak dikenal → exception di dev, fallback key di prod; test: `rental` → `term('contact')='Penyewa'`, `pharmacy` → `'Pasien'`; override company menang. **Temuan:** `TerminologyResolver` sudah generik lewat `CompanyContext`/`PresetSource`/`CompanySettingsStore` (adapter Eloquent-nya sudah ada dari T-08b) — tidak perlu adapter baru, cukup test regression `tests/Feature/TerminologyResolverEloquentTest.php` (2 test). | `DONE` |
| T-08d | Tabel `workflow_definitions` + `workflow_transitions_log`; `WorkflowEngine` (T-F6) diberi `DB::transaction` + log ke tabel | T-08b | D-42 | migration ×2, `app/Services/Workflow/WorkflowEngine.php`, `app/Services/Workflow/Effects/*.php`, `app/Contracts/HasWorkflow.php` | `transition($model,$to,$actor)`: tolak transisi tak terdefinisi (exception), tolak role salah (403), `requires_approval` → buat tiket & tahan, jalankan `effects` dalam `DB::transaction`, tulis log. Materialisasi dari preset saat company dibuat. Test: 6 kasus + rollback bila efek gagal. Efek awal yang diimplementasi: `approval.request`, `notify.owner_wa` (via `HermesNodeClient` fake) — efek lain menyusul bersama kapabilitasnya. **Keputusan arsitektur (2026-09-18):** WorkflowEngine tetap SATU implementasi (bukan duplikasi Json/Eloquent) — logging ke tabel DB (`workflow_transitions_log`, `approval_tickets`) dibuat conditional pada `config('datasource.driver')==='eloquent'`; JSON-mode Fase 2 tetap murni log JSON, tidak butuh migration. `NotifyOwnerWa` fail-closed terverifikasi dengan 2 negative test (WA belum verified, delivery gagal) — keduanya membuktikan rollback in-memory + log tidak tertulis. | `DONE` |
| T-08e | Regression `WidgetRegistry` + `DashboardComposer` dari T-F7 dengan sumber Eloquent | T-08, T-F7 | D-42 | test integrasi dashboard Eloquent | Dengan `DATA_SOURCE=eloquent`, komposisi widget identik dengan sumber JSON untuk preset yang sama; tidak ada implementasi composer kedua. **BLOCKED (ditemukan 2026-09-18):** `DashboardComposer`/`WidgetRegistry` bergantung pada `EntityRepository`, yang untuk `DATA_SOURCE=eloquent` masih melempar `LogicException` (belum diimplementasikan — scope Fase 3c). Percobaan pertama men-spoof `JsonEntityRepository` via reflection ditolak orkestrator karena tidak membuktikan Eloquent bekerja sungguhan (menyesatkan). Menunggu `EntityRepository` Eloquent tersedia sebelum task ini bisa dikerjakan jujur. | `BLOCKED` |
| T-03b | Regression `DynamicMenuRegistry` dari T-F8 dengan sumber Eloquent | T-08b, T-F8 | D-42 | test integrasi menu Eloquent | Dengan `DATA_SOURCE=eloquent`, registry per kapabilitas menghasilkan menu dan istilah yang sama dengan sumber JSON; tidak ada registry kedua atau kondisi industri. **Temuan:** `DynamicMenuRegistry` juga generik (hanya bergantung `CompanyContext`/`PresetSource`, bukan `EntityRepository`) — selesai penuh dengan test `tests/Feature/EloquentDynamicMenuRegistryTest.php` (5 test). | `DONE` |
| T-09 | ~~kolom `business_preset`~~ → digabung ke T-00a. | — | — | — | — | `DONE (merged)` |

**Urutan wajib Fase 3b: T-08 → T-08b → (T-08c ∥ T-08d ∥ T-08e read-only design boleh paralel, implementasi serial) → T-03b.**

---

## Fase 3c: Tabel Kapabilitas (generik — bukan per industri)

| ID | Task | Depends On | Decisions | File Target | Acceptance | State |
|---|---|---|---|---|---|---|
| T-13 | `contacts`, `deals`, `activity_logs` (§3) | T-08d | D-38 (kode netral, locked) | migration ×3 + model + `HasWorkflow` di `Deal` | `stage VARCHAR`, transisi via `WorkflowEngine`; `type` + `attributes`; test A/B; test bahwa preset `agency` dan `pharmacy` memakai tabel **yang sama** dengan `term()` berbeda. | `DONE e8fe772` |
| T-13b | `projects`, `project_milestones`, `project_assignments`, `project_vendors`, `timesheet_entries` (§5) | T-13 | — | migration ×5 + model | `Project` ber-workflow; milestone `trigger_type` valid; test: milestone `progress_pct` mencapai `trigger_value` → status `invoiced` via efek `invoice.create_*` (efek diimplementasi di task ini). | `DONE 35c1fa7` |
| T-13c | `resources`, `bookings`, `booking_incidents` (§6) + `BookingService` | T-13 | — | migration ×3 + model + service | **Invarian anti-double-booking** ditegakkan service + test (2 booking overlap → exception); `Booking` ber-workflow; efek `deposit.collect/settle`, `late_fee.compute` diimplementasi; test rundown EO = booking ber-`project_id`. | `DONE 7231b5d` |
| T-13d | `items`, `item_batches`, `stock_movements`, `bom_lines` (§7) + `StockService` | T-00a | — | migration ×4 + model + service | Efek `stock.reserve/deduct`; FEFO: deduct mengambil batch `expires_on` terdekat (test dengan 3 batch); BOM: produce 1 produk → consume komponen sesuai `bom_lines`; test A/B. | `DONE 89d64d6` |
| T-13e | `pos_shifts`, `orders`, `order_lines` (§8) + `OrderService` | T-13, T-13c, T-13d, T-11 | — | migration ×3 + model + service | `Order` ber-workflow; `dpp/tax/grand_total` dihitung `TaxRateService` dari `business_identity` (D-03, REQUIREMENTS §1); `external_ref` idempoten untuk webhook NalarPesan (D-04); `pos.tables`: `resource_id` meja + `fired_at` re-fire; efek `journal.post` dari order `paid`; test: tax inclusive vs exclusive, replay webhook no-op. | `DONE 9503b08` |
| T-13f | `employees`, `payrolls`, `ai_reminders` (§9) | T-00a | — | migration ×3 + model | `UNIQUE(company, employee, period)`; test A/B. | `DONE 01a627d` |
| T-11 | Akuntansi `chart_of_accounts`, `accounting_journals`, `accounting_journal_lines` (§4) + `JournalService` + `TaxRateService` | T-13b (FK `project_id`) | — | migration ×3 + model + 2 service | `company_id` di lines (D-26); `post()` menolak unbalance; `TaxRateService::calculateTax()` sesuai REQUIREMENTS §1.3 (test 4 skenario); template jurnal untuk cashbook (Debit beban / Kredit kas); test A/B. | `DONE 27d604f` |
| T-10 | `membership_plans`, `company_memberships` (§11.1–11.2) | T-00a | — | migration + model | Test A/B. | `BLOCKED` |
| T-10a | `token_ledger_entries` + `TokenLedgerService` (§11.3) | T-10 | — | migration + model + service | Idempoten via `idempotency_key`; saldo cache + ledger dalam 1 transaksi; test dua panggilan key sama → saldo berubah sekali. | `BLOCKED` |
| T-10b | `hermes_nodes`, `hermes_profiles`, `hermes_profile_companies`, `hermes_conversation_contexts` (§12) | T-00a | D-37, **D-53** (kuota GRUP dari paket vs bot ke-2 = add-on), **D-55** (nomor WA **milik klien** via scan QR; platform menyediakan nomor hanya sebagai add-on — jalur default tidak boleh mengasumsikan platform punya stok nomor) | migration x4 + model | `*_secret_reference` bukan plaintext; `owner_user_id` (bukan `company_id UNIQUE`); test: owner 2 company → 1 profile + 2 pivot; `type=addon` tanpa `billing_addon_id` ditolak; tepat satu `primary` per owner divalidasi model. | `BLOCKED` |
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
| T-19 | Webhook payment gateway | T-10a, T-12 | D-34 (Midtrans, locked) | `HUMAN:SECRET` → fake | `routes/api.php` (`php artisan install:api`), `app/Http/Controllers/Api/PaymentWebhookController.php`, `app/Services/Payment/MidtransSignatureVerifier.php` | Signature `SHA512(order_id+status_code+gross_amount+server_key)`; salah → 403; `order_id` tak dikenal → 404; `settlement` → invoice `paid` + ledger credit 1 transaksi; replay → 200 no-op; test 5 kasus dengan `MIDTRANS_SERVER_KEY=test`. | `BLOCKED` |
| T-19b | Webhook NalarPesan → `orders` (D-04) | T-13e | — | `HUMAN:SECRET` → fake | `app/Http/Controllers/Api/NalarPesanWebhookController.php` | HMAC fail-closed; `external_ref` idempoten (replay → no-op); order masuk `stage='open'` dengan `resource_id` meja bila `pos.tables`; test 4 kasus. | `BLOCKED` |
| T-17 | API Master Bot (BOS Care) | T-10a, T-19, `support_tickets` (dipindah ke T-17 sendiri) | D-34 | `HUMAN:SECRET` → fake | migration `support_tickets`, `routes/api.php`, `app/Http/Controllers/Api/MasterBot/*`, `app/Http/Middleware/AuthenticateMasterBot.php` | Header `X-Master-Bot-Key` via `hash_equals`; endpoint tiket, saldo, topup-invoice; semua wajib `company_id` & 403 bila WA user tidak memiliki company; test 6 kasus. | `BLOCKED` |
| T-17b | API Tenant Bot (MCP ERP) — `mcp_configure_modules`, `mcp_update_company_settings`, `mcp_create_contact`, `mcp_create_deal`, `mcp_record_expense`, `mcp_create_reminder` | T-08b, T-08c, T-13, T-11, T-13f | — | `HUMAN:SECRET` → fake | `routes/api.php`, `app/Http/Controllers/Api/TenantBot/*`, `app/Http/Middleware/AuthenticateTenantBot.php` | Auth per `hermes_profiles.webhook_secret_reference`; **setiap** tool wajib `company_id` & ditolak 403 bila caller `wa_number` bukan anggota company (COMMERCIAL §4 Lapis 3); `PUT /api/bot/settings|features` hanya untuk `wa_number` role owner (REQUIREMENTS §3.1–3.2); `mcp_configure_modules` menulis `module_settings[features|terminology]` → menu berubah tanpa kode (test dengan preset `custom`); aksi destruktif → `approval_flow` tiket `YA <kode>` (D-27). Test 8 kasus. | `BLOCKED` |
| T-18 | `billing:check-expiring` + **tangga penurunan layanan (D-49)** | T-12, T-17 | D-49 | `HUMAN:SECRET` → fake | `app/Console/Commands/BillingCheckExpiring.php`, `app/Services/Billing/DunningLadder.php`, `app/Contracts/HermesNodeClient.php`, `app/Services/Hermes/FakeHermesNodeClient.php`, `routes/console.php` | H-3 → invoice `subscription` `pending` + `sendWhatsApp()`; idempoten per hari; dijadwalkan harian; test `Carbon::setTestNow`. **Tangga D-49:** H+0 `status=ai_suspended` (bot mati, web penuh); H+7 `read_only` (tulis ditolak 423, **ekspor tetap jalan**); H+30 `frozen` (login ditolak, data utuh); H+90 boleh hapus **hanya bila** `dunning_notified_at` memuat 3 peringatan (H+30/H+60/H+83). Pembayaran di tahap mana pun → `active` seketika tanpa kehilangan data. Test: 6 transisi tangga + test negatif "hapus tanpa 3 peringatan ditolak". | `BLOCKED` |
| T-20 | Scout + Universal Search nyata | T-13, T-12, T-08b, T-08c | D-35 (`database`, locked) | — | `composer require laravel/scout`, `Searchable` di `Contact`, `Deal`, `Project`, `Invoice`, `Item`; `app/Livewire/CommandPalette.php` | Hasil dari DB, scoped `company_id`, label via `term()`; dummy & `href="#"` dihapus; test: A tidak melihat B; hasil menampilkan `Pasien` untuk preset `pharmacy`. | `BLOCKED` |
| T-17c | **Panel Super Admin minimal + "Login As" beraudit (D-47)** | T-00c, T-16 | `HUMAN:UI-LOCK` (belum ada mockup panel admin) | `app/Http/Controllers/Admin/*`, migration `admin_impersonation_sessions`, `app/Http/Middleware/RequireSuperAdmin.php`, banner Blade global | Route `/admin/*` terpisah dari `/app/*`, guard role `platform_admin` (bukan `companies` biasa); tombol "Login As" per company → buat sesi impersonasi + redirect `/app/dashboard`; banner kuning permanen non-dismissable selama sesi; semua tulis ke `module_settings`/`business_preset`/`companies.theme` selama impersonasi tercatat `changed_by_type=admin_impersonation` + `admin_user_id`; aksi finansial/destruktif tetap lewat D-45 tingkat 1/2 (tidak ada bypass); admin **tidak** bisa lihat password/secret klien (test 403 pada endpoint secret); tombol "Akhiri Sesi Bantuan" mengembalikan ke `/admin`. Test 6 kasus termasuk audit log lengkap. | `BLOCKED` |
| T-10c | **Mode hemat saat saldo token habis (D-48)** | T-10a | D-48 | — | `app/Services/Token/EmergencyModeResolver.php`, `app/Services/Token/ModelSelector.php` | Saat `current_token_balance <= 0` → `emergency_mode_active=true`, pemilihan model dipaksa ke multiplier terendah di `ai_model_pricings`, debit tetap tercatat di `token_ledger_entries` dari `emergency_balance`. Bot memberi tahu **sekali per percakapan** (U-05: tidak berulang, tidak terasa jualan). `emergency_balance` habis → bot berhenti membalas + link topup, **web tetap 200 penuh** (test). Topup → mode hemat mati seketika. Test 5 kasus termasuk "web tidak terpengaruh saat bot mati". | `BLOCKED` |
| T-10d | **Gerbang kapabilitas per paket (D-52)** | T-10, T-08b | D-52 | — | `app/Services/PlanCapabilityGate.php`, integrasi ke `FeatureResolver` | `FeatureResolver` menghasilkan irisan: kapabilitas aktif = `preset ∩ module_settings ∩ plan.features`. Kapabilitas di luar paket → `false` (menu hilang, route 403). Saat onboarding, preset yang menuntut kapabilitas di luar paket tampil berlabel **"perlu paket lebih tinggi" + daftar kapabilitas yang kurang** (pengecualian sah U-04 karena ini informasi harga). **Dilarang** memakai nama preset/industri sebagai syarat (D-31) — test grep memastikan gerbang hanya menyebut kunci kapabilitas. Test: Starter tidak bisa `projects.progress_billing`; upgrade paket → kapabilitas muncul tanpa migration. | `BLOCKED` |
| T-12b | **Trial 14 hari + ekspor data mandiri (D-51)** | T-10, T-00c | D-51 | — | `app/Services/Billing/TrialProvisioner.php`, `app/Livewire/Settings/DataExport.php`, `app/Jobs/BuildCompanyExport.php` | Trial: 14 hari fitur penuh, `trial_token_quota`, tanpa kartu; satu owner satu trial (dicegah via `wa_number` **dan** email, bukan email saja — test duplikat); berakhir → masuk D-49 tahap H+7 (hanya-baca), bukan hilang. **Ekspor:** owner unduh seluruh data usahanya dari `/app/settings` kapan saja (CSV per entitas + JSON preset/pengaturan), **tetap berfungsi saat status `read_only`/menunggak** (test eksplisit). Tidak perlu minta admin platform. Lampiran tetap di Drive klien (D-22). | `BLOCKED` |


---

## Fase 4b: Kepatuhan Data Pribadi (D-50) — WAJIB sebelum menjual preset klinik/apotek

> Produk menyimpan data pribadi pelanggan klien, dan untuk klinik/apotek juga
> **data kesehatan** — data pribadi spesifik menurut UU 27/2022 (PDP). Platform
> berperan sebagai **prosesor**, tenant sebagai **pengendali**. Fase ini bukan
> opsional: tanpa ini, preset klinik/apotek tidak boleh dijual.

| ID | Task | Depends On | Decisions | Acceptance | State |
|---|---|---|---|---|---|
| T-27 | Penandaan kapabilitas sensitif + kebijakan privasi & persetujuan | T-08, T-00c | D-50(a) | Katalog kapabilitas diberi atribut `sensitive: true` (mis. yang menyimpan kondisi kesehatan); teks kebijakan privasi + persetujuan ditampilkan saat onboarding dan **waktu persetujuannya dicatat** (`companies.privacy_accepted_at`, `accepted_by_user_id`, versi teks). Test: company tanpa persetujuan tidak bisa mengaktifkan kapabilitas `sensitive`. | `BLOCKED` |
| T-27b | Enkripsi at-rest kolom sensitif | T-27, T-13 | D-50(b) | Kolom identitas pasien/catatan medis/NIK dienkripsi di level aplikasi (cast terenkripsi Laravel), bukan hanya TLS. Kunci dari `APP_KEY`/KMS, **tidak** di repo. Test: baris di DB tidak terbaca polos; pencarian tetap berfungsi lewat kolom hash/blind-index terpisah bila diperlukan. | `BLOCKED` |
| T-27c | `access_logs` — siapa membuka data sensitif | T-27 | D-50(c) | migration `access_logs` (company_id, user_id, subject_type, subject_id, action, ip, created_at); setiap pembacaan entitas bertanda `sensitive` tercatat. Test: buka rekam pasien → 1 baris log; ekspor massal → tercatat sebagai satu peristiwa. Log ini **tidak** boleh bisa dihapus dari UI tenant. | `BLOCKED` |
| T-27d | Hak subjek data: ekspor & hapus per pelanggan | T-12b, T-27c | D-50(d) | Owner tenant dapat mengekspor **atau menghapus** data satu pelanggan/pasien atas permintaan orang tersebut, dari UI. Penghapusan memakai D-45 tingkat 1 (ketik YA) dan menyisakan catatan audit tanpa data pribadi. Test: setelah hapus, data pribadi hilang dari semua entitas terkait; jejak transaksi keuangan tetap ada dalam bentuk teranonimkan (kewajiban pembukuan tetap terpenuhi). | `DONE` |
| T-27e | Kendali pengiriman data ke AI | T-27, T-17b | D-50(f) | Data pada kapabilitas bertanda `sensitive` **tidak** dikirim ke model AI kecuali owner mengaktifkan secara eksplisit per-kapabilitas; default **mati**. Test: dengan flag mati, payload MCP tidak memuat field sensitif (assert field-by-field), dan bot menjawab bahwa ia tidak memiliki akses tersebut. | `BLOCKED` |

**Catatan:** T-27..T-27e tidak memblokir peluncuran preset non-kesehatan
(bengkel, salon, laundry, kontraktor, ritel). Yang diblokir hanya penjualan
preset klinik/apotek sampai fase ini hijau.

---

## Fase 5: UAT, QA, dan Peluncuran

| ID | Task | Depends On | Gate | Acceptance | State |
|---|---|---|---|---|---|
| T-21 | Full regression hijau | semua Fase 4 | — | `php artisan test` exit 0; `vendor/bin/pint --test` bersih. | `DONE` |
| T-21b | **Paritas MySQL** (dari B-01) | T-21 | `HUMAN:SECRET` (koneksi MySQL lokal) | `migrate:fresh --seed` + `php artisan test` hijau pada `DB_CONNECTION=mysql`; catat perbedaan `json`/`decimal` bila ada. | `BLOCKED` |
| T-21c | **Bukti akhir D-31 dengan database nyata** | T-21 | — | Seeder memuat `database/presets/laundry.json` yang sudah dibuktikan pada T-F15, lalu buat company preset tersebut. Assert: Lobby/sidebar menampilkan modul dan `term()` yang benar, dashboard menampilkan widget yang benar, workflow order berjalan, dan `EnsureFeatureEnabled` memblokir modul yang off. **Diff source di luar test harus kosong.** Bila perlu perubahan kode atau salinan preset → D-31 belum terpenuhi dan task `BLOCKED` dengan daftar hardcode. | `DONE` |
| T-22 | Audit white-label & tenant isolation | T-21 | — | D-39: grep `Hermes|Nous|Nous Research|laravel/laravel` di `resources/views`, `public/`, `composer.json name` = 0 di UI tenant; **grep literal istilah** (`Klien`, `Pasien`, `Penyewa`, `Karyawan`) di Blade = 0 — semua via `term()`; semua test A/B hijau. | `DONE` |
| T-23 | Build + smoke tenant dogfood | T-22, T-21c | `HUMAN:DEPLOY` | `DogfoodTenantSeeder` (4 company untuk preset kanonik `bengkel`, `klinik`, `salon`, `laundry`); `npm run build`; login-as tiap owner → `/app/dashboard` 200 dan hanya modul preset yang tampil. **Bagian "live" butuh gate deploy.** | `BLOCKED` |

---

## Fase 6: Ekspansi Pasar — Preset Data (pasca-T-21c)

Sumber: `INDUSTRY_PRESETS.md` §7 (peta 63 bisnis) dan §7.11 (urutan GTM).
Setiap task = **hanya** file JSON di `database/presets/` + satu test
komposisi. **Jika sebuah task di fase ini memerlukan diff kode di luar folder
preset, task itu bukan preset — hentikan, laporkan hardcode yang ditemukan,
dan buka tiket D-32/D-33 ke Bos.**

| ID | Task | Depends On | Gate | Acceptance | State |
|---|---|---|---|---|---|
| T-24 | Gate kepatuhan/release preset kanonik `klinik` dan `salon` | T-21c, **T-27..T-27e (D-50) untuk `klinik`** | D-50 | `klinik.json` dan `salon.json` yang sudah dibuat T-F4 divalidasi ulang, muncul di dropdown onboarding, dan dimuat `DogfoodTenantSeeder`; task ini tidak membuat salinan atau preset baru. | `DONE` |
| T-24b | Preset gelombang 2: tambah `kursus`, `kos_coworking`; validasi ulang preset kanonik `bengkel` dan `laundry` | T-24 | — | 2 JSON baru + `PresetCompositionTest` untuk empat preset: kapabilitas, `term()`, workflow default, widget dashboard sesuai §7.3/§7.6/§7.7/§7.9. Workflow `laundry` yang sudah dibuat T-F15 tetap memicu `notify.owner_wa`. **Diff kode = 0.** | `DONE` |
| T-24c | Preset gelombang 3: `katering`, `bakery_preorder`, `travel_umroh`, `gym`, `praktek_dokter`, `cuci_mobil` | T-24b, **T-27..T-27e untuk `praktek_dokter`** | D-50 | 6 JSON + test; sama seperti T-24b. | `DONE` |
| T-24d | Preset gelombang 4: sisa Tier A dari §7 (prioritas ditentukan Bos berdasarkan permintaan pasar) | T-24c | `HUMAN:PRIORITY` | Batch ≤6 preset per PR; setiap batch memperbarui tabel §6/§7 dan `PRESET_COVERAGE.md` (dibuat di T-24). | `BLOCKED` |
| T-25 | **Keputusan Tier B berikutnya** (bukan kode) | T-24b | `HUMAN:DECISION` | Bos memilih 0–2 dari: `manufacturing.production_order` (BOM multi-level + WIP, §7.8) dan `finance.loan_schedule` (angsuran/koperasi, §7.9). Hasil dicatat sebagai D-34/D-35 di `00-DECISIONS.md` dengan spesifikasi tabel di `DATA_MODEL.md`. Tanpa keputusan → tidak ada task kode. | `BLOCKED` |
| T-25b | Implementasi modul Tier B terpilih | T-25 | — | Mengikuti pola T-14b: tabel + workflow effect + widget; **dibungkus flag** sehingga preset yang tidak memakainya tidak berubah (regression T-24* tetap hijau). | `BLOCKED` |
| T-26 | Halaman publik "Cocok untuk bisnis apa?" | T-24b, T-22 | `HUMAN:UI-LOCK` (copy) | Route publik `/industri` membaca daftar preset + `description` dari `business_presets` (bukan hardcode); tiap preset punya CTA onboarding 1-klik. Grep nama industri literal di Blade = 0. | `BLOCKED` |

### Fase 6b: Add-on Komersial (D-56) — dibangun sesuai permintaan pasar

Katalog add-on resmi ada di D-56. **Urutan pembangunan ditentukan permintaan
nyata, bukan ditebak sekarang** — karena itu belum diberi ID task tetap. Setiap
add-on wajib: (a) diaktifkan lewat mekanisme kapabilitas (D-52) atau item
billing, (b) **tidak** menambah nama industri ke kode (D-31), (c) tunduk Tier B
(D-33) bila butuh aturan domain khusus.

| Add-on (D-56) | Bergantung pada | Catatan teknis |
|---|---|---|
| Cabang/lokasi tambahan | T-00a, T-16 | Multi-company sudah ada (D-06/D-41); yang perlu dibangun: penagihan per cabang + pelaporan gabungan |
| e-Faktur / Coretax (PKP) | T-11, T-13e | Integrasi eksternal; hanya relevan bila `tax_mode=taxable` (D-44) |
| Loyalty pelanggan (poin/voucher) | T-13, T-13e | Kapabilitas generik baru → **butuh keputusan D-32** sebelum dibangun |
| Integrasi marketplace/ojol | T-13e, T-19b | Pola sama dengan webhook NalarPesan: idempoten via `external_ref` (D-04) |
| Payroll lanjutan (BPJS/PPh21) | T-13f | Perluasan `hr.payroll`, bukan kapabilitas baru |
| Nomor WA disediakan platform | T-10b | **D-55**: default tetap nomor klien; ini jalur tambahan, bukan pengganti |
| Domain & struk ber-merek sendiri | T-22 | White-label lebih dalam dari D-09 |
| Penyimpanan terkelola (non-BYOS) | T-14 | Alternatif D-22 bagi klien tanpa Google Drive |

**Metrik keberhasilan fase:** rasio *preset ditambah* : *diff kode* — target
≥ 20 preset baru dengan 0 baris kode domain baru selain T-25b.

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
                                           ├─ T-21c ─┬─ T-24 ─ T-24b ─┬─ T-24c ─ T-24d
                                           │         │                ├─ T-25 ─ T-25b
                                           │         │                └─ T-26 (← T-22)
                                           └─ T-22 ──┴─ T-23
```

**Prinsip urutan (D-42):** kerangka UI -> **frontend utuh dari JSON (kontrak + mesin komposisi + 6 pola layar) -> UI-LOCK** -> fondasi tenant → **mesin komposisi** → tabel kapabilitas
generik → Tier B → API → bukti komposisi → **ekspansi pasar sebagai data** (Fase 6). Tabel domain **tidak boleh** dibuat
sebelum `WorkflowEngine`, `FeatureResolver`, `TerminologyResolver` ada, karena
tabel itu bergantung pada ketiganya.

---

## Matriks Grup Paralel (dihitung sekali — jangan diturunkan ulang tiap sesi)

Dihitung dari Dependency Graph di atas + syarat `HERMES.md` §Parallel Writer
Policy. Kolom **Lebar** = jumlah worktree yang bisa dipakai bersamaan; kolom
**Syarat** = pengecualian dari 6 syarat umum yang perlu dicek ulang sebelum
klaim.

| Grup | Fase | Task | Lebar | Syarat khusus |
|---|---|---|---|---|
| **PG-1** | 2 | T-F10, T-F11, T-F12, T-F13 | 4 | ⚠️ **Prep wajib dulu**: `resources/views/livewire/dummy-module.blade.php` baris dispatch pola layar (saat ini `@if ($screen === 'list')`) harus diubah jadi dispatch berbasis konvensi (`screen` → komponen `screens.{screen}-screen` + fallback aman) **sebelum** grup ini boleh paralel — tanpa prep, keempat task menabrak baris yang sama. Prep itu sendiri satu commit serial. **PREP SUDAH LANDED (2026-09-17):** dispatch kini `screen` -> `App\Livewire\Screens\{Studly}Screen` -> `screens.{screen}-screen` via `DummyModule::screenComponent()`, pola tanpa komponen jatuh ke kartu kontrak. Menambah pola layar = menambah satu kelas komponen, **tanpa** menyentuh Blade dispatcher. PG-1 kini boleh paralel. |
| 🔒 barrier | 2 | T-F13R → T-F14 → T-F15 | 1 | T-F13R depends T-F13 (koreksi QA sebelum konvergensi); T-F14 depends T-F7..T-F13, T-F13R; T-F15 depends T-F14. Selalu serial. |
| ⛔ gate | 2→3 | `HUMAN:UI-LOCK` | 0 | Tunggu keputusan Bos, bukan soal teknis. |
| **PG-2** | 3a | T-00b, T-00c | 2 | Keduanya depends T-00a saja; jalankan setelah T-00a (migration, serial) merge. |
| **PG-3** | 3b | T-08c, T-08e, T-03b | 3 | Semua depends T-08b saja. T-08d **dikecualikan** dari grup ini (buat migration `workflow_definitions`/`workflow_transitions_log` → serial). |
| ❌ serial penuh | 3c | T-13, T-13b, T-11, T-13c, T-13d, T-13e, T-13f, T-10, T-10a, T-12, T-14, T-14b | 1 | Urutan FK dikunci eksplisit di §Fase 3c ("**Urutan FK wajib** ... Semua migration serial"). Fase terbesar proyek, tidak bisa dipercepat lewat paralelisme worktree. |
| **PG-4** | 4 | T-16, T-20, T-10c, T-10d, T-12b | ≤5 | Semua non-migration setelah dependency masing-masing `DONE`. T-19, T-19b, T-17, T-17b, T-18, T-17c tetap dicek satu-satu (webhook/API sensitif, §HUMAN:SECRET). |
| ⚠️ mayoritas serial | 4b | T-27, T-27b, T-27c, T-27d, T-27e | 1-2 | T-27b (migration/enkripsi kolom) dan T-27c (migration `access_logs`) serial. T-27d/T-27e bisa paralel satu sama lain setelah T-27c `DONE`. |
| 🔒 barrier | 5 | T-21 | 1 | Depends **seluruh** Fase 4. Regression gate, selalu serial. |
| **PG-5** | 5 | T-21b, T-21c, T-22 | 3 | Semua depends T-21 saja; verifikasi/audit, risiko konflik file rendah. |
| **PG-6** | 6 | T-24, T-24b, T-24c, T-24d, T-25, T-25b, T-26 (preset baru per §7.11) | N (praktis tak terbatas) | Murni data — satu preset = satu file `database/presets/{slug}.json` baru. Konflik hanya bila dua worker menulis file preset yang sama; hindari dengan penamaan preset unik per klaim. |

**Cara pakai:** sebelum mengklaim task apa pun, cek grup mana ia berada di
tabel ini. Grup dengan lebar > 1 dan tanpa syarat terbuka = boleh langsung
klaim branch `task/{TASK-ID}` di worktree bebas. Grup dengan ❌/🔒/⛔ = kerjakan
serial di `main`, tunggu barrier, atau tunggu gate — jangan menebak jalan
pintas.

---

## Aturan Eksekusi Autopilot Wajib

1. **Satu writer per worktree pada satu waktu.** Paralel-write lintas worktree
   (`worker-a/b/c` + `main`) diizinkan **bersyarat** — lihat `HERMES.md`
   §Parallel Writer Policy (6 syarat: deps `DONE`, tanpa gate terbuka, bukan
   migration, bukan perubahan dependency, file target lepas dari task
   berjalan, dikerjakan di worktree+branch sendiri). Klaim task = buat branch
   `task/{TASK-ID}`; `git worktree list` adalah registry klaim yang hidup.
2. **Review sebelum tulis.** Baca requirement + keputusan terkait task, audit kode
   existing, tetapkan invariant dan negative case, lalu review diff sendiri.
3. **Paralel untuk lane read-only selalu boleh; paralel-write hanya bila lolos
   6 syarat §1.** Audit, pencarian, review UX/a11y, dan shard test (SQLite
   `:memory:` terisolasi per proses) selalu boleh paralel. Migration,
   perubahan dependency, merge ke `main`, dan task konvergensi (§Matriks Grup
   Paralel) **selalu serial** tanpa kecuali.
4. **Rekonsiliasi setelah paralel.** Hasil subagent bukan verdict final.
5. **TDD sesuai kondisi nyata.** Bug terbukti → RED lalu GREEN. Perilaku sudah
   benar → characterization test, catat no-change.
6. **Commit lokal diizinkan** (gate `HUMAN:COMMIT` terbuka). Sebelum commit:
   `git log --oneline -3`, `git status --short`, pastikan tidak ada `.env`/secret.
   **Push, deploy, migrate non-dev** tetap butuh approval eksplisit.
7. **Laporan** di setiap batas fase dan saat `BLOCKED`: task, file, command +
   hasil, risiko, blocker, next `READY`.
