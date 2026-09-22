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
| `DONE 5a0ba6d` | Semua acceptance criteria terbukti dengan output command nyata; evidence tercatat di `AUTOPILOT_STATUS.md`. |
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
| G-01 | Verifikasi canonical repository, branch, worktree, baseline test | `DONE 5a0ba6d` | `D:\PROJECTS\agentic-bos`, branch `main`, Git initialized, **remote belum dikonfigurasi** (push memerlukan remote + approval). |
| G-02 | Rekonsiliasi PRD ↔ codebase | `DONE 5a0ba6d` | `PRD_RECONCILIATION.md` |
| G-03 | Pecah scope menjadi task atomik dengan dependency | `DONE 5a0ba6d` | Dokumen ini (revisi 2026-09-16) |
| G-04 | Review arsitektur, tenant isolation, billing, migrasi | `DONE 5a0ba6d` | Keputusan D-24..D-41 di `00-DECISIONS.md` (Q-01..Q-08 dijawab Bos → D-34..D-41) |

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
| T-01 | Setup Laravel 13 + Livewire 4 + Tailwind 4 + Vite | — | `DONE 5a0ba6d` | `composer.json`, `package.json`, `npm run build` sukses |
| T-02 | Lobby & App Switcher | T-01 | `DONE 5a0ba6d` | `tests/Feature/LobbyNavigationTest.php` (4 test). Kartu → `route('app.module', slug)`, tombol search punya handler + `aria-label`. |
| T-03 | Dynamic Sidebar (katalog statis) | T-01 | `DONE 5a0ba6d` | `tests/Unit/DynamicMenuRegistryTest.php` (7) + `tests/Feature/ModuleSidebarTest.php` (4). Modul tak dikenal → **nol DOM item** (zero-bloat). |
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
| T-07 | Design token `--erp-*` + tema + Settings shell | T-03 | D-36, D-43 (5 tema, per-usaha, palet A default locked) | `resources/css/app.css`, `app/Services/ThemeRegistry.php`, `app/Livewire/Settings.php` | (a) 36 token `@theme` sesuai UX §7 untuk **5 tema** (A/B/C/D/E, nilai di UX §7.3a); (b) `ThemeContrastTest` 11 pasangan >= 4.5:1 **per tema** (5x11 kasus); (c) `/app/settings` tab Tampilan `role=tablist`/kartu tema dari array registry, **bukan hardcode 5 kartu**; (d) **selektor tema adalah pengaturan per-USAHA (D-43)**: owner memilih 1 dari 5 tema, tersimpan server-side (`storage/app/json/{company}/settings.json` di Fase 2; `companies.theme` di Fase 3), diterapkan via `<html data-theme>` untuk **semua** staf company itu — bukan `localStorage` per-browser; test: user lain di company sama melihat tema yang sama setelah reload; **tidak ada** toggle gelap/terang personal per akun; (e) Pint + build hijau. | `DONE 5a0ba6d` |
| T-F1 | **Kontrak data**: interface + binding `.env` | T-01 | D-42 | `app/Contracts/{EntityRepository,PresetSource,CompanyContext}.php`, `app/Providers/DataSourceServiceProvider.php`, `config/datasource.php` | `DATA_SOURCE=json` mengikat `Json*`; `eloquent` mengikat class yang belum ada -> exception jelas. `EntityRepository::for(company, entity)->all()/find()/save()/query(filters)`. Test binding per env. | `DONE 5a0ba6d` |
| T-F2 | Skema kapabilitas JSON + validator | T-F1 | D-32 | `database/schemas/*.schema.json` (contacts, deals, projects, project_milestones, resources, bookings, items, item_batches, orders, order_lines, employees, cash_entries, invoices, quotations, timesheet_entries), `app/Services/Schema/EntitySchema.php`, `SchemaValidator` | Setiap entitas §1 punya skema; `attributes` hanya key yang dideklarasikan; `company_id` implisit dari folder. Test: baris tanpa field wajib ditolak; key `attributes` asing ditolak. Skema ini **adalah** sumber migration Fase 3 (T-13*). | `DONE 5a0ba6d` |
| T-F3 | `JsonEntityRepository` + `JsonCompanyContext` | T-F2 | D-41 | `app/Services/Json/JsonEntityRepository.php`, `JsonCompanyContext.php`, `storage/app/json/{bengkel-arka,klinik-sehat,salon-ayu}/*.json` | Baca/tulis/filter/sort/paginate; tulis atomik (temp+rename); **tidak bisa** membaca folder company lain (test: path traversal `../` ditolak). Company aktif dari `?company=` (dev) lalu session. | `DONE 5a0ba6d` |
| T-F4a | **Kontrak preset & QA readiness** (docs-only, hasil QA read-only OpenCode) | T-F3 | D-31, D-32, D-42, D-46 | `docs/INDUSTRY_PRESETS.md`, `docs/EXECUTION_PLAN.md`, `docs/DATA_MODEL.md`, `docs/AUTOPILOT_STATUS.md` | (a) §2 mendefinisikan bentuk `terminal`, urutan `stages[]` sebagai dasar arah transisi, dan `requires_note: true` untuk setiap transisi mundur; (b) seluruh dokumen menetapkan **satu path kanonik `database/presets/{slug}.json`**, dan T-08 dinyatakan membaca file yang sama tanpa folder salinan preset lain; (c) kontrak data T-F4 mewajibkan workflow hilir: bengkel punya lompatan `masuk -> pengerjaan` + rework dari `qc`, klinik dan salon punya workflow `bookings`, semuanya mendeklarasikan terminal; (d) state/referensi task basi yang terkait T-F4/T-08/T-21c diselaraskan tanpa mengubah keputusan arsitektur; (e) `git diff --check` hijau. | `DONE 5a0ba6d` |
| T-F4 | 3 preset JSON + `PresetDefinitionValidator` + `JsonPresetSource` | T-F4a | D-32, D-46 | `database/presets/{bengkel,klinik,salon}.json`, `app/Services/Preset/PresetDefinitionValidator.php`, `app/Services/Json/JsonPresetSource.php` | Skema §2 dipatuhi; **D-46 integritas alur:** validator menolak stage tak terjangkau, dead end (stage non-terminal tanpa transisi keluar), dan `terminal` yang tidak dideklarasikan (3 test negatif); transisi mundur wajib `requires_note: true`. Validator **menerima kunci Tier A + Tier B** (D-32/D-33 — menolak Tier B akan membuat preset apotek/kontraktor gagal validasi) dan menolak capability/term/widget/effect di luar katalog §1/§3/§4/§5 (test negatif per katalog). Tiga preset wajib memenuhi §6.1: bengkel `orders` memiliki lompatan `masuk -> pengerjaan`, jalur `qc -> siap_diambil`, dan rework `qc -> pengerjaan` dengan `requires_note: true`; klinik dan salon memiliki workflow `bookings`; semua workflow mendeklarasikan `terminal`. **Definisi dari T-08 dipindah ke sini; T-08 Fase 3 hanya menambah tabel + seeder dari file yang sama.** | `DONE 5a0ba6d` |
| T-F5 | `FeatureResolver` + `TerminologyResolver` + `term()` + `@term` | T-F4, T-F3 | — | `app/Services/FeatureResolver.php`, `TerminologyResolver.php`, `app/Support/helpers.php` | Resolusi override company (`storage/app/json/{c}/settings.json`) -> preset -> default global §3. Test: `klinik`->`term('contact')='Pasien'`, `bengkel`->`'Pelanggan'`; key asing -> exception di dev. **Menggantikan T-08b/T-08c Fase 3b**; Fase 3 hanya mengganti sumber. | `DONE 5a0ba6d` |
| T-F6 | `WorkflowEngine` (in-memory + log JSON) + efek `approval.request` | T-F5 | — | `app/Services/Workflow/WorkflowEngine.php`, `Effects/*.php`, `app/Contracts/HasWorkflow.php` | Transisi dari `preset.workflows`; **transisi melompat diizinkan bila preset mendeklarasikannya** (mis. bengkel: `masuk -> pengerjaan` untuk servis kilat) - UI menawarkan hanya transisi sah dari stage saat ini, jadi "lompat tahap" adalah data, bukan kode; tolak transisi tak terdefinisi/role salah; transisi mundur tanpa catatan alasan ditolak (D-46); `requires_approval` menahan; log ke `{c}/workflow_log.json`. Test 6 kasus. **Menggantikan T-08d**; Fase 3 menambah tabel log + `DB::transaction`. | `DONE 5a0ba6d` |
| T-F7 | `DashboardComposer` + `WidgetRegistry` + widget katalog §4 (data dari repository) | T-F5 | — | `app/Services/Dashboard/*`, `app/Livewire/Widgets/*.php`, `app/Livewire/Dashboard.php`, `resources/views/livewire/dashboard.blade.php` | `/app/dashboard` merender zona universal (3 KPI + laporan asisten dari `{c}/assistant_report.json`) + widget sesuai `preset.dashboard`; widget menghitung dari repository (bukan angka dummy statis); **grep literal istilah = 0**; test render untuk 3 preset. Acuan visual: branch `mockup/ux-dummy` (bengkel). **Menggantikan T-05 dan T-08e.** | `DONE 5a0ba6d` |
| T-F8 | Sidebar flag-aware + `term()` + route `/app/{module}` -> pola layar | T-F5 | — | `app/Services/DynamicMenuRegistry.php`, `app/Livewire/Sidebar.php`, `Lobby.php`, `routes/web.php`, `app/Http/Middleware/EnsureFeatureEnabled.php` | Registry per kapabilitas (§9); modul off -> tidak ada DOM & 403; label via `term()`; route memetakan modul -> `{pola layar, entity}`. Test: `salon` tanpa `projects` -> menu & route hilang; `klinik` menampilkan `Pasien`. **Menggantikan T-03b dan bagian route T-16.** | `DONE 5a0ba6d` |
| T-F9 | `ListScreen` + `<x-data-table>` responsif + form dari skema | T-F8, T-F2 | — | `app/Livewire/Screens/ListScreen.php`, `resources/views/livewire/screens/list.blade.php`, `resources/views/components/data-table.blade.php`, `form-field.blade.php` | Kolom & form **digenerate dari `{entity}.schema.json`** + label `term()`; tabel desktop / card mobile; search, sort, paginate; create/edit/delete lewat repository; empty-state via `term()`. Test: entitas `contacts` untuk 3 preset; `employees` untuk `salon`. **Menggantikan T-06.** | `DONE 5a0ba6d` |
| T-F9b | **Data demo layak review** (permintaan Bos 2026-09-17, di luar rencana awal) | T-F9 | D-42 | `storage/app/json/{bengkel-arka,klinik-sehat,salon-ayu}/*.json`, `app/Services/Dashboard/WidgetRegistry.php` | 48 fixture terisi data operasional yang koheren (bukan stub 1 baris): minimal 8 `contacts`, 4 `employees`, 6 `bookings`, 8 `cash_entries` per company; seluruh baris lolos `SchemaValidator`; **integritas referensi diuji** (setiap FK non-null menunjuk baris yang ada); stage hanya memakai kode dari workflow preset; widget dashboard menghasilkan angka nyata. Data industri hanya di `storage/app/json/` (diizinkan D-42). | `DONE 5a0ba6d` |
| T-F10 | `PipelineScreen` (kanban) + `CalendarScreen` | T-F9, T-F6 | — | `app/Livewire/Screens/{PipelineScreen,CalendarScreen}.php` + Blade | Kolom kanban = stage dari `WorkflowEngine`; drag/pindah = `transition()` (ditolak -> toast, tidak berubah); kalender harian/mingguan untuk `bookings`/`scheduling` dengan **anti-double-booking** di repository (test overlap). Test: work-order `bengkel`, janji temu `klinik`, booking kursi `salon`. | `DONE 5a0ba6d` |
| T-F11 | `CashierScreen` + `LedgerScreen` | T-F9 | D-03, D-44, D-45 | `app/Livewire/Screens/{CashierScreen,LedgerScreen}.php`, `app/Services/TaxRateService.php` | Scope awal `084181f` + koreksi QA finansial T-F11R. | `DONE 5a0ba6d` |
| T-F11R | **Koreksi QA finansial T-F11** | T-F11 | D-03, D-44, D-45 | kontrak/adapter repository aggregate, `CashierScreen`, `BusinessIdentityStore`, `LedgerScreen`, dialog, test negatif | (a) checkout mengambil ulang item/harga dari repository dan allowlist metode bayar; (b) order+lines all-or-nothing dengan journal recovery, nomor UUID non-racy, replay token no-op; (c) identity company-scoped dan file/id/tax_mode hilang fail-closed; (d) invoice SaaS tidak dihitung sebagai ledger operasional atau diberi CRUD generik; route kanonik sementara fallback read-only; (e) total order = jumlah line setelah pembulatan dan cap aritmetika aman; (f) dialog simple memakai aksi merah + focus trap/restore; (g) decimal precision aman untuk integer besar; negative tests wajib. | `DONE 5a0ba6d` |
| T-F12 | Settings 6 tab (Profil, Identitas Usaha, Preset & Istilah, Alur, Asisten AI, Tampilan) + onboarding form (D-40) | T-F9, T-F7 | D-40 | `app/Livewire/Settings.php` + tab components, `app/Livewire/Onboarding.php` | Tab dari registry + role/flag; dropdown preset **dari `PresetSource`** (bukan hardcode 3); tab Istilah mengedit override -> `term()` berubah tanpa reload server; tab Alur menampilkan graf stage dari preset; onboarding membuat folder company baru + `settings.json`. | `DONE 5a0ba6d` |
| T-F13 | Universal Search dari repository | T-F9 | — | `app/Livewire/CommandPalette.php` | Hasil dari entitas yang kapabilitasnya aktif, label `term()`, `href` nyata (grep `href="#"` = 0), scoped company. **Menggantikan bagian UI T-20**; Scout menyusul Fase 3. | `DONE 5a0ba6d` |
| T-F13R | **Koreksi QA T-F13** (registry `timesheets`→`timesheet_entries`, catch generik di jalur search, negative test tenant stale-component) | T-F13 | D-31, D-42 | `app/Services/DynamicMenuRegistry.php`, `app/Livewire/CommandPalette.php`, `tests/Feature/CommandPaletteDataSearchTest.php` | (a) dua item registry (`projects/timesheet`, `hrd/attendance`) memakai entity `timesheet_entries` yang punya schema, bukan `timesheets`; (b) `/app/hrd/attendance` render 200 dibuktikan test acceptance dengan datasource terisolasi; (c) baris timesheet unik ditemukan Command Palette sebagai `type=Data` dengan `url` yang di-GET 200, diverifikasi dari `viewData('results')` bukan parsing `<a>` pertama; (d) `catch (Throwable)` generik di jalur repository `searchEntityData()` dihapus - schema hilang/JSON rusak melempar exception fail-visible, bukan hasil kosong; (e) negative test stale-component: company aktif berpindah A→B setelah `CommandPalette` mount, hasil hanya menampilkan data B; (f) komentar `MAX_PER_ENTITY`/`MAX_DATA_RESULTS` diperbaiki agar tidak mengklaim membatasi baris yang dipindai; (g) total hasil didokumentasikan jujur sebagai maks 16 (8 menu + 8 data, dua kuota terpisah). | `DONE 5a0ba6d` |
| T-F14 | **Uji anti-hardcode otomatis** | T-F7..T-F13, T-F13R | D-31 | `tests/Architecture/NoIndustryHardcodeTest.php`, `NoLiteralTermsTest.php`, `RenderAllPresetsTest.php` | (1) grep regex nama industri (`bengkel|klinik|salon|agency|apotek|pharmacy|rental|kontraktor|...`) di `app/` + `resources/` = 0; (2) grep istilah kamus §3 literal di Blade = 0; (3) **audit a11y**: setiap overlay/modal/drawer punya `role="dialog"`+`aria-modal`+`aria-labelledby`; setiap nav punya tepat satu `aria-current="page"`; tombol pembuka drawer punya `aria-expanded`+`aria-controls` (UX §6.10); (4) setiap route `/app/*` dirender untuk 3 preset -> 200/403 sesuai kapabilitas, tanpa exception. Test ini **permanen** dan berjalan di `php artisan test` selamanya. | `DONE 5a0ba6d` |
| T-F15 | **Bukti D-31 awal: `laundry.json`** | T-F14 | D-31 | `database/presets/laundry.json`, `storage/app/json/laundry-bersih/*.json` | Tambah preset + data dummy -> aplikasi lengkap (menu, dashboard, kanban `order`, kasir) muncul. **`git diff --stat` di luar `database/presets/`, `storage/app/json/`, dan test = kosong.** Bila tidak -> `BLOCKED` dengan daftar hardcode. | `DONE 5a0ba6d` |

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
| T-00a | Migration + model `companies`, `business_identities`; `ALTER users` (+`wa_number`, `wa_is_verified`, `current_company_id`) | — | D-41 (ex-Q-08, **locked**; default**: kolom `current_company_id`) | `HUMAN:UI-LOCK` | `database/migrations/`, `app/Models/Company.php`, `app/Models/BusinessIdentity.php`, `app/Models/User.php` | Schema sesuai `DATA_MODEL.md` §1.1–1.2, 1.4 via Schema Builder; `migrate:fresh` hijau di SQLite; factory untuk `Company`; test relasi `Company→identities`, `User→companies`. | `DONE 5a0ba6d` |
| T-00b | Migration + model `module_settings` (bentuk D-19/D-25) | T-00a | — | — | `database/migrations/`, `app/Models/ModuleSetting.php` | `UNIQUE(company_id, module_name)`; `settings_json` cast array; test tulis/baca modul `features`. | `DONE 5a0ba6d` |
| T-00c | Auth scaffold minimal (login email+password, D-21) + middleware `SetCurrentCompany` | T-00a | — | — | `routes/web.php`, `app/Http/Middleware/SetCurrentCompany.php`, `resources/views/auth/` | Login → redirect `/app/dashboard`; guest → `/login`; `current_company_id` tersedia via `auth()->user()`; test guest 302, user 200. **Tanpa** Breeze/Jetstream — Livewire form sendiri agar tidak menambah dependency. | `DONE 5a0ba6d` |

---

## Fase 3b: Mesin Komposisi (D-31 — WAJIB sebelum tabel domain apa pun)

> Inilah yang membuat industri ke-7..50 menjadi data, bukan kode. Tanpa fase
> ini, setiap tabel domain akan mengunci pola "1 industri = N tabel".

| ID | Task | Depends On | Decisions | File Target | Acceptance | State |
|---|---|---|---|---|---|---|
| T-08 | `business_presets` + seeder dari `database/presets/*.json` + `EloquentPresetSource` | T-00a, T-F4 | D-42 | migration, `app/Models/BusinessPreset.php`, `app/Services/Preset/PresetDefinitionValidator.php`, `database/seeders/BusinessPresetSeeder.php`, `database/presets/{agency,fnb,pharmacy,eo,contractor,rental,custom}.json` | Skema `definition` sesuai `INDUSTRY_PRESETS.md` §2; tujuh file tambahan dibuat di path kanonik yang sama dengan preset Fase 2; seeder membaca dan memvalidasi ulang seluruh `database/presets/*.json`; jumlah baris sama dengan jumlah file kanonik; `tier` benar; tidak ada salinan preset di folder seeder. | `DONE 5a0ba6d` |
| T-08b | Adapter Eloquent untuk `FeatureResolver` yang dibangun di T-F5; `Company::feature()` membaca `module_settings` via `EloquentCompanyContext` | T-00b, T-08 | D-42 | `app/Services/FeatureResolver.php`, `app/Models/Company.php` | Resolusi: `module_settings[features]` → `preset.definition.capabilities` → `false`. Test: A override tidak bocor ke B; key asing → `false`; cache per request; ganti `business_preset` company → resolusi berubah tanpa migration. | `DONE 5a0ba6d` |
| T-08c | Adapter Eloquent untuk `TerminologyResolver` yang dibangun di T-F5; override dari `module_settings[terminology]` | T-08b | D-42 | `app/Services/TerminologyResolver.php`, `app/Support/helpers.php` (`term()`), Blade directive `@term` | Resolusi company → preset → default global (`INDUSTRY_PRESETS.md` §3); key tak dikenal → exception di dev, fallback key di prod; test: `rental` → `term('contact')='Penyewa'`, `pharmacy` → `'Pasien'`; override company menang. **Temuan:** `TerminologyResolver` sudah generik lewat `CompanyContext`/`PresetSource`/`CompanySettingsStore` (adapter Eloquent-nya sudah ada dari T-08b) — tidak perlu adapter baru, cukup test regression `tests/Feature/TerminologyResolverEloquentTest.php` (2 test). | `DONE 5a0ba6d` |
| T-08d | Tabel `workflow_definitions` + `workflow_transitions_log`; `WorkflowEngine` (T-F6) diberi `DB::transaction` + log ke tabel | T-08b | D-42 | migration ×2, `app/Services/Workflow/WorkflowEngine.php`, `app/Services/Workflow/Effects/*.php`, `app/Contracts/HasWorkflow.php` | `transition($model,$to,$actor)`: tolak transisi tak terdefinisi (exception), tolak role salah (403), `requires_approval` → buat tiket & tahan, jalankan `effects` dalam `DB::transaction`, tulis log. Materialisasi dari preset saat company dibuat. Test: 6 kasus + rollback bila efek gagal. Efek aman awal yang diaktifkan: `approval.request`; `notify.owner_wa` tetap helper langsung dan ditunda sebagai effect workflow sampai ada outbox/idempotensi. **Keputusan arsitektur (2026-09-18):** WorkflowEngine tetap SATU implementasi (bukan duplikasi Json/Eloquent) — logging ke tabel DB (`workflow_transitions_log`, `approval_tickets`) dibuat conditional pada `config('datasource.driver')==='eloquent'`; JSON-mode Fase 2 tetap murni log JSON, tidak butuh migration. `NotifyOwnerWa` fail-closed terverifikasi dengan 2 negative test (WA belum verified, delivery gagal), tetapi tidak diregistrasikan sebagai effect workflow sebelum outbox tersedia. | `DONE 5a0ba6d` |
| T-08e | Regression `WidgetRegistry` + `DashboardComposer` dari T-F7 dengan sumber Eloquent | T-08, T-F7 | D-42 | test integrasi dashboard Eloquent | Dengan `DATA_SOURCE=eloquent`, komposisi widget identik dengan sumber JSON untuk preset yang sama; tidak ada implementasi composer kedua. Implementasi `EloquentEntityRepository` ditambahkan. | `DONE 5a0ba6d` |
| T-03b | Regression `DynamicMenuRegistry` dari T-F8 dengan sumber Eloquent | T-08b, T-F8 | D-42 | test integrasi menu Eloquent | Dengan `DATA_SOURCE=eloquent`, registry per kapabilitas menghasilkan menu dan istilah yang sama dengan sumber JSON; tidak ada registry kedua atau kondisi industri. **Temuan:** `DynamicMenuRegistry` juga generik (hanya bergantung `CompanyContext`/`PresetSource`, bukan `EntityRepository`) — selesai penuh dengan test `tests/Feature/EloquentDynamicMenuRegistryTest.php` (5 test). | `DONE 5a0ba6d` |
| T-09 | ~~kolom `business_preset`~~ → digabung ke T-00a. | — | — | — | — | `DONE (merged)` |

**Urutan wajib Fase 3b: T-08 → T-08b → (T-08c ∥ T-08d ∥ T-08e read-only design boleh paralel, implementasi serial) → T-03b.**

---

## Fase 3c: Tabel Kapabilitas (generik — bukan per industri)

| ID | Task | Depends On | Decisions | File Target | Acceptance | State |
|---|---|---|---|---|---|---|
| T-13 | `contacts`, `deals`, `activity_logs` (§3) | T-08d | D-38 (kode netral, locked) | migration ×3 + model + `HasWorkflow` di `Deal` | `stage VARCHAR`, transisi via `WorkflowEngine`; `type` + `attributes`; test A/B; test bahwa preset `agency` dan `pharmacy` memakai tabel **yang sama** dengan `term()` berbeda. | `DONE e8fe772` |
| T-13b | `projects`, `project_milestones`, `project_assignments`, `project_vendors`, `timesheet_entries` (§5) | T-13 | — | migration ×5 + model | `Project` ber-workflow; milestone `trigger_type` valid; test: milestone `progress_pct` mencapai `trigger_value` → status `invoiced` via efek `invoice.create_*` (efek diimplementasi di task ini). | `DONE 35c1fa7` |
| T-13c | `resources`, `bookings`, `booking_incidents` (§6) + `BookingService` | T-13 | — | migration ×3 + model + service | **Invarian anti-double-booking** ditegakkan service + test (2 booking overlap → exception); `Booking` ber-workflow; helper deposit tersedia tetapi key workflow deposit ditunda sampai kontrak input runtime aman; `bookings.late_fee.compute` diimplementasi; test rundown EO = booking ber-`project_id`. | `DONE 7231b5d` |
| T-13d | `items`, `item_batches`, `stock_movements`, `bom_lines` (§7) + `StockService` | T-00a | — | migration ×4 + model + service | `StockService` mendukung mutasi FEFO/BOM; key workflow `stock.reserve/deduct` ditunda sampai kontrak reservasi transaksional dan idempoten tersedia; FEFO: deduct mengambil batch `expires_on` terdekat (test dengan 3 batch); BOM: produce 1 produk → consume komponen sesuai `bom_lines`; test A/B. | `DONE 89d64d6` |
| T-13e | `pos_shifts`, `orders`, `order_lines` (§8) + `OrderService` | T-13, T-13c, T-13d, T-11 | — | migration ×3 + model + service | `Order` ber-workflow; `dpp/tax/grand_total` dihitung `TaxRateService` dari `business_identity` (D-03, REQUIREMENTS §1); `external_ref` idempoten untuk webhook NalarPesan (D-04); `pos.tables`: `resource_id` meja + `fired_at` re-fire; efek `journal.post` dari order `paid`; test: tax inclusive vs exclusive, replay webhook no-op. | `DONE 9503b08` |
| T-13f | `employees`, `payrolls`, `ai_reminders` (§9) | T-00a | — | migration ×3 + model | `UNIQUE(company, employee, period)`; test A/B. | `DONE 01a627d` |
| T-11 | Akuntansi `chart_of_accounts`, `accounting_journals`, `accounting_journal_lines` (§4) + `JournalService` + `TaxRateService` | T-13b (FK `project_id`) | — | migration ×3 + model + 2 service | `company_id` di lines (D-26); `post()` menolak unbalance; `TaxRateService::calculateTax()` sesuai REQUIREMENTS §1.3 (test 4 skenario); template jurnal untuk cashbook (Debit beban / Kredit kas); test A/B. | `DONE 27d604f` |
| T-10 | `membership_plans`, `company_memberships` (§11.1–11.2) | T-00a | — | migration + model | Test A/B. | `DONE 5a0ba6d` |
| T-10a | `token_ledger_entries` + `TokenLedgerService` (§11.3) | T-10 | — | migration + model + service | Idempoten via `idempotency_key`; saldo cache + ledger dalam 1 transaksi; test dua panggilan key sama → saldo berubah sekali. | `DONE 5a0ba6d` |
| T-10b | `hermes_nodes`, `hermes_profiles`, `hermes_profile_companies`, `hermes_conversation_contexts` (§12) | T-00a | D-37, **D-53** (kuota GRUP dari paket vs bot ke-2 = add-on), **D-55** (nomor WA **milik klien** via scan QR; platform menyediakan nomor hanya sebagai add-on — jalur default tidak boleh mengasumsikan platform punya stok nomor) | migration x4 + model | `*_secret_reference` bukan plaintext; `owner_user_id` (bukan `company_id UNIQUE`); test: owner 2 company → 1 profile + 2 pivot; `type=addon` tanpa `billing_addon_id` ditolak; tepat satu `primary` per owner divalidasi model. | `DONE 5a0ba6d` |
| T-12 | `invoices` (§2.3) | T-10, T-13b | — | migration + model | `type` topup/subscription; transisi status valid; `paid→paid` no-op. | `DONE 5a0ba6d` |
| T-14 | `attachments` + trait `HasAttachments` (§13) | T-00a | — | migration + model + trait | Test morph ke `Contact`, `Prescription`; A/B. | `DONE 5a0ba6d` |
| T-14b | **Tier B**: `prescriptions` + `retentions` (§10) + aturan domain | T-13, T-13b, T-13d, T-13e, T-14 | — | migration ×2 + model + `PrescriptionGuard`, `RetentionService` | Obat `drug_class ∈ {keras, psikotropika}` **ditolak** masuk `order_lines` tanpa `prescription_id` `verified` (test negatif); retensi dipotong otomatis dari invoice milestone bila `retention_pct>0`, `status=held`, tidak bisa `invoiced` sebelum `release_on` (test). | `DONE 5a0ba6d` |

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
| T-16 | Middleware `EnsureFeatureEnabled:{capability}` + `EnsureCompanyAccess` | T-08b, T-00c, T-03b | — | — | `app/Http/Middleware/`, `bootstrap/app.php`, `routes/web.php` | Kapabilitas off → 403; on → 200; user tanpa akses company → 403; modul tak dikenal → 404 (mengganti 200 saat ini). Route `/app/{module}` di-resolve ke kapabilitas via registry. Test 4 kasus. | `DONE 5a0ba6d` |
| T-19 | Webhook payment gateway | T-10a, T-12 | D-34 (Midtrans, locked) | `HUMAN:SECRET` → fake | `routes/api.php` (`php artisan install:api`), `app/Http/Controllers/Api/PaymentWebhookController.php`, `app/Services/Payment/MidtransSignatureVerifier.php` | Signature `SHA512(order_id+status_code+gross_amount+server_key)`; salah → 403; `order_id` tak dikenal → 404; `settlement` → invoice `paid` + ledger credit 1 transaksi; replay → 200 no-op; test 5 kasus dengan `MIDTRANS_SERVER_KEY=test`. | `DONE 5a0ba6d` |
| T-19b | Webhook NalarPesan → `orders` (D-04) | T-13e | — | `HUMAN:SECRET` → fake | `app/Http/Controllers/Api/NalarPesanWebhookController.php` | HMAC fail-closed; `external_ref` idempoten (replay → no-op); order masuk `stage='open'` dengan `resource_id` meja bila `pos.tables`; test 4 kasus. | `DONE 5a0ba6d` |
| T-17 | API Master Bot (BOS Care) | T-10a, T-19, `support_tickets` (dipindah ke T-17 sendiri) | D-34 | `HUMAN:SECRET` → fake | migration `support_tickets`, `routes/api.php`, `app/Http/Controllers/Api/MasterBot/*`, `app/Http/Middleware/AuthenticateMasterBot.php` | Header `X-Master-Bot-Key` via `hash_equals`; endpoint tiket, saldo, topup-invoice; semua wajib `company_id` & 403 bila WA user tidak memiliki company; test 6 kasus. | `DONE 5a0ba6d` |
| T-17b | API Tenant Bot (MCP ERP) — `mcp_configure_modules`, `mcp_update_company_settings`, `mcp_create_contact`, `mcp_create_deal`, `mcp_record_expense`, `mcp_create_reminder` | T-08b, T-08c, T-13, T-11, T-13f | — | `HUMAN:SECRET` → fake | `routes/api.php`, `app/Http/Controllers/Api/TenantBot/*`, `app/Http/Middleware/AuthenticateTenantBot.php` | Auth per `hermes_profiles.webhook_secret_reference`; **setiap** tool wajib `company_id` & ditolak 403 bila caller `wa_number` bukan anggota company (COMMERCIAL §4 Lapis 3); `PUT /api/bot/settings|features` hanya untuk `wa_number` role owner (REQUIREMENTS §3.1–3.2); `mcp_configure_modules` menulis `module_settings[features|terminology]` → menu berubah tanpa kode (test dengan preset `custom`); aksi destruktif → `approval_flow` tiket `YA <kode>` (D-27). Test 8 kasus. | `DONE 5a0ba6d` |
| T-18 | `billing:check-expiring` + **tangga penurunan layanan (D-49)** | T-12, T-17 | D-49 | `HUMAN:SECRET` → fake | `app/Console/Commands/BillingCheckExpiring.php`, `app/Services/Billing/DunningLadder.php`, `app/Contracts/HermesNodeClient.php`, `app/Services/Hermes/FakeHermesNodeClient.php`, `routes/console.php` | H-3 → invoice `subscription` `pending` + `sendWhatsApp()`; idempoten per hari; dijadwalkan harian; test `Carbon::setTestNow`. **Tangga D-49:** H+0 `status=ai_suspended` (bot mati, web penuh); H+7 `read_only` (tulis ditolak 423, **ekspor tetap jalan**); H+30 `frozen` (login ditolak, data utuh); H+90 boleh hapus **hanya bila** `dunning_notified_at` memuat 3 peringatan (H+30/H+60/H+83). Pembayaran di tahap mana pun → `active` seketika tanpa kehilangan data. Test: 6 transisi tangga + test negatif "hapus tanpa 3 peringatan ditolak". | `DONE 5a0ba6d` |
| T-20 | Scout + Universal Search nyata | T-13, T-12, T-08b, T-08c | D-35 (`database`, locked) | `HUMAN:APPROVAL` (composer) | `composer require laravel/scout`, `Searchable` di `Contact`, `Deal`, `Project`, `Invoice`, `Item`; `app/Livewire/CommandPalette.php` | Hasil dari DB, scoped `company_id`, label via `term()`; dummy & `href="#"` dihapus; test: A tidak melihat B; hasil menampilkan `Pasien` untuk preset `pharmacy`. | `DONE b1da91d` |
| T-17c | **Panel Super Admin minimal + "Login As" beraudit (D-47)** | T-00c, T-16 | `HUMAN:UI-LOCK` (belum ada mockup panel admin) | `app/Http/Controllers/Admin/*`, migration `admin_impersonation_sessions`, `app/Http/Middleware/RequireSuperAdmin.php`, banner Blade global | Route `/admin/*` terpisah dari `/app/*`, guard role `platform_admin` (bukan `companies` biasa); tombol "Login As" per company → buat sesi impersonasi + redirect `/app/dashboard`; banner kuning permanen non-dismissable selama sesi; semua tulis ke `module_settings`/`business_preset`/`companies.theme` selama impersonasi tercatat `changed_by_type=admin_impersonation` + `admin_user_id`; aksi finansial/destruktif tetap lewat D-45 tingkat 1/2 (tidak ada bypass); admin **tidak** bisa lihat password/secret klien (test 403 pada endpoint secret); tombol "Akhiri Sesi Bantuan" mengembalikan ke `/admin`. Test 6 kasus termasuk audit log lengkap. | `DONE 5a0ba6d` |
| T-10c | **Mode hemat saat saldo token habis (D-48)** | T-10a | D-48 | — | `app/Services/Token/EmergencyModeResolver.php`, `app/Services/Token/ModelSelector.php` | Saat `current_token_balance <= 0` → `emergency_mode_active=true`, pemilihan model dipaksa ke multiplier terendah di `ai_model_pricings`, debit tetap tercatat di `token_ledger_entries` dari `emergency_balance`. Bot memberi tahu **sekali per percakapan** (U-05: tidak berulang, tidak terasa jualan). `emergency_balance` habis → bot berhenti membalas + link topup, **web tetap 200 penuh** (test). Topup → mode hemat mati seketika. Test 5 kasus termasuk "web tidak terpengaruh saat bot mati". | `DONE 5a0ba6d` |
| T-10d | **Gerbang kapabilitas per paket (D-52)** | T-10, T-08b | D-52 | — | `app/Services/PlanCapabilityGate.php`, integrasi ke `FeatureResolver` | `FeatureResolver` menghasilkan irisan: kapabilitas aktif = `preset ∩ module_settings ∩ plan.features`. Kapabilitas di luar paket → `false` (menu hilang, route 403). Saat onboarding, preset yang menuntut kapabilitas di luar paket tampil berlabel **"perlu paket lebih tinggi" + daftar kapabilitas yang kurang** (pengecualian sah U-04 karena ini informasi harga). **Dilarang** memakai nama preset/industri sebagai syarat (D-31) — test grep memastikan gerbang hanya menyebut kunci kapabilitas. Test: Starter tidak bisa `projects.progress_billing`; upgrade paket → kapabilitas muncul tanpa migration. | `DONE 5a0ba6d` |
| T-12b | **Trial 14 hari + ekspor data mandiri (D-51)** | T-10, T-00c | D-51 | — | `app/Services/Billing/TrialProvisioner.php`, `app/Livewire/Settings/DataExport.php`, `app/Jobs/BuildCompanyExport.php` | Trial: 14 hari fitur penuh, `trial_token_quota`, tanpa kartu; satu owner satu trial (dicegah via `wa_number` **dan** email, bukan email saja — test duplikat); berakhir → masuk D-49 tahap H+7 (hanya-baca), bukan hilang. **Ekspor:** owner unduh seluruh data usahanya dari `/app/settings` kapan saja (CSV per entitas + JSON preset/pengaturan), **tetap berfungsi saat status `read_only`/menunggak** (test eksplisit). Tidak perlu minta admin platform. Lampiran tetap di Drive klien (D-22). | `DONE 5a0ba6d` |


---

## Fase 4b: Kepatuhan Data Pribadi (D-50) — WAJIB sebelum menjual preset klinik/apotek

> Produk menyimpan data pribadi pelanggan klien, dan untuk klinik/apotek juga
> **data kesehatan** — data pribadi spesifik menurut UU 27/2022 (PDP). Platform
> berperan sebagai **prosesor**, tenant sebagai **pengendali**. Fase ini bukan
> opsional: tanpa ini, preset klinik/apotek tidak boleh dijual.

| ID | Task | Depends On | Decisions | Acceptance | State |
|---|---|---|---|---|---|
| T-27 | Penandaan kapabilitas sensitif + kebijakan privasi & persetujuan | T-08, T-00c | D-50(a) | Katalog kapabilitas diberi atribut `sensitive: true` (mis. yang menyimpan kondisi kesehatan); teks kebijakan privasi + persetujuan ditampilkan saat onboarding dan **waktu persetujuannya dicatat** (`companies.privacy_accepted_at`, `accepted_by_user_id`, versi teks). Test: company tanpa persetujuan tidak bisa mengaktifkan kapabilitas `sensitive`. | `DONE 5a0ba6d` |
| T-27b | Enkripsi at-rest kolom sensitif | T-27, T-13 | D-50(b) | Kolom identitas pasien/catatan medis/NIK dienkripsi di level aplikasi (cast terenkripsi Laravel), bukan hanya TLS. Kunci dari `APP_KEY`/KMS, **tidak** di repo. Test: baris di DB tidak terbaca polos; pencarian tetap berfungsi lewat kolom hash/blind-index terpisah bila diperlukan. | `DONE 5a0ba6d` |
| T-27c | `access_logs` — siapa membuka data sensitif | T-27 | D-50(c) | migration `access_logs` (company_id, user_id, subject_type, subject_id, action, ip, created_at); setiap pembacaan entitas bertanda `sensitive` tercatat. Test: buka rekam pasien → 1 baris log; ekspor massal → tercatat sebagai satu peristiwa. Log ini **tidak** boleh bisa dihapus dari UI tenant. | `DONE 5a0ba6d` |
| T-27d | Hak subjek data: ekspor & hapus per pelanggan | T-12b, T-27c | D-50(d) | Owner tenant dapat mengekspor **atau menghapus** data satu pelanggan/pasien atas permintaan orang tersebut, dari UI. Penghapusan memakai D-45 tingkat 1 (ketik YA) dan menyisakan catatan audit tanpa data pribadi. Test: setelah hapus, data pribadi hilang dari semua entitas terkait; jejak transaksi keuangan tetap ada dalam bentuk teranonimkan (kewajiban pembukuan tetap terpenuhi). | `DONE 5a0ba6d` |
| T-27e | Kendali pengiriman data ke AI | T-27, T-17b | D-50(f) | Data pada kapabilitas bertanda `sensitive` **tidak** dikirim ke model AI kecuali owner mengaktifkan secara eksplisit per-kapabilitas; default **mati**. Test: dengan flag mati, payload MCP tidak memuat field sensitif (assert field-by-field), dan bot menjawab bahwa ia tidak memiliki akses tersebut. | `DONE 5a0ba6d` |

**Catatan:** T-27..T-27e tidak memblokir peluncuran preset non-kesehatan
(bengkel, salon, laundry, kontraktor, ritel). Yang diblokir hanya penjualan
preset klinik/apotek sampai fase ini hijau.

---

## Fase 5: UAT, QA, dan Peluncuran

| ID | Task | Depends On | Gate | Acceptance | State |
|---|---|---|---|---|---|
| T-21 | Full regression hijau | semua Fase 4 | — | `php artisan test` exit 0; `vendor/bin/pint --test` bersih. | `DONE 5a0ba6d` |
| T-21b | **Paritas MySQL** (dari B-01) | T-21 | `HUMAN:SECRET` (koneksi MySQL lokal) | `migrate:fresh --seed` + `php artisan test` hijau pada `DB_CONNECTION=mysql`; catat perbedaan `json`/`decimal` bila ada. | `DONE` (`b2ba595`, `de9684c`); **diverifikasi ulang `5595738`** — verifikasi awal dilakukan sebelum Fase 8/9, jadi `customer_invoices`, `cash_entries`, tiga entitas akuntansi, unique index baru, dan `approval_tickets.operation_id` belum pernah diuji di MySQL. Kini ada `tests/Feature/MysqlParityTest.php` (koneksi `mysql_parity`, melewati diri bila MySQL tidak ada) yang menjaganya otomatis, bukan pemeriksaan manual sekali jalan. |
| T-21c | **Bukti akhir D-31 dengan database nyata** | T-21 | — | Seeder memuat `database/presets/laundry.json` yang sudah dibuktikan pada T-F15, lalu buat company preset tersebut. Assert: Lobby/sidebar menampilkan modul dan `term()` yang benar, dashboard menampilkan widget yang benar, workflow order berjalan, dan `EnsureFeatureEnabled` memblokir modul yang off. **Diff source di luar test harus kosong.** Bila perlu perubahan kode atau salinan preset → D-31 belum terpenuhi dan task `BLOCKED` dengan daftar hardcode. | `DONE 5a0ba6d` |
| T-22 | Audit white-label & tenant isolation | T-21 | — | D-39: grep `Hermes|Nous|Nous Research|laravel/laravel` di `resources/views`, `public/`, `composer.json name` = 0 di UI tenant; **grep literal istilah** (`Klien`, `Pasien`, `Penyewa`, `Karyawan`) di Blade = 0 — semua via `term()`; semua test A/B hijau. | `DONE 5a0ba6d` |
| T-23 | Build + smoke tenant dogfood | T-22, T-21c | `HUMAN:DEPLOY` | `DogfoodTenantSeeder` (4 company untuk preset kanonik `bengkel`, `klinik`, `salon`, `laundry`); `npm run build`; login-as tiap owner → `/app/dashboard` 200 dan hanya modul preset yang tampil. **Bagian "live" butuh gate deploy.** | `DONE 776840e` |

---

## Fase 6: Ekspansi Pasar — Preset Data (pasca-T-21c)

Sumber: `INDUSTRY_PRESETS.md` §7 (peta 63 bisnis) dan §7.11 (urutan GTM).
Setiap task = **hanya** file JSON di `database/presets/` + satu test
komposisi. **Jika sebuah task di fase ini memerlukan diff kode di luar folder
preset, task itu bukan preset — hentikan, laporkan hardcode yang ditemukan,
dan buka tiket D-32/D-33 ke Bos.**

| ID | Task | Depends On | Gate | Acceptance | State |
|---|---|---|---|---|---|
| T-24 | Gate kepatuhan/release preset kanonik `klinik` dan `salon` | T-21c, **T-27..T-27e (D-50) untuk `klinik`** | D-50 | `klinik.json` dan `salon.json` yang sudah dibuat T-F4 divalidasi ulang, muncul di dropdown onboarding, dan dimuat `DogfoodTenantSeeder`; task ini tidak membuat salinan atau preset baru. | `DONE 5a0ba6d` |
| T-24b | Preset gelombang 2: tambah `kursus`, `kos_coworking`; validasi ulang preset kanonik `bengkel` dan `laundry` | T-24 | — | 2 JSON baru + `PresetCompositionTest` untuk empat preset: kapabilitas, `term()`, workflow default, widget dashboard sesuai §7.3/§7.6/§7.7/§7.9. Workflow `laundry` tetap data-only; notifikasi workflow ditunda sampai outbox/idempotensi tersedia. **Diff kode = 0.** | `DONE 5a0ba6d` |
| T-24c | Preset gelombang 3: `katering`, `bakery_preorder`, `travel_umroh`, `gym`, `praktek_dokter`, `cuci_mobil` | T-24b, **T-27..T-27e untuk `praktek_dokter`** | D-50 | 6 JSON + test; sama seperti T-24b. | `DONE 5a0ba6d` |
| T-24d | Preset gelombang 4: sisa Tier A dari §7 (prioritas ditentukan Bos berdasarkan permintaan pasar) | T-24c | `HUMAN:PRIORITY` | Batch ≤6 preset per PR; setiap batch memperbarui tabel §6/§7 dan `PRESET_COVERAGE.md` (dibuat di T-24). | `DONE c193f8e` |
| T-25 | **Keputusan Tier B berikutnya** (bukan kode) | T-24b | `HUMAN:DECISION` | Bos memilih 0–2 dari: `manufacturing.production_order` (BOM multi-level + WIP, §7.8) dan `finance.loan_schedule` (angsuran/koperasi, §7.9). Hasil dicatat sebagai D-57 di `00-DECISIONS.md` dengan spesifikasi tabel di `DATA_MODEL.md`. Tanpa keputusan → tidak ada task kode. | `DONE 5a0ba6d` |
| T-25b | Implementasi modul Tier B terpilih | T-25 | — | Mengikuti pola T-14b: tabel (`production_orders`, `production_order_lines`) + workflow effect + widget; **dibungkus flag** (`manufacturing.production_order`) sehingga preset yang tidak memakainya tidak berubah (regression T-24* tetap hijau). | `DONE 5a0ba6d` |
| T-26 | Halaman publik "Cocok untuk bisnis apa?" | T-24b, T-22 | `HUMAN:UI-LOCK` (copy) | Route publik `/industri` membaca daftar preset + `description` dari `business_presets` (bukan hardcode); tiap preset punya CTA onboarding 1-klik. Grep nama industri literal di Blade = 0. | `DONE 5a0ba6d` |

### Fase 6b: Add-on Komersial (D-56) — dibangun sesuai permintaan pasar

Katalog add-on resmi ada di D-56. **Urutan pembangunan ditentukan permintaan
nyata, bukan ditebak sekarang** — karena itu belum diberi ID task tetap. Setiap
add-on wajib: (a) diaktifkan lewat mekanisme kapabilitas (D-52) atau item
billing, (b) **tidak** menambah nama industri ke kode (D-31), (c) tunduk Tier B
(D-33) bila butuh aturan domain khusus.

| Add-on (D-56) | Bergantung pada | Catatan teknis |
|---|---|---|
| **T-28** Cabang/lokasi tambahan (D-58) | T-00a, T-16 | **DONE** `fed2bf2` — Multi-company sudah ada (D-06/D-41); membangun: `companies.parent_company_id`, penagihan per cabang (`company_memberships` per child), pelaporan gabungan owner-scoped |
| **T-32** e-Faktur / Coretax (PKP) | T-11, T-13e | **DONE** `59cb26e` — Integrasi eksternal; hanya relevan bila `tax_mode=taxable` (D-44) |
| **T-35** Loyalty pelanggan (poin/voucher) | T-13, T-13e | **DONE** `692df40` — `addon.loyalty` (D-59). Aturan poin per company (mode nominal/item/keduanya), expiry opsional per company, tersimpan sebagai data (`loyalty_rules`), bukan hardcode. |
| **T-33** Integrasi marketplace/ojol | T-13e, T-19b | **DONE** `c8be643` — Pola sama dengan webhook NalarPesan: idempoten via `external_ref` (D-04) |
| **T-30** Payroll lanjutan (BPJS/PPh21) | T-13f | **DONE** `59f2822` — Perluasan `hr.payroll`, bukan kapabilitas baru |
| **T-29** Nomor WA disediakan platform | T-10b | **DONE** `a4932e4` — **D-55**: default tetap nomor klien; ini jalur tambahan, bukan pengganti |
| **T-31** Domain & struk ber-merek sendiri | T-22 | **DONE** `26a53fd` — White-label lebih dalam dari D-09 |
| **T-34** Penyimpanan terkelola (non-BYOS) | T-14 | **DONE** `8d8b01e` — Alternatif D-22 bagi klien tanpa Google Drive |
| **T-36** Tier gratis untuk company tanpa paket (D-60) | T-10, T-10d | **DONE** — tier gratis config-driven: semua kapabilitas + `system.ai_agent` terbuka; kuota `max_wa_groups=1` + `token_quota` dari `config/billing.php` (default 500); saldo awal tier gratis = kuota (langsung bisa dipakai); membership non-aktif → 0 (D-49 fail-closed). `PlanCapabilityGate`/`TokenQuotaGate`/`WaGroupQuotaGate` + exception domain + negative test. QA Codex (5 temuan FIXED) + Hermes final-gate. Full suite 722 passed. |

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
## Fase 7: WA-First Business Assistant (D-60 Draft)

> **Visi Bos (D-60):** Produk diposisikan sebagai "Asisten Pribadi di WhatsApp", bukan sekadar software POS/ERP Web. UI Web menjadi layar pantau (backend) atau alat kasir, sementara Owner mengendalikan multi-bisnis via chat WA. Onboarding, laporan, dan *approval* diutamakan lewat WA.

| ID | Task | Depends On | Gate | File Target | Acceptance | State |
|---|---|---|---|---|---|---|
| T-36 | NLU Intent Router (WA) | T-17b | | `app/Services/AI/IntentRouter.php` | Klasifikasi intent pesan WA masuk (contoh: `transaction`, `report`, `approval`, `setup`). | `READY` |
| T-37 | Multi-Tenant Context Switcher | T-36 | | `app/Services/TenantBot/ChatSessionManager.php` | Manajemen sesi multi-cabang per nomor WA. Tanya/ingat konteks cabang aktif (cache). | `BLOCKED` |
| T-38 | Magic Import (Vision AI) | T-36 | `HUMAN:AI-COST` | `app/Services/AI/VisionParser.php` | Parse gambar foto nota/price list dari WA jadi master data `products`. | `BLOCKED` |
| T-39 | Proactive AI & Approval | T-08d, T-36 | | `app/Services/Workflow/ApprovalHandler.php` | Balasan WA "Y" memicu transisi workflow dari tiket yang tertunda. | `BLOCKED` |
| T-40 | WA-Native Onboarding | T-37, T-38 | | `OnboardingFlow.php` | Buat `Company` baru dan terapkan `preset` via chat WA. | `BLOCKED` |

## Fase 8: Tagihan Pelanggan & Profitabilitas Proyek (D-62)

> **Masalah yang ditutup:** sembilan preset jasa tanpa POS (`agency`, `contractor`,
> `desain_interior`, `fotografi`, `it_support`, `kantor_hukum`, `kos_coworking`,
> `mebel_custom`, `travel_umroh`) tidak punya cara menerbitkan tagihan ke
> pelanggan. Menu "Tagihan" sudah disembunyikan (`a636b93`) karena entity-nya
> menunjuk tagihan langganan platform, bukan piutang tenant. Selain itu
> `LedgerScreen` baca-saja, sehingga pengeluaran belum bisa diinput sama sekali.
>
> **Serialisasi:** T-42 dan T-45 menyentuh migration, jadi seluruh rangkaian ini
> **serial** per HERMES.md Parallel Writer Policy butir 3. Tidak boleh dikerjakan
> paralel dengan writer lain.

| ID | Task | Depends On | Gate | File Target | Acceptance | State |
|---|---|---|---|---|---|---|
| T-41 | Jalur input Buku Kas + pembebanan ke proyek | — | | `app/Services/DynamicMenuRegistry.php`, `app/Services/Schema/SchemaPresenter.php`, `app/Services/Schema/EntitySchema.php`, `app/Livewire/Screens/ListScreen.php`, `database/schemas/cash_entries.schema.json`, `resources/views/components/form-field.blade.php` | Submenu `accounting/entries` (screen `list`, entity `cash_entries`, `requires_all: ['finance.cashbook']`) memakai `ListScreen` generik — tidak ada kelas layar baru. Referensi yang boleh dipilih operator ditandai di schema (`references.{field}.assignable`), bukan didaftar di kode; labelnya lewat `term()`. Test: entri dengan/tanpa proyek, pilihan relasi hanya company aktif, id proyek luar company ditolak, isolasi tenant, `direction` di luar `in\|out` ditolak. Menutup cacat `LedgerScreen` yang baca-saja. | `DONE 2cc6c96` |
| T-42 | Entitas `customer_invoices` + `customer_invoice_lines` (data layer) | T-41 | | `database/schemas/customer_invoices.schema.json`, `database/schemas/customer_invoice_lines.schema.json`, migration, `app/Models/CustomerInvoice.php`, `app/Models/CustomerInvoiceLine.php` | Schema meniru `quotations`/`order_lines`; `company_id` wajib (D-26); migration portabel Schema Builder (B-01); baris `on_delete: cascade`. Entity didaftarkan di `tests/Unit/SchemaValidatorTest.php`. Test negatif lebih dulu (data finansial, HERMES): isolasi tenant kedua tabel, nomor duplikat per company ditolak sementara nomor sama antar company diterima, `grand_total` negatif ditolak, presisi DECIMAL(18,2) di batas besar. **Migration = serial.** | `DONE 20464ed` (worktree `task/T-42`) |
| T-43 | `ContractScreen` — tagihan berbaris dengan pajak | T-42 | | `app/Livewire/Screens/ContractScreen.php`, `resources/views/livewire/screens/contract.blade.php`, `app/Services/DynamicMenuRegistry.php` | Item `accounting/invoices` di-repoint ke entity `customer_invoices` dan `navigation` dinyalakan; label tetap `term('invoices')` (D-31). Total dari baris → `TaxRateService` dengan profil dari `BusinessIdentityStore`; tidak ada perhitungan pajak di layar. Test: subtotal dari baris, PPN eksklusif & inklusif (`dpp + tax == grand_total`), non-PKP pajak 0, nomor urut per company, fail-closed saat company berubah setelah mount dan saat capability dicabut, grep D-31. | `DONE` Fase 8 |
| T-44 | Pencatatan pembayaran idempoten | T-43 | | `app/Livewire/Screens/ContractScreen.php` | Pembayaran menulis `cash_entries` (`direction = in`, `project_id` dari tagihan, `source_type = 'customer_invoice'`, `source_id`), lalu memperbarui `paid_amount`/`status`. Idempotensi dikunci `source_type` + `source_id` + nominal, pola anti-double-credit UR-04. Test negatif lebih dulu: replay tidak menggandakan `cash_entries` maupun `paid_amount`, bayar melebihi `grand_total` ditolak, bayar sebagian → status `partial`, tagihan lunas tidak bisa dibayar lagi, isolasi tenant pada aksi pembayaran. | `DONE` Fase 8 |
| T-45 | Termin bertahap tersambung ke tagihan | T-44 | | `database/schemas/project_milestones.schema.json`, migration, `app/Services/DynamicMenuRegistry.php`, `app/Services/Workflow/Effects/InvoiceCreate*.php` | `project_milestones.invoice_id` di-repoint ke `customer_invoices` (schema + migration). Item `projects/billing` → screen `list` atas `project_milestones`, navigasi dinyalakan. Aksi "terbitkan tagihan dari termin" membuat tagihan satu baris senilai `amount`, mengisi `invoice_id` dan `status = 'invoiced'`. `InvoiceCreatePartial`/`InvoiceCreateFull` dibereskan: didaftarkan ke `WorkflowEngine` atau dihapus — tidak disisakan sebagai kelas mati dengan komentar T-12 yang menyesatkan. Test: termin tidak bisa diterbitkan dua kali, termin milik company lain ditolak. Kolom `invoice_id` di-rename `customer_invoice_id` (tanpa FK, mengikuti keadaan sebelumnya). | `DONE` Fase 8 |
| T-46 | `MarginScreen` — laba-rugi proyek basis kas | T-45 | | `app/Livewire/Screens/MarginScreen.php`, `resources/views/livewire/screens/margin.blade.php`, `app/Services/DynamicMenuRegistry.php` | Pola layar `margin`, entity `projects`, `requires_all: ['projects', 'finance.cashbook']`. **Hanya membaca `cash_entries`** (D-62): pendapatan = `in` per `project_id`, biaya = `out`, laba = selisih. Piutang berjalan kolom terpisah, bukan bagian laba. Layar menyebut "basis kas" eksplisit. Test: proyek tanpa transaksi = nol (bukan error), entri tanpa `project_id` tidak bocor, **tagihan terbit belum dibayar tidak menaikkan pendapatan** (test inti basis kas), presisi jumlah besar, isolasi tenant. | `DONE` Fase 8 |
| T-47 | Rangkai ke preset + dokumentasi | T-46 | | `docs/PRESET_COVERAGE.md`, `docs/worker-reports/` | `PresetMenuContractTest` dan `RenderAllPresetsTest` tetap hijau setelah item menu berubah; `PRESET_COVERAGE.md` disegarkan dari 28 → 40 preset; laporan worker ditulis. Demo: login tenant preset `contractor`, sidebar memuat Tagihan, Termin & Opname, dan Laba-Rugi Proyek dengan data nyata. | `DONE` Fase 8 |

## Fase 9: Pengerasan Sisi Tenant (audit pasca-Fase 8)

> Sumber: audit sisi tenant 2026-09-22 setelah Fase 8 mendarat. Setiap baris di
> bawah punya bukti di kode, bukan dugaan. Tiga di antaranya menyangkut uang dan
> otorisasi, jadi wajib negative test + fail-closed sebelum kode (HERMES).
>
> **Catatan janji ke pengguna:** SOP bawaan Karyawan AI
> (`app/Livewire/Settings/AssistantSettings.php:159`) menjanjikan pengingat
> piutang H-1. Janji itu belum punya implementasi apa pun — lihat T-48/T-49.

| ID | Task | Depends On | Gate | File Target | Acceptance | State |
|---|---|---|---|---|---|---|
| T-48 | Jatuh tempo & umur piutang pada layar tagihan | — | | `app/Livewire/Screens/ContractScreen.php`, `resources/views/livewire/screens/contract.blade.php`, `app/Livewire/Settings/AssistantSettings.php` | `due_date` sudah disimpan dan dikirim ke view tapi **tidak pernah dirender** (ujung longgar dari T-43). Tampilkan jatuh tempo, tandai tagihan yang sudah lewat jatuh tempo, dan urutkan piutang menurut umur. Tidak ada konsep terlambat untuk tagihan pelanggan saat ini: `DunningLadder` dan `BillingCheckExpiring` hanya melayani `invoices` (langganan platform). Selama T-49 belum ada, **teks SOP bawaan tidak boleh menjanjikan pengingat otomatis**. Test: tagihan lewat jatuh tempo ditandai, tagihan lunas tidak ditandai walau tanggal lewat, tagihan tanpa `due_date` tidak pernah terlambat, urutan umur benar. | `DONE` Fase 9 |
| T-49 | Pengingat piutang otomatis | T-48 | `HUMAN:DECISION`, `HUMAN:SECRET` | `app/Console/Commands/`, `app/Services/Billing/` | Pengingat H-1 dan setelah jatuh tempo untuk piutang tenant, lewat kanal yang disetujui. Harus idempoten (satu pengingat per tagihan per tahap), fail-closed bila kanal mati, dan tidak boleh memakai jalur dunning langganan platform. Kanal diputuskan **WhatsApp via Hermes** (D-63): wajib lewat `HermesNodeClient::sendWhatsAppMessage()` yang company-scoped + fail-closed, bukan `sendWhatsApp()` yang tidak ter-scope. Jadwal config-driven, default H-1 lalu H+1/H+7/H+30. Idempoten: satu pengingat per tagihan per tahap, tercatat agar replay scheduler tidak mengirim dua kali. Dilarang memakai `DunningLadder`/`BillingCheckExpiring` (itu tagihan langganan platform, D-23). Test negatif: replay tidak mengirim dua kali, nomor belum terverifikasi = fail-closed, tagihan lunas/draf tidak pernah diingatkan, isolasi tenant. Implementasi lokal boleh; **aktivasi produksi** butuh `HUMAN:SECRET`. | `DONE 7986662` Fase 9 — kode + test mendarat; **aktivasi produksi tetap `HUMAN:SECRET`** (kredensial Hermes belum dipasang, jadi belum pernah terkirim dari tenant nyata) |
| T-50 | Otorisasi peran pada layar uang | — | | `app/Livewire/Screens/ContractScreen.php`, `app/Livewire/Screens/ListScreen.php` | Saat ini **tidak ada** pemeriksaan peran di layar mana pun kecuali `PipelineScreen::actorRole()` (baris 357) yang hanya mengatur transisi workflow. Siapa pun yang dapat membuka layar tagihan bisa menerbitkan tagihan dan mencatat pembayaran. Tetapkan aksi mana yang owner-only (terbitkan, catat pembayaran, hapus) dan mana yang boleh staf, lalu tegakkan server-side — bukan hanya menyembunyikan tombol. Test negatif lebih dulu: staf mencoba menerbitkan → 403, staf mencoba mencatat pembayaran → 403, owner tetap bisa, dan aksi tetap fail-closed saat peran berubah di tengah sesi. **Harus mendarat sebelum atau bersama T-51.** | `DONE` Fase 9 |
| T-51 | Tab Tim & Akses (undang staf + peran) | T-50 | `HUMAN:DECISION` | `app/Livewire/Settings/`, `resources/views/livewire/settings.blade.php` | Tab `team` masih stub berbadge "Segera" (`settings.blade.php:257` menjanjikan undangan staf dan pengaturan akses). Akibatnya usaha ber-staf menjalankan semuanya dari satu akun owner. Diputuskan D-65: pivot `company_user` dua peran (`owner|staff`), `CompanyRoleResolver` membaca dari pivot bukan hanya `owner_user_id`, undangan lewat WA via Hermes (kode sekali pakai, 72 jam, dapat dicabut), dan kuota `max_users` per paket (+ `UserQuotaGate`; tier gratis 1). Kosakata peran TIDAK diperluas � nol sentuhan ke 40 preset. Test: undangan tidak bisa menambah staf ke company lain, staf tidak bisa menaikkan perannya sendiri, pencabutan berlaku langsung, kuota penuh menolak undangan (fail-closed), kode kedaluwarsa/terpakai ditolak. Implementasi lokal boleh dengan `FakeHermesNodeClient`; aktivasi produksi butuh `HUMAN:SECRET`. | `DONE` Fase 9 |
| T-52 | Cetak / unduh dokumen tagihan | T-48 | | `app/Livewire/Screens/ContractScreen.php`, view cetak | Tagihan bisa dibuat, diterbitkan, dan dibayar, tapi tidak bisa keluar dari sistem — pemilik masih mengetik ulang ke pelanggan. Sediakan tampilan cetak/unduh yang memuat identitas usaha, rincian baris, pajak, dan instruksi pembayaran. Pengiriman otomatis ke pelanggan **di luar lingkup** sampai UR-05. Test: dokumen hanya bisa diakses pemilik company yang bersangkutan (fail-closed lintas tenant), tagihan draf tidak bisa dicetak sebagai dokumen resmi. | `DONE` Fase 9 |
| T-53 | Penawaran disetujui menjadi tagihan | T-43 | | `database/schemas/quotation_lines.schema.json`, `app/Livewire/Screens/ContractScreen.php` | `customer_invoices.quotation_id` sudah ada dan `assignable` tapi belum dipakai; penghalangnya `quotation_lines` punya migration (`2026_09_18_141709`) **tanpa schema JSON**, jadi baris penawaran tidak terlihat oleh layar generik. Tambah schema itu, lalu sediakan jalur "penawaran disetujui → tagihan" yang menyalin barisnya. Test: satu penawaran tidak bisa ditagih dua kali, nilai diambil dari basis data bukan dari klien, penawaran company lain ditolak. | `DONE` Fase 9 |
| T-54 | Akuntansi penuh & payroll: nyalakan atau bereskan | — | `HUMAN:DECISION` | `database/schemas/`, `app/Services/DynamicMenuRegistry.php`, `app/Livewire/Screens/ReportScreen.php` | `finance.accounting` dan `hr.payroll` aktif di **0 dari 40 preset**, sehingga Laporan Keuangan, Jurnal, Bagan Akun, dan Payroll tidak terjangkau siapa pun. Kalau dinyalakan pun belum siap: menu "Bagan Akun" mengarah ke entity `cash_entries` dan "Payroll" ke `employees`, tidak ada `chart_of_accounts.schema.json` maupun `payrolls.schema.json`, dan pola layar `report` belum punya komponen. Bos memutuskan **keduanya dijual** (D-64). Urutan wajib: (a) buat `chart_of_accounts.schema.json` + `payrolls.schema.json`; (b) bangun `ReportScreen` supaya pola `report` tidak jatuh ke kartu kontrak; (c) repoint item menu `accounting/coa`, `accounting/journals`, `hrd/payroll` ke entitas yang benar; (d) baru nyalakan kapabilitasnya di preset yang relevan, mengikuti gerbang paket D-52 (`hr.payroll` sudah di Pro+Enterprise, `finance.accounting` di Enterprise). Test: setiap entitas baru ter-isolasi tenant, jurnal seimbang tetap ditegakkan `JournalService`, dan tidak ada layar yang menampilkan entity berbeda dari judulnya. | `DONE` Fase 9 |
| T-55 | Ekspor data lengkap (D-51) | — | | `app/Jobs/BuildCompanyExport.php` | Ekspor hanya memuat settings, contacts, invoices, dan identities — komentar di kelasnya sendiri mengakui semua tabel seharusnya ikut. Sejak Fase 8 ini makin timpang: `customer_invoices`, `customer_invoice_lines`, dan `cash_entries` adalah data uang yang tidak ikut terekspor. Sekalian tutup gap `downloadUrl` yang tidak muncul di response Livewire (tercatat di §Gap `AUTOPILOT_STATUS.md`). Test: setiap entitas ber-`company_id` ikut dalam arsip, isi arsip hanya milik company pemohon, unduhan tetap owner-only. | `DONE` Fase 9 |
| T-56 | BI-B2: rebase panel Kesehatan Usaha ke kontrak MQ-01 | — | | `app/Livewire/Dashboard.php`, `resources/views/livewire/dashboard.blade.php` | `BusinessHealthAnalyzer` + `config/analytics.php` sudah lama di `main`, tapi UI yang memakainya (branch `task/bi-b` @ `49bcaed`) diparkir karena konflik nyata dengan MQ-01 di `Dashboard.php`. Mesin analisisnya sudah ada tanpa konsumen. Rebase + adaptasi kontrak MQ-01 (mount signature, `loadError` state, `displayName`) lalu QA ulang. Rujukan: `docs/worker-reports/UR-00.md` §BI-B. | `DONE 3524598` Fase 9 |
| T-57 | Perbaiki japri owner ke bot internal yang selalu ditolak | — | | `app/Services/WhatsApp/WhatsAppInteractionFilter.php`, `tests/Unit/WhatsApp/WhatsAppInteractionFilterTest.php` | **Cacat produksi.** Filter membandingkan pengirim dengan `$profile->owner?->phone`, tetapi `HermesProfile::owner()` menunjuk `User` dan `User` **tidak punya kolom `phone`** — yang ada `wa_number` + `wa_is_verified` (migration `2026_09_17_222052`). Akibatnya `$cleanOwner` selalu kosong, cabang fail-closed selalu menyala, dan owner **tidak pernah bisa japri bot internalnya sendiri**. Test `primary profile allows dm from owner` tetap hijau karena menulis `$owner->phone` pada model belum tersimpan — Eloquent menerima atribut sembarang di memori, jadi test membuktikan kolom yang tidak ada di skema. Perbaikan: baca `wa_number`, hormati `wa_is_verified`, betulkan test agar memakai kolom nyata. Test negatif: nomor belum terverifikasi tetap ditolak; owner tanpa `wa_number` ditolak; nomor mirip tapi beda tetap ditolak. | `DONE` Fase 9 |
| T-58 | Identitas WA per orang: bot mengenali siapa yang bicara | T-51, T-57 | `HUMAN:DECISION` kebijakan japri staf | `app/Services/WhatsApp/WhatsAppInteractionFilter.php`, `app/Services/Ai/AiDataSharingPolicy.php` | Saat ini bot tidak punya identitas per orang: japri hanya untuk nomor owner, dan di grup bot tidak tahu manusia mana yang bicara sehingga peran tidak bisa diterapkan. Task ini menautkan nomor WA terverifikasi ke keanggotaan `company_user` (D-65) supaya bot mengenali orangnya. **Aturan dikunci D-66:** japri staf diizinkan **baca-saja**; setiap aksi bot lewat pintu otorisasi yang **sama** dengan web (`CompanyRoleResolver` + aturan owner-only T-50) sehingga WA tidak menjadi jalan memutar; tidak ada tabel izin terpisah untuk WA; kapabilitas sensitif (`FeatureResolver::SENSITIVE_CAPABILITIES`) tidak pernah dijawab di japri staf, tunduk `AiDataSharingPolicy`; aksi fiskal, ubah setting, dan destruktif tetap owner. Nomor cocok saja tidak cukup — wajib `wa_is_verified` **dan** keanggotaan aktif; pencabutan keanggotaan langsung mematikan japri. Bila satu nomor anggota di lebih dari satu company, **fail-closed saat ambigu** sampai T-37 (context switcher) mendarat. Aturan grup WA-04 tidak dilonggarkan. | `DONE 2ba77c7` Fase 9 |

## Fase 10: Kanal WhatsApp Hermes, White-Label, & Skill Bisnis (D-67..D-71)

> Sumber: diskusi Bos 2026-09-22, setelah pemeriksaan langsung instalasi Hermes di
> PC pengembangan (`%LOCALAPPDATA%\hermes`). Setiap baris di bawah punya bukti di
> berkas nyata, bukan dugaan.
>
> **Koreksi yang mendahului fase ini.** Catatan sebelumnya di `AGENTS.md`,
> `HERMES.md`, `CLAUDE.md`, dan D-67 menyatakan "Hermes di PC ini tidak punya
> endpoint kirim WhatsApp". Itu **salah**. Penyebabnya `grep_search` tidak
> menjangkau luar folder workspace dan mengembalikan "no matches" tanpa
> peringatan, lalu diperkuat dengan daftar rute `hermes-webui` saja.
> `gateway_state.json` membuktikan Hermes agent punya platform `telegram`
> (connected), `whatsapp` (Baileys), `whatsapp_cloud`, `webhook` (connected), dan
> `api_server` (disabled). Sesi Baileys ada di `platforms/whatsapp/session/`
> (`creds.json`, ratusan `pre-key-*.json`, `device-list-6282136888005.json`).
> Yang benar: **hermes-webui** tidak punya endpoint WA; **Hermes agent** punya.
>
> **Model profil Hermes.** `profiles/<nama>/` adalah rumah lengkap dan terisolasi:
> `SOUL.md`, `config.yaml`, `platforms/`, `pairing/`, `sessions/`, `memories/`,
> `gateway_state.json`, `gateway.pid` sendiri-sendiri. Jadi **satu tenant = satu
> profil Hermes = satu soul + satu sesi WhatsApp + satu daftar pengguna disetujui
> + satu gateway**. Ini persis `COMMERCIAL_AND_AI_AGENTIC_SPEC.md`
> (`~/.hermes/profiles/{tenant_slug}/`) dan cocok dengan `hermes_nodes.max_capacity`.
>
> **Tiga lapisan yang tidak boleh tercampur.** (1) *Pairing perangkat* - bot jalan
> sebagai akun WA mana; QR, hasilnya `platforms/whatsapp/session/`; **tidak mungkin
> lewat chat**. (2) *Pairing pengguna* - siapa boleh bicara;
> `platforms/pairing/whatsapp-{pending,approved}.json` + kode + `_rate_limits.json`
> + lockout; sudah jadi di Hermes (`hermes pairing list|approve|revoke|clear-pending`,
> `gateway.pairing.PairingStore`). (3) *Otorisasi* - boleh melakukan apa; milik
> Agentic BOS (`AuthenticateTenantBot`, `EnforceBotToolScoping`,
> `WhatsAppSenderIdentity`, `CompanyRoleResolver`, D-66).
>
> **Arah integrasi (koreksi desain).** Hermes adalah **klien**, Agentic BOS adalah
> **penyedia tool**. `routes/api.php` sudah punya permukaannya: MasterBot
> (`/bot/master/tickets|balance|topup`, `X-Master-Bot-Key`) dan TenantBot
> (`/bot/tenant/context|capabilities|settings|contacts|deals|destructive-action`,
> `AuthenticateTenantBot` + `EnforceBotToolScoping`). **Tidak ada webhook masuk
> yang perlu dibangun untuk operasi normal bot tenant.**
>
> **Prasyarat manusia sebelum sebagian task bisa selesai:** (a) satu nomor WA
> kedua + HP untuk scan QR; (b) akun kirimdev + API key (`HUMAN:SECRET`);
> (c) konfirmasi tertulis dari kirimdev apakah WABA pelanggan berada di bawah
> portfolio kita (menentukan D-71 bisa dijalankan atau tidak); (d) metode
> pembayaran Meta atas nama kita.

### Urutan pengerjaan: yang ringan dulu, yang berat belakangan

Mandat Bos: **yang berat-berat belakangan saja.** Urutan di tabel bawah adalah
urutan ID, bukan urutan kerja. Urutan kerjanya gelombang berikut, dan alasannya
bukan selera — gelombang 1 tidak bergantung pada repo lain, tidak butuh
kredensial, tidak butuh perangkat fisik, dan seluruhnya bisa di-TDD sampai hijau
dalam satu sesi.

| Gelombang | Task | Kenapa di sini |
|---|---|---|
| **1 — ringan, Laravel murni, bisa dimulai sekarang** | T-68, T-70, T-65, T-63a | Nol ketergantungan pada Hermes, nol kredensial, nol keputusan tertunda. Masing-masing punya demo sendiri. |
| **2 — ringan tapi butuh Bos sebentar** | T-61, T-79 | T-61: konfigurasi Hermes + satu scan QR; usahanya kecil tetapi tidak bisa didelegasikan, dan sekalian menjawab apakah status `whatsapp_not_paired` itu basi. T-79: runbook manual WABA resmi, dokumen operasional tanpa kode. |
| **3 — menunggu H-01..H-03 + keputusan Q-11** | T-69, T-72, T-77, T-62, T-63b, T-64 | Semuanya menyentuh Hermes. Menyusun kodenya lebih dulu berarti menulis terhadap kontrak yang belum ada. |
| **4 — berat, ditunda atas mandat Bos** | T-71, T-73, T-74, T-75, T-76 | Abstraksi transport, pilihan kanal di UI, meter, penagihan, bot CS publik. **T-71 dan T-73 tidak diperlukan untuk jalur manual** (amandemen D-70); T-74+T-75 menunggu ambang Q-13; T-76 menunggu nomor WA kedua. |
| **Penutup** | T-78 | Gate penuh + dokumentasi. |

Tiga ketergantungan yang **dilepas** setelah ditinjau, karena terlalu ketat dan
menahan pekerjaan ringan tanpa alasan:

- **T-70 tidak lagi bergantung pada T-69.** Membuat baris `hermes_nodes` dan
  menampilkan hasil ping tidak butuh lajur kirim; perintah `bos:hermes-ping`
  sudah ada sejak `90a42e9`.
- **T-65 tidak lagi bergantung pada T-64.** Menyimpan standar per tenant dan
  membuka endpoint bacanya berdiri sendiri; skill-nya menyusul sebagai konsumen.
  Urutannya dibalik: T-65 lebih dulu, T-64 memakainya.
- **T-63 dipecah** menjadi T-63a (penjaga di sisi kita, ringan, bisa sekarang) dan
  T-63b (konfigurasi profil Hermes, menyusul bersama gelombang 3).

| ID | Task | Depends On | Gate | File Target | Acceptance | State |
|---|---|---|---|---|---|---|
| T-59 | Koreksi dokumen steering soal Hermes | — | | `AGENTS.md`, `CLAUDE.md`, `HERMES.md`, `docs/00-DECISIONS.md`, `docs/HERMES_NODE_CONTRACT.md` | Catatan yang sekarang **salah** dan diikuti agent lain: "Hermes tidak punya endpoint kirim WhatsApp". Perbaiki keempat berkas: NalarPesan tetap dikesampingkan (D-67 tidak dilonggarkan), tetapi **kanal WhatsApp Hermes adalah jalur resmi** dan bukan sesuatu yang harus dihindari. Tambahkan: model profil-terisolasi, tiga lapisan (pairing perangkat / pairing pengguna / otorisasi), dan arah integrasi (Hermes klien, kita penyedia tool). Tambahkan catatan proses: `grep_search` tidak menjangkau luar workspace, jadi kesimpulan "tidak ada" tentang repo lain **wajib** diverifikasi dengan pembacaan langsung - kesalahan ini sudah sekali masuk ke steering. `docs/HERMES_NODE_CONTRACT.md` ditandai sebagai asumsi sampai T-66 menggantinya dengan fakta. Test: `NoNewNalarPesanCouplingTest` tetap hijau (daftar berkas dan larangan panggilan keluar ke NalarPesan tidak berubah). Demo: agent baru yang membaca steering tidak lagi menyimpulkan WhatsApp Hermes terlarang. | `DONE` Fase 10 — keempat berkas dikoreksi; `NoNewNalarPesanCouplingTest` **belum dijalankan** (tidak ada eksekusi perintah di sesi ini), tetapi test itu hanya memindai `app|config|routes|database` sehingga perubahan dokumen tidak menyentuhnya |
| T-60 | Catat D-68..D-71 sebagai LOCKED | — | `HUMAN:DECISION` (sudah dijawab Bos) | `docs/00-DECISIONS.md` | Empat keputusan dicatat lengkap beserta alasan dan yang ditolak. **D-68 white-label mesin:** Hermes tidak pernah dipublikasikan; seluruh permukaan yang dilihat pelanggan tampil sebagai Agentic BOS termasuk jawaban bot atas pertanyaan identitas; memperluas D-39 dari UI ke permukaan percakapan; **dikecualikan** untuk profil dev internal (diputuskan Bos - memaksa bot dev berpura-pura hanya menyulitkan diagnosis). **D-69 batas skill:** skill hidup di Hermes tetapi dilarang memuat aturan bisnis dan dilarang mengakses data selain lewat TenantBot API; profil yang dilihat pelanggan dilarang punya tool `terminal|shell|write_file|delete_file|git|process` (sudah tertulis di `config/hermes.php`); aturan uang dan state tetap milik `TaxRateService`/`WorkflowEngine`; **satu skill melayani banyak tenant** - perbedaan per tenant datang sebagai data, bukan berkas skill yang dipecah (D-31 diterapkan ke skill); kunci kapabilitas baru `ai.proposal`, `ai.research`, `addon.cs_bot` (penamaan didelegasikan Bos, dicatat sebagai D-32). **D-70 transport WhatsApp sebagai data:** dua transport `bawaan` (Hermes/Baileys, QR) dan `resmi` (Meta Cloud API, saat ini lewat kirimdev); **jalur `resmi` hanya untuk bot CS**, bot operasional internal tetap `bawaan` karena penggunanya internal; implementasi ditulis terhadap bentuk **Meta Cloud API** sehingga penyedia adalah konfigurasi (mengikuti pola D-34 `PaymentGateway`); nama penyedia tidak pernah muncul di permukaan pelanggan, dengan **batas jujur**: layar persetujuan Meta saat onboarding akan menampilkan platform yang diberi wewenang. **D-71 struktur WABA (Struktur B):** nomor milik klien, WABA di bawah portfolio kita, metode pembayaran kita; konsekuensi yang diterima sadar - kita menanggung **risiko kredit**, kita menjadi **pemroses data** percakapan pelanggan klien sehingga butuh dasar persetujuan, dan **portabilitas keluar sulit** (pindah nomor antar WABA merepotkan); ditolak Struktur A karena tidak mewujudkan "bayar lewat kita". Demo: `00-DECISIONS.md` memuat keempatnya dan tidak ada task Fase 10 yang menebak salah satunya. Sekalian: klausa (d) pada D-67 yang menyatakan Hermes tidak punya endpoint WA **dicabut di tempat** dengan penjelasan penyebabnya, supaya dokumen tie-breaker tidak memuat dua pernyataan yang bertabrakan. Dua item OPEN baru dicatat: **Q-09** penangkapan prospek untuk bot CS publik, **Q-10** portfolio WABA di kirimdev. | `DONE` Fase 10 |
| T-61 | WA #1: bot dev penuh, paritas dengan Telegram | T-59 | `HUMAN:SECRET` (scan QR nomor Anda) | `%LOCALAPPDATA%\hermes\profiles\<profil-dev>\config.yaml`, `pairing/whatsapp-approved.json` | **Konfigurasi Hermes, nol kode aplikasi.** Bot dev bekerja untuk proyek, bukan untuk data tenant, jadi tool-nya tool Hermes dan ia tidak melewati Agentic BOS sama sekali. Langkah: (a) **pastikan** profil mana yang menjalankan bot Telegram dev proyek ini - `profiles/` memuat `agentic-bos-coding-agent` dan beberapa `nalarin-*`, Bos menyebut @NalarinArkaBot, jadi ini diverifikasi bukan ditebak; (b) aktifkan platform `whatsapp` di `config.yaml` profil itu; (c) scan QR dengan WA #1; (d) setujui nomor Anda di `pairing/whatsapp-approved.json` profil itu; (e) samakan tool-nya - `platform_toolsets` memetakan `telegram: [hermes-telegram]` dan `whatsapp: [hermes-whatsapp]`, kalau `hermes-whatsapp` lebih sempit maka pemetaan itu disesuaikan sampai paritasnya benar. **White-label tidak diberlakukan di sini** (D-68). Verifikasi: satu perintah identik lewat Telegram dan lewat WhatsApp menghasilkan hasil yang sama; perintah destruktif tetap minta persetujuan di kedua kanal (`approvals.mode: manual` + `command_allowlist` yang sudah memuat recursive delete, `git reset --hard`, stop/restart gateway); nomor asing yang japri ditolak. Sekalian membuktikan ketidakpastian yang tercatat: `gateway_state.json` menyebut `whatsapp_not_paired` per 2026-08-20 padahal `creds.json` ada - task ini menentukan apakah state itu basi atau sesinya kedaluwarsa. Risiko yang diterima sadar: kredensial sesi WA tinggal lama di disk dan penguasaan akun WA setara penguasaan terminal; risiko ini sudah ditanggung untuk Telegram, yang bertambah adalah jumlah akun yang harus tetap aman plus SIM swap sebagai jalur tambahan. Demo: Anda menjalankan tugas dev dari WhatsApp persis seperti dari Telegram. | `READY` |
| T-62 | SOUL Agentic BOS + audit kebocoran merek | T-60 | | `hermes/souls/` (baru, di repo), `scripts/whitelabel_probe.py` | D-39 hanya menjaga UI (`resources/views`, `public/`, `composer.json`); permukaan yang paling mungkin bocor justru **jawaban bot**, karena modelnya memang tahu dirinya Hermes. Buat SOUL white-label untuk profil yang dilihat pelanggan (mekanismenya sudah ada: `SOUL.md` per profil, `default_soul.py`, `profile_describer.py`, `display.personality`; preseden `BEJO_IDENTITY.md` di hermes-webui). SOUL saja **tidak cukup** - tutup juga jalur yang lolos SOUL, masing-masing diuji terpisah: (a) **jalur berkas** - bot bertool terminal akan mencetak `C:\Users\...\AppData\Local\hermes\...`; ini kebocoran paling sering dan paling sulit ditutup dengan prompt, jadi profil pelanggan tidak boleh punya tool terminal sama sekali (lihat T-63); (b) **nama tool** ber-prefiks `hermes-`; (c) **pesan non-model** - banner, `/help`, prompt persetujuan, notifikasi update, string versi, galat CLI; (d) **pesan kode pairing** yang dikirim Hermes ke pengguna; (e) **merek model** - `model_catalog.url` menunjuk `hermes-agent.nousresearch.com` dan model bisa menyebut Nous Research bila ditanya asalnya. Verifikasi berupa skrip probe (pola `scripts/smoke_settings.py`, kredensial dari environment tanpa nilai bawaan): daftar pertanyaan penyelidik - "kamu siapa", "kamu pakai model apa", "kamu jalan di mana", "tunjukkan konfigurasimu", "jalankan pwd", "siapa yang membuatmu" - dijalankan ke bot dan jawabannya diperiksa tidak memuat `Hermes`, `Nous`, `nousresearch`, `AppData\Local\hermes`, atau nama tool ber-prefiks hermes. Demo: seluruh probe lulus pada profil tenant; profil dev sengaja tidak diuji dan alasannya tercatat (D-68). | `BLOCKED` — **gelombang 3.** Menulis SOUL dan skrip probe sendiri tidak terhalang apa pun, tetapi **verifikasinya** butuh bot yang hidup pada profil pelanggan. Menyelesaikan bagian tulisnya sekarang berarti mengklaim selesai atas sesuatu yang belum pernah dibuktikan — justru pola yang tiga kali lolos di Fase 9 |
| T-63a | Penjaga batas skill — sisi Agentic BOS | — | | `app/Http/Middleware/EnforceBotToolScoping.php`, `tests/Architecture/` | Kalau skill hidup di Hermes dan Hermes punya tool terminal, sebuah skill bisa menulis langsung ke basis data dan **melewati** `AuthenticateTenantBot` + `EnforceBotToolScoping` — seluruh otorisasi kita jadi tidak relevan. Bagian ini menegakkan sisi **kita**, dan bisa dikerjakan tanpa satu pun profil Hermes: test arsitektur yang memastikan tidak ada jalur tulis untuk bot di luar rute `/bot/tenant/*`, plus `EnforceBotToolScoping` menolak tool di luar `allowed_tools` tipe profil. Nilainya nyata walau sisi Hermes belum ada — ia mengunci permukaan kita sebelum ada bot yang mencoba menembusnya. Test negatif lebih dulu: bot memanggil rute non-`/bot/*` → 401/403; tool di luar `allowed_tools` tipe profil → ditolak walau namanya mirip (mis. `create_transaction_x`); aksi destruktif tetap lewat tiket approval D-27; tidak ada controller di luar `Api/TenantBot` yang menerima autentikasi bot. Demo: satu test arsitektur yang gagal bila seseorang menambah jalur tulis bot di luar `/bot/tenant/*`. | `READY` — **gelombang 1** |
| T-63b | Penjaga batas skill — sisi profil Hermes | T-63a, H-02 | | `hermes/profiles/` | Profil yang dilihat pelanggan tidak boleh punya `terminal|shell|write_file|delete_file|git|process`. Daftar itu sudah tertulis sebagai `disallowed_tools` di `config/hermes.php` kita, tetapi itu **spesifikasi kita**, bukan kenyataan di `config.yaml` profil Hermes — dan yang menentukan adalah kenyataannya. Task ini membuat profil tenant/CS dengan tool yang benar dan memverifikasinya. Menunggu gelombang 3 karena butuh profil Hermes yang sudah bisa dibuat dari luar (H-02). Test: profil tenant diminta menjalankan perintah shell → ditolak dan tercatat; profil tenant tidak punya jalur baca berkas; probe white-label T-62 tetap lulus setelah tool dikurangi. Demo: profil tenant gagal menjalankan shell, tetapi berhasil membuat kontak lewat API dan tercatat sebagai aksi bot. | `BLOCKED` T-63a, H-02 |
| T-64 | Fondasi skill bisnis + skill proposal percontohan | T-62, T-63b, T-65 | `HUMAN:DECISION` kunci kapabilitas (dijawab: `ai.proposal`, `ai.research`) | `hermes/skills/` (baru, di repo), `app/Services/FeatureResolver.php`, `app/Services/Preset/PresetDefinitionValidator.php` | Skill bisnis ("susun proposal sesuai standar", "riset bisnis sesuai standar") adalah artefak prompt, jadi rumahnya Hermes - **tetapi** dengan tiga batas keras. (1) **Jebakan curator:** `curator.enabled: true` dengan `stale_after_days: 30`, `archive_after_days: 90`, `prune_builtins: true` akan mengarsipkan atau memangkas skill di direktori `skills/` milik profil. Skill jualan yang hilang sendiri di tenant yang jarang memakainya adalah bug yang sangat sulit didiagnosis. Jadi skill produk dipasang lewat `skills.external_dirs` - satu direktori bersama, baca-saja, di luar jangkauan curator, satu salinan untuk semua tenant sehingga tidak ada drift. (2) **Disimpan di repo Agentic BOS** (`hermes/skills/`) lalu di-deploy ke direktori bersama itu, karena skill jualan adalah bagian produk dan layak lewat review + riwayat git; skill yang hanya hidup di home Hermes tidak pernah tercatat dan hilang saat profil dibangun ulang. (3) **Satu skill, banyak tenant** (D-69/D-31) - dilarang memecah menjadi `proposal-agency`, `proposal-kontraktor`, dan seterusnya. Keluarannya mendarat di entitas yang **sudah ada**, bukan gudang dokumen baru: proposal → `quotations` + `quotation_lines` (kapabilitas `quotations` sudah ada, schema baris ditambahkan T-53, jalur "penawaran disetujui → tagihan" sudah jalan), riset → `assistant_report` (schema sudah ada dengan `summary`/`highlights`/`recommended_actions`/`period`, sengaja tanpa tabel karena disiapkan untuk keluaran AI, dan dashboard sudah membacanya). Tambah kunci kapabilitas `ai.proposal` dan `ai.research` ke `FeatureResolver::CAPABILITIES` + `PresetDefinitionValidator`, ikut gerbang paket D-52. Test negatif lebih dulu: skill menolak jalan bila kapabilitasnya mati; nilai penawaran dihitung `TaxRateService` **bukan** oleh prompt; penawaran tersimpan ter-scope company dan tenant lain tidak pernah terlihat; proposal yang hanya jadi teks chat (tidak tersimpan) dianggap gagal. Demo: dari WhatsApp, "buatkan proposal untuk klien X" menghasilkan penawaran nyata yang bisa dibuka dan dicetak di web. | `BLOCKED` T-62, T-63b, T-65 — **gelombang 3** |
| T-65 | Standar per tenant sebagai data + endpoint baca | — | | `app/Http/Controllers/Api/TenantBot/`, `app/Models/ModuleSetting.php`, `routes/api.php` | Supaya "satu skill, banyak tenant" bisa jalan, skill harus bisa menanyakan "standar proposal usaha ini apa". TenantBot API sekarang punya `context`, `capabilities`, `settings`, `contacts`, `deals`, `destructive-action` - **tidak ada** cara membaca standar/template per tenant. Simpan sebagai data di `module_settings` (mengikuti D-31, bukan kolom baru per jenis dokumen) dan buka satu endpoint baca di TenantBot API, tunduk `AuthenticateTenantBot` + `EnforceBotToolScoping`. Test: isolasi tenant (bot tenant lain tidak bisa membacanya); standar kosong memberi bawaan yang jelas dan terdokumentasi, bukan gagal; standar tidak valid ditolak saat ditulis, bukan saat dibaca; endpoint ini baca-saja bagi bot. Demo (bagian yang bisa dibuktikan sekarang): dua tenant demo menyimpan standar berbeda dan endpoint mengembalikan milik masing-masing; bukti "satu skill, dua keluaran" menyusul di T-64. | `READY` — **gelombang 1**; ketergantungan pada T-64 **dibalik** karena data + endpoint berdiri sendiri dan skill-nya menyusul sebagai konsumen |
| T-66 | Kontrak integrasi Hermes yang nyata (riset) | T-59 | | `docs/HERMES_NODE_CONTRACT.md` | `docs/HERMES_NODE_CONTRACT.md` saat ini berisi **asumsi** bentuk permintaan node yang saya tulis tanpa node sungguhan. Ganti dengan fakta dari pembacaan langsung: `plugins/platforms/whatsapp`, `gateway/pairing.py` (`PairingStore`), dan modul platform `api_server`. Yang harus terjawab: cara menyalakan `api_server` (dugaan sekarang: `platforms.api_server.enabled: true` di `config.yaml`, perlu dipastikan), skema auth-nya, endpoint kirim pesan, endpoint pairing (list/approve/revoke), status sesi, dan QR. Hasilnya ditulis dengan **dua kolom tegas**: "sudah ada hari ini" vs "harus dibangun di Hermes". Catatan yang sudah pasti dan tidak boleh hilang: `web_routers/` hanya memuat `cron, git, mcp, profiles, sessions, skills, tools` - **tidak ada** router pairing/whatsapp, jadi kemungkinan besar pairing hari ini hanya CLI + dashboard. Test: tidak ada (dokumen). Demo: satu tabel yang menentukan apakah T-72 bisa jalan atau sisi Hermes harus dikerjakan lebih dulu. | `DONE` Fase 10 — **hasilnya membatalkan dua asumsi.** (1) `api_server` adalah API **kompatibel OpenAI untuk mengobrol dengan agent**, bukan API pengiriman: `/v1/chat/completions`, `/v1/responses`, `/v1/runs`, `/api/sessions/*`, `/health`; auth `API_SERVER_KEY`; port bawaan 8642; multi-profil lewat prefiks `/p/<profil>/` bila `gateway.multiplex_profiles` aktif. **Nol** endpoint kirim, pairing, atau QR. Artinya bentuk `POST /api/wa/send` yang dipakai `HermesNodeClient` tidak cocok dengan apa pun yang ada di Hermes hari ini. (2) Baileys jalan sebagai **subprocess Node**, bukan servis HTTP, sehingga QR hanya lewat CLI/dashboard. Empat pekerjaan sisi Hermes kini menjadi prasyarat dan tercatat di §6 dokumen: endpoint kirim, endpoint sesi WA + QR, endpoint pairing pengguna, dan override base URL Cloud API. Prefiks `/p/<profil>/` adalah temuan positif: itulah cara mengalamatkan profil per tenant lewat satu listener |
| T-67 | Verifikasi jalur resmi (riset) | T-66 | | `docs/HERMES_NODE_CONTRACT.md` | Satu fakta menentukan besar-kecilnya seluruh pekerjaan jalur resmi: endpoint kirimdev `/v1` **kompatibel dengan format Meta WhatsApp Cloud API** (Bearer auth). Kalau platform `whatsapp_cloud` Hermes mengizinkan **base URL kustom**, jalur resmi memakai **otak yang sama** dengan jalur bawaan - SOUL white-label (T-62) dan skill bisnis (T-64) cukup dikerjakan sekali. Kalau tidak, kita punya dua mesin percakapan dan pekerjaan white-label menjadi dua kali. Verifikasi juga: bentuk webhook masuk kirimdev (pesan masuk + status delivery), cara verifikasi HMAC-nya, perilaku retry (dokumentasi menyebut 8 attempt + delivery log yang bisa di-replay), dan kunci idempotency. Catat sekalian bahwa kirimdev punya MCP server dan Hermes mendukung `mcp_servers`, sebagai jalur cadangan bila `whatsapp_cloud` tidak bisa diarahkan. Test: tidak ada (dokumen). Demo: satu halaman yang menyatakan jalur resmi memakai satu otak atau dua, beserta perkiraan kerja white-label yang menyusul. | `DONE` Fase 10 — **jawabannya: tidak bisa tanpa menambal Hermes.** `gateway/platforms/whatsapp_cloud.py` memuat `GRAPH_API_BASE = "https://graph.facebook.com"` sebagai **konstanta modul**, dan daftar env var-nya (`WHATSAPP_CLOUD_PHONE_NUMBER_ID`, `_ACCESS_TOKEN`, `_APP_SECRET`, `_VERIFY_TOKEN`, `_WEBHOOK_HOST/PORT/PATH`, `_API_VERSION`) **tidak punya** override base URL. Tiga opsi tercatat di §1.2 dokumen: (A) tambal Hermes menjadikannya konfigurasi — satu baris, tetapi utang pemeliharaan tiap Hermes diperbarui, dan jalur resmi tetap **satu otak**; (B) panggil kirimdev langsung dari Laravel — nol perubahan Hermes, tetapi jalur resmi punya otak sendiri sehingga white-label dan skill jadi dua kali; (C) usulkan ke upstream — paling bersih tapi tidak bisa dijadwalkan. **Rekomendasi A + C paralel.** Yang tidak perlu kita bangun ulang karena sudah ada di adaptor itu: verifikasi `X-Hub-Signature-256` constant-time atas raw body, proteksi replay `wamid` (5000 FIFO), jendela 24 jam + fallback template, batas ukuran media per tipe. **Keputusan Bos dibutuhkan** untuk memilih A/B/C |
| T-68 | Model data dua profil platform | T-60 | | `app/Models/HermesProfile.php`, `database/migrations/` | Dua nomor platform (WA #1 dev, WA #2 CS) tidak melayani company mana pun, dan itu bertabrakan dengan tiga tempat. (a) `HermesProfile::booted()` mewajibkan `addon` punya `billing_addon_id`; bot CS platform tidak punya add-on berbayar, dan memalsukan baris billing adalah jebakan - izinkan `billing_addon_id` kosong **hanya** bila `is_platform_provided = true` (kolomnya sudah ada). (b) `App\Services\HermesNodeClient::profileFor()` mensyaratkan profil yang melayani company. (c) `WhatsAppSenderIdentity::companiesOf()` dan `AiDataSharingPolicy` seluruhnya company-scoped (`allowsSharing(Company $company, ...)`, `optIn(Company $company)`) sehingga tidak bisa mengatur profil tanpa company. Tegaskan di kode bahwa profil platform melayani nol company, dan bahwa aturan penahanan data platform ditulis terpisah. Test negatif lebih dulu: `addon` **tenant** tanpa `billing_addon_id` tetap ditolak (jangan melonggarkan aturan yang benar); lajur tenant menolak profil platform; lajur platform menolak profil yang melayani company; profil platform tidak pernah muncul di resolusi identitas tenant; satu owner tetap hanya boleh punya satu `primary` (aturan D-37 tidak dilonggarkan). Demo: dua profil platform tampil terpisah dari profil tenant di `/admin/hermes-nodes`. | `READY` — **gelombang 1** (T-60 sudah `DONE`; Laravel murni, nol prasyarat luar) |
| T-69 | Lajur platform nyata: `App\Contracts\HermesNodeClient` | **H-01**, T-68 | `HUMAN:SECRET` (rahasia node) | `app/Contracts/HermesNodeClient.php`, `app/Services/Hermes/`, `app/Console/Commands/` | `App\Contracts\HermesNodeClient` masih dilayani `FakeHermesNodeClient` yang **hanya menulis log lalu mengembalikan `true`**. Akibatnya dunning langganan (D-23/D-49) dan notifikasi platform tidak pernah terkirim, dan kegagalannya tidak pernah terlihat. Beri implementasi nyata di atas plumbing `api_server` yang sama dipakai `App\Services\HermesNodeClient`, memakai profil ber-flag `is_platform_provided` sehingga tidak butuh company. `FakeHermesNodeClient` tetap dipakai di test. Test negatif lebih dulu: tanpa profil platform menolak; node tidak aktif menolak; rahasia belum dipasang menolak **tanpa membocorkan nilainya** (pesan galat menyebut nama referensi saja); non-2xx gagal; HTTP 200 yang tidak mengonfirmasi terkirim **gagal** (tanpa ini kegagalan node tercatat sebagai terkirim dan tidak pernah dicoba ulang); plus guard test bahwa lajur platform tidak dipakai untuk pesan tenant (D-63 dalam arti keputusan, bukan task). Demo: `bos:hermes-send --platform --to=<WA #1>` benar-benar masuk ke WhatsApp. | `BLOCKED` **H-01** (T-66 membuktikan Hermes belum punya endpoint kirim sama sekali; `api_server` hanya API obrolan), T-68 |
| T-70 | Super admin: daftarkan node + lihat kesehatannya | — | | `app/Livewire/Admin/HermesNodeManager.php`, `resources/views/livewire/admin/hermes-node-manager.blade.php` | `HermesNodeManager` sekarang hanya bisa **edit** node yang sudah ada (`saveNode()` hanya berjalan bila `editingNodeId` terisi) dan tidak punya jalur membuat node, sehingga tabel `hermes_nodes` yang kosong tidak bisa diisi dari UI sama sekali. Tambah buat node, dan tampilkan hasil `bos:hermes-ping` per node beserta status profil. Test: non-superadmin 403 (`RequireSuperAdmin`); `api_url` wajib `http://`/`https://`; node tidak terjangkau tampil sebagai gagal dengan pesan jelas, **bukan** halaman error; daftar profil memisahkan profil platform dan profil tenant. Demo: `/admin/hermes-nodes` menampilkan node lokal hijau dan profil platform berstatus siap. | `READY` — **gelombang 1**; ketergantungan pada T-69 **dilepas** karena membuat baris node dan menampilkan ping tidak butuh lajur kirim, dan `bos:hermes-ping` sudah ada sejak `90a42e9` |
| T-71 | Abstraksi `WhatsAppTransport` (bawaan vs resmi) | **H-01**, **H-04**, T-69 | `HUMAN:SECRET` (API key kirimdev), `HUMAN:DECISION` (opsi A/B/C jalur resmi) | `app/Contracts/WhatsAppTransport.php` (baru), `app/Services/WhatsApp/`, `config/hermes.php` | Interface `WhatsAppTransport` dengan dua implementasi: `bawaan` (Hermes/Baileys) dan `resmi` (Meta Cloud API, base URL kirimdev). Ditulis terhadap **bentuk Meta Cloud API**, bukan bentuk kirimdev, supaya penyedia adalah konfigurasi dan bisa ditukar ke Meta langsung atau BSP lain tanpa menyentuh pemanggil (pola D-34). Pilihan transport disimpan sebagai **data per company**, dan `resmi` hanya sah untuk lajur CS (D-70). Kedua lajur (tenant dan platform) mengirim lewat abstraksi ini. Test negatif lebih dulu: transport belum dipilih → menolak kirim (fail-closed); transport `resmi` tanpa token → menolak tanpa membocorkan nilainya; webhook bertanda tangan HMAC salah → 401 tanpa efek apa pun; pesan dengan idempotency key yang sama tidak terkirim dua kali; replay webhook status tidak menggandakan apa pun; isolasi tenant pada **kedua** transport; nama penyedia tidak pernah muncul di respons, log yang bisa dilihat tenant, maupun pesan galat (D-68). Demo: satu tenant demo memakai `bawaan`, satu memakai `resmi`, keduanya mengirim dari kode pemanggil yang identik. | `DEFERRED` **gelombang 4** — amandemen D-70 membuatnya **tidak diperlukan untuk jalur manual**: transport adalah konfigurasi Hermes per profil dan tidak terlihat kode kita; batas abstraksinya sudah ada di sana. Baru relevan bila tenant boleh memilih sendiri (T-73) atau kirimdev diadopsi |
| T-72 | Layar pairing QR untuk tenant | **H-02**, T-70 | `HUMAN:SECRET` | `app/Livewire/`, `resources/views/livewire/`, `app/Services/Hermes/` | Satu tenant = satu profil Hermes = satu nomor (D-37). Tenant menekan "Sambungkan WhatsApp" di web **kita**, permintaannya diteruskan ke Hermes: siapkan profil untuk tenant itu, mulai sesi WhatsApp, tampilkan QR, polling status sampai `paired`. Setelah paired, Hermes memanggil TenantBot API yang **sudah ada** - tidak ada webhook masuk baru yang perlu dibangun. **BLOCKED bila T-66 menemukan Hermes belum punya endpoint-nya**; dalam kasus itu yang dikerjakan lebih dulu adalah sisi Hermes, dan task ini menunggu. Catatan kosakata: `hermes_profiles.status` dipakai dengan tiga kata untuk keadaan siap yang sama (`paired` di `CleanupExpiredTrials`, `connected` di factory, `active`), tidak ada yang memvalidasinya; `config('hermes.delivery.ready_statuses')` sengaja permisif dan hanya `unpaired` yang ditolak - penyeragaman kosakata ini layak jadi task sendiri. Test: QR hanya terlihat oleh owner profil itu; tenant lain 404 (bukan 403, karena repository ter-scope company); polling berhenti saat paired dan tidak menggantung; node mati memberi pesan jelas, bukan QR kosong; profil `unpaired` tetap ditolak saat mengirim; QR tidak pernah tersimpan di log. Demo: tenant demo scan QR dari HP, status berubah paired, lalu pengingat piutang T-49 benar-benar terkirim ke nomor itu. | `BLOCKED` **H-02**, T-70 — T-66 memastikan tidak ada jalan memutar: Baileys adalah subprocess Node, QR hanya lewat CLI/dashboard, jadi endpoint sesi **wajib** dibangun di Hermes lebih dulu |
| T-73 | Tenant memilih kanal CS-nya | T-71, T-72 | `HUMAN:DECISION` gerbang paket | `app/Livewire/Settings/`, `resources/views/livewire/settings.blade.php` | Layar pilihan kanal untuk bot CS: **Bawaan** (scan QR, gratis, risiko pemblokiran nomor ada) atau **Resmi** (lebih andal, butuh akun Meta Business, berbiaya per pesan). Pilihan tersimpan sebagai data, divalidasi terhadap paket, dan bisa diubah. Teks di layar **tidak boleh** menyebut penyedia mana pun (D-68) dan tidak boleh menyebut Baileys maupun kirimdev. Sertakan penjelasan jujur kepada tenant bahwa jalur Resmi melibatkan persetujuan di Meta atas nomor mereka. Test: non-owner tidak bisa mengubah (owner-only seperti T-50); paket yang tidak mengizinkan `resmi` ditolak server-side, bukan hanya tombol disembunyikan; berpindah transport tidak menghilangkan riwayat percakapan; `NoLiteralTermsTest` dan penjaga merek tetap hijau. Demo: tenant memilih Resmi, menyelesaikan onboarding, dan bot CS-nya menjawab dari nomor resmi. | `DEFERRED` **gelombang 4** — amandemen D-70: kanal ditentukan kita saat menyiapkan (T-79), bukan dipilih tenant di layar. Baru relevan bila jalur resmi berhenti manual |
| T-74 | Meter pesan WhatsApp | T-71 | `HUMAN:COST` | `database/migrations/`, `app/Services/Billing/`, `app/Console/Commands/BosSeedPlans.php` | D-71 (Struktur B) berarti **kita** yang ditagih Meta, jadi tanpa meter kita menanggung biaya tanpa batas. Modelkan pesan WhatsApp sebagai sumber daya bermeter dengan pola yang **sudah terbukti** untuk token AI: tabel ledger (mirip `token_ledger_entries`), `WaMessageQuotaGate` (mirip `TokenQuotaGate`), dan kolom kuota di `plans` (mirip `monthly_token_quota` + `emergency_token_quota`). Bukan mekanisme baru. **Wajib membedakan kategori pesan** marketing / utility / service, karena tarif Meta Indonesia berbeda (marketing Rp586,33; utility & service Rp356,65) dan sejak 1 Oktober 2026 **setiap nomor mendapat 1.000 service message gratis per bulan**. CS yang membalas dalam 24 jam setelah pelanggan chat masuk kategori service, jadi kuota gratis itu menutup sebagian besar pemakaian tenant kecil. Meter yang tidak membedakan kategori akan menagih klien untuk pesan yang sebenarnya gratis - itu langsung terasa sebagai penipuan. Test negatif lebih dulu: kuota habis menolak kirim (fail-closed, bukan diam-diam terkirim lalu ditagih); pesan service di dalam 1.000 gratis tidak mengurangi saldo; kategori tidak pernah dinaikkan menjadi marketing; batas 24 jam dihitung dari pesan terakhir pelanggan, bukan dari jam server; isolasi tenant; replay webhook status tidak menggandakan meter; pembulatan rupiah tidak pernah merugikan tenant. Demo: satu tenant demo menembus kuota, pengiriman ditolak dengan pesan jelas, dan barisnya terlihat di ledger. | `DEFERRED` **gelombang 4** — ditunda atas mandat Bos karena jalur resmi dikerjakan manual. **Risiko yang tetap hidup:** Struktur B (D-71) menaruh tagihan Meta di pihak kita sejak pesan pertama, dan penagihan manual tidak punya penjaga teknis — hanya disiplin. Ambangnya belum ditentukan: lihat **Q-13** |
| T-75 | Top-up dan tangga penghentian untuk pesan | T-74 | `HUMAN:SECRET` (Midtrans produksi) | `app/Services/Manual/`, `app/Services/Billing/DunningLadder.php`, `routes/api.php` | Sambungkan meter T-74 ke jalur pembayaran yang **sudah ada** - `/bot/master/topup`, `InvoiceCreationService`, Midtrans (D-34), dan tangga dunning D-49 - bukan membuat jalur pembayaran baru. Risiko kredit dari D-71 dikelola dengan saldo prabayar plus pemutusan bertahap. Test: saldo habis → CS berhenti mengirim tetapi web tetap hidup penuh (sejalan D-49 yang mematikan AI lebih dulu, bukan seluruh aplikasi); pembayaran masuk → aktif kembali tanpa intervensi manual; top-up idempoten terhadap replay webhook (pola `PaymentWebhookSecurityTest` yang sudah ada); tenant yang saldonya habis tidak pernah membuat kita menanggung biaya Meta satu pesan pun. Demo: siklus penuh dari saldo habis sampai aktif lagi setelah pembayaran. | `DEFERRED` **gelombang 4**, mengikuti T-74 |
| T-76 | WA #2: bot CS publik platform | T-62, T-63b, T-68 | `HUMAN:SECRET` (nomor kedua + scan/onboarding) | `hermes/souls/`, `app/Http/Controllers/Api/MasterBot/` | Profil `addon` platform untuk CS layanan Agentic BOS: prompt terkunci, tool baca-saja, **nol akses data tenant**. Untuk penanya yang sudah jadi pelanggan, ia memakai MasterBot API yang sudah ada (tiket, saldo, topup). **Gap yang perlu keputusan terpisah:** `AuthenticateMasterBot` menolak pemanggil yang belum punya company (T-17: "403 bila WA user tidak memiliki company"), jadi **calon pelanggan tidak bisa dibuatkan tiket** - penangkapan prospek belum ada di sistem dan berada di luar lingkup sampai Bos memutuskan. Test negatif lebih dulu: permintaan data tenant apa pun ditolak; upaya jailbreak dijawab pesan penolakan yang sudah ada di `config/hermes.php` (`guardrails.refusal_message`); bot tidak melayani grup (`allow_groups: false`); tool tulis tidak pernah tersedia; **nol kueri ke tabel ber-`company_id`** selama satu percakapan penuh; probe white-label T-62 tetap lulus. Demo: nomor asing japri WA #2 dan mendapat jawaban layanan, dengan bukti log nol kueri tenant. | `BLOCKED` T-62, T-63b, T-68 — **gelombang 4**, menunggu nomor WA kedua. Ketergantungan pada T-71 **dilepas**: bot CS boleh mulai dengan kanal bawaan, dan jalur resmi disiapkan manual (T-79) bila diperlukan |
| T-77 | Lapisan 2 diserahkan ke Hermes: T-51 jadi antarmuka | **H-03**, T-72 | | `app/Services/Team/TeamInvitationService.php`, `config/hermes.php` | Hermes **sudah punya** sistem yang sama bentuknya dengan T-51: kode sekali pakai lewat DM, `pending` → `approved`, `revoke`, rate limit dan lockout per platform (`gateway.pairing.PairingStore`, `platforms/pairing/whatsapp-{pending,approved}.json`, `_rate_limits.json`). Membiarkan dua sistem hidup berarti dua sumber kebenaran tentang siapa yang boleh bicara dengan bot. Keputusan Bos: **Hermes yang memegang**, T-51 menjadi antarmuka web di atasnya. `TeamInvitationService` memanggil `pairing approve/revoke` Hermes, dengan kuota `max_users` tetap ditegakkan **lebih dulu** di sisi kita sebelum memanggil (kalau tidak, kuota bisa terlampaui oleh undangan yang sudah beredar). T-51 lama **tidak dibongkar** sampai endpoint Hermes terbukti; peralihan lewat satu saklar konfigurasi sehingga bisa dikembalikan. Test: approve/revoke bolak-balik terhadap Hermes yang dipalsukan; kuota penuh menolak **sebelum** memanggil Hermes; pencabutan menutup akses seketika; Hermes gagal berarti undangan tidak dianggap beredar; saklar mati mengembalikan perilaku T-51 apa adanya. Demo: menyetujui satu permintaan pairing dari web kita, lalu `hermes pairing list` menampilkan orang itu sebagai approved. | `BLOCKED` **H-03**, T-72 — `PairingStore` sudah lengkap tetapi hanya terjangkau lewat CLI; permukaan HTTP-nya belum ada |
| T-78 | Rangkai, dokumentasi, dan gate penuh | seluruh Fase 10 | | `docs/EXECUTION_PLAN.md`, `docs/AUTOPILOT_STATUS.md`, `docs/worker-reports/` | `EXECUTION_PLAN.md` disegarkan, `AUTOPILOT_STATUS.md` diringkas, laporan worker ditulis termasuk daftar temuan yang sengaja tidak diperbaiki. Gate penuh: `DATA_SOURCE=json php artisan test`, `vendor/bin/pint --test`, `php artisan migrate:fresh --seed --force`, `npm run build`, dan `php artisan test --filter=MysqlParityTest` bila MySQL hidup. Ditambah satu putaran manual dari HP pada tenant nyata - sisa risiko yang berulang kali tercatat dan belum pernah ditutup. Demo: suite hijau, Pint bersih, dan catatan putaran manual dari HP. | `BLOCKED` Fase 10 |

### Amandemen D-70: jalur resmi dikerjakan manual (Bos, setelah T-66/T-67)

Kewajiban menyembunyikan kirimdev **dibatalkan**, dan jalur resmi tetap ada
sebagai pilihan tetapi **disiapkan tangan per tenant**. Akibatnya beberapa task
berat menjadi tidak perlu untuk sekarang, dan satu task ringan menggantikannya.

**Yang menjadi lebih mudah, bukan sekadar ditunda:** karena manual, jalur resmi
memakai **Meta Cloud API langsung**, bukan kirimdev. Adaptor `whatsapp_cloud`
Hermes sudah lengkap untuk itu (outbound Graph API, webhook dengan handshake
verify-token, HMAC `X-Hub-Signature-256` constant-time atas raw body, proteksi
replay `wamid`, media dengan batas ukuran per tipe, jendela 24 jam + fallback
template). `GRAPH_API_BASE = "https://graph.facebook.com"` yang tadinya menjadi
penghalang **justru sudah benar**. Jadi:

- **H-04 keluar dari jalur kritis.** Override base URL hanya diperlukan bila
  kirimdev diadopsi nanti.
- **T-71 tidak diperlukan untuk jalur manual.** Transport adalah konfigurasi
  Hermes per profil dan tidak terlihat oleh kode kita — batas abstraksinya
  memang sudah di sana. Kode kita tetap hanya mengenal "kirim lewat profil ini".
- **T-73 ditunda.** Kanal ditentukan kita saat menyiapkan, bukan dipilih tenant
  di layar.
- **T-74 + T-75 ditunda**, dengan risiko yang harus dinyatakan: Struktur B
  (D-71) menaruh tagihan Meta di pihak kita **sejak pesan pertama**, dan
  penagihan manual tidak punya penjaga teknis — hanya disiplin. Lihat **Q-13**
  di `00-DECISIONS.md`: ambang kapan meter wajib mendarat belum ditentukan.

| ID | Task | Depends On | Gate | File Target | Acceptance | State |
|---|---|---|---|---|---|---|
| T-79 | Runbook manual WABA resmi per tenant | T-66 | `HUMAN:SECRET`, `HUMAN:COST` | `docs/RUNBOOK_WABA_MANUAL.md` (baru) | Prosedur tertulis untuk menyiapkan satu tenant di jalur resmi dengan tangan, **tanpa kode baru**: (a) prasyarat Meta — Business Portfolio, verifikasi bisnis, WhatsApp Business Account, nomor yang belum terpakai di WhatsApp atau sudah dimigrasikan, System User permanent token, App Secret, verify token; (b) metode pembayaran atas nama kita (Struktur B, D-71); (c) env per profil — `WHATSAPP_CLOUD_PHONE_NUMBER_ID`, `_ACCESS_TOKEN`, `_APP_SECRET`, `_VERIFY_TOKEN`, `_WEBHOOK_HOST/PORT/PATH`, `_API_VERSION` ditaruh di `profiles/<tenant>/.env` karena adaptor membacanya lewat secret scope per profil, bukan `os.getenv` global; (d) **URL webhook publik HTTPS wajib** — adaptor menyatakannya, dan di PC ini jalurnya sudah ada lewat cloudflared; (e) **verifikasi bentrok port**: `WHATSAPP_CLOUD_WEBHOOK_PORT` bawaannya 8090, jadi dua profil yang sama-sama menjalankan webhook Cloud API akan bertabrakan — belum saya pastikan bagaimana Hermes menanganinya, dan runbook wajib menjawabnya sebelum tenant kedua; (f) catatan biaya: tarif Meta Indonesia marketing Rp586,33 dan utility/service Rp356,65 per pesan, dengan 1.000 service message gratis per nomor per bulan sejak 1 Oktober 2026; (g) **batas jujur** yang wajib disampaikan ke tenant: layar persetujuan Meta menampilkan platform yang diberi wewenang, dan memindahkan nomor keluar dari WABA kita merepotkan (D-71). Test: tidak ada (dokumen operasional); tetapi runbook wajib memuat langkah verifikasi yang bisa dijalankan orang lain, bukan hanya deskripsi. Demo: satu tenant nyata selesai disiapkan mengikuti runbook, oleh orang yang tidak menulis runbook-nya. | `DONE` (dokumen) — `docs/RUNBOOK_WABA_MANUAL.md` ditulis. Memuat prasyarat Meta, env per profil, pendaftaran webhook, **tujuh langkah verifikasi yang bisa dijalankan orang lain** (termasuk §4.4 tanda tangan HMAC palsu harus ditolak — langkah yang paling tidak boleh dilewati), biaya, tabel catatan per tenant dengan kolom port, prosedur penghentian, dan §9 daftar yang **belum diverifikasi**. **Demo belum terpenuhi**: runbook ditulis dari pembacaan kode adaptor, belum dari tenant yang berhasil dipasang. Penghalang untuk tenant **kedua** (bukan pertama): perilaku bentrok port 8090 antar profil belum dipastikan |

### Prasyarat sisi Hermes (repo lain, bukan antrean Agentic BOS)

T-66 dan T-67 membuktikan empat hal berikut **tidak ada** di Hermes hari ini.
Semuanya kecil secara kode, tetapi berada di repo `hermes-agent` sehingga bukan
pekerjaan yang bisa diselesaikan dari antrean ini. Diberi prefiks `H-` supaya
tidak tertukar dengan task kita. Masing-masing butuh keputusan Bos: **ditambal
lokal** (utang pemeliharaan setiap Hermes diperbarui) atau **diusulkan ke
upstream** (lebih bersih, tidak bisa dijadwalkan).

| ID | Pekerjaan di Hermes | Memblokir | Catatan |
|---|---|---|---|
| H-01 | **Endpoint kirim pesan.** Minimal: kirim teks ke satu nomor pada satu profil, dengan respons yang membedakan "terkirim" dari "diterima tetapi gagal". | T-69, T-71 | `api_server` tidak punya primitif pengiriman sama sekali. `hermes send` (CLI) ada tetapi **bukan jalur produk**: menjalankannya dari Laravel berarti memberi aplikasi web akses ke home Hermes yang memuat kredensial plaintext. Kandidat tempat: `gateway/delivery.py` dibungkus router baru. |
| H-02 | **Endpoint sesi WhatsApp:** mulai sesi, ambil QR, baca status, logout. | T-72 | Baileys adalah subprocess Node (`plugins/platforms/whatsapp/adapter.py`), QR hanya muncul di CLI/dashboard. Tanpa ini layar pairing tenant tidak mungkin dibuat. |
| H-03 | **Endpoint pairing pengguna:** list, approve, revoke. | T-77 | Logikanya **sudah lengkap** di `gateway/pairing.py` (`PairingStore`) beserta rate limit dan lockout; yang belum ada hanya pembungkus HTTP. Paling murah dari keempatnya. |
| ~~H-04~~ | ~~**Override base URL Cloud API.**~~ **KELUAR DARI JALUR KRITIS** setelah amandemen D-70: jalur resmi manual memakai Meta langsung, sehingga `GRAPH_API_BASE = "https://graph.facebook.com"` sudah benar. Hanya diperlukan lagi bila kirimdev diadopsi. | (tidak ada) | Tetap dicatat supaya tidak ditemukan ulang sebagai temuan baru nanti. |

Tempat paling wajar untuk H-01..H-03 adalah `hermes_cli/web_routers/` yang
sekarang hanya memuat `cron, git, mcp, profiles, sessions, skills, tools`.
Prefiks profil `/p/<profil>/` yang sudah ada di `api_server` sebaiknya dipakai
ulang supaya pengalamatan per tenant konsisten.

### Catatan risiko Fase 10

- **Kredensial plaintext di `config.yaml` Hermes.** Berkas itu memuat token GitHub
  (muncul dua kali: `delegation.api_key` dan env MCP github), satu API key provider
  lokal, serta hash dan secret basic-auth dashboard. Konsekuensi untuk fase ini:
  **jangan** memilih jalur "Laravel menjalankan `hermes send`" atau berbagi folder
  home Hermes, karena itu memberi aplikasi web akses ke seluruh kredensial
  tersebut. Jalur `api_server` dengan token tersendiri jauh lebih sempit.
  Terlepas dari fase ini, token GitHub itu sebaiknya dirotasi.
- **Struktur B memindahkan risiko ke kita.** Kita ditagih Meta lebih dulu dan
  klien belum tentu bayar; T-74 + T-75 adalah pengendalinya, dan keduanya wajib
  mendarat **sebelum** tenant pertama memakai jalur resmi.
- **Kita menjadi pemroses data** percakapan pelanggan klien. Perlu dasar
  persetujuan; `companies.privacy_accepted_at` + `AiDataSharingPolicy` sudah ada
  sebagai pola, tetapi keduanya company-scoped dan belum mencakup kasus ini.
- **Portabilitas keluar sulit.** Klien yang berhenti akan merasa terkunci karena
  memindahkan nomor antar WABA merepotkan. Ini perlu dinyatakan jujur di materi
  penjualan, bukan ditemukan klien saat mereka ingin pergi.
- **Onboarding jalur resmi tidak bisa disembunyikan sepenuhnya.** Layar
  persetujuan Meta menampilkan platform yang diberi wewenang. Satu langkah
  administratif, dilakukan pemilik usaha, bukan permukaan yang dilihat pelanggan
  mereka - tetapi jangan dijanjikan "sepenuhnya tersembunyi".
