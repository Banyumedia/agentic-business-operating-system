# Agentic BOS Autopilot Status

**Updated:** 2026-09-17 (T-F15 bukti D-31 `laundry.json` selesai; Fase 2 lengkap - menunggu `HUMAN:UI-LOCK`)
**Mode:** FASE 2 SELESAI - T-07 + T-F1..T-F15 DONE; **berhenti menunggu gate `HUMAN:UI-LOCK`** (bukan soal teknis, keputusan Bos)
**Arsitektur target:** puluhan jenis bisnis — industri = data, kapabilitas = kode (D-31..D-33)
**Canonical workspace:** `D:\PROJECTS\agentic-bos`
**Git:** branch `main`, HEAD lihat `git rev-parse --short HEAD`; **remote belum dikonfigurasi**.

> Agent yang resume: baca file ini, lalu `EXECUTION_PLAN.md` §0 untuk definisi
> `READY` dan command verifikasi. Jangan pakai angka/SHA dari ingatan sesi.

## Review Bisnis Menyeluruh 2026-09-16 (D-48..D-56)

Review ujung-ke-ujung seluruh dokumen. Hasil: 56 keputusan terkunci, **tidak
ada item OPEN**, semua keputusan punya task pelaksana.

**Kontradiksi diperbaiki:** D-32 (18→21 kapabilitas), D-36 & U-06 digantikan
D-43, PRD (nama warisan + "1 bot per company" → per owner), `companies.theme`
dan `admin_impersonation_sessions` ditambahkan ke skema.

**Kesalahan skema diperbaiki:** FK menggantung `approval_ticket_id` (tabel
`approval_tickets` kini didefinisikan §1.8), tabel hilang `cash_entries` (§4.4),
`quotations` + `quotation_lines` (§4.5).

**Task baru dari keputusan bisnis:** T-10c (mode hemat token, D-48), T-10d
(gerbang kapabilitas per paket, D-52), T-12b (trial + ekspor data, D-51), T-18
diperluas (tangga dunning, D-49), **Fase 4b T-27..T-27e** (kepatuhan PDP,
D-50 — memblokir penjualan preset klinik/apotek), Fase 6b (katalog add-on,
D-56).

**Yang belum berubah:** T-07 tetap task READY berikutnya. UI-LOCK belum
diberikan Bos. Fase 2 (frontend-first D-42) tidak terpengaruh review ini.
## Akses Pratinjau Jarak Jauh (untuk review dari HP)

| URL | Sumber | Port |
|---|---|---|
| `https://agentic-bos.nalar.army/` | worktree `main` (aplikasi nyata) | 8000 |
| `https://bos-mockup.nalar.army/mockup` | worktree `mockup/ux-dummy` (referensi visual) | 8001 |

Keduanya di balik basic auth Caddy (user `bos`) dan `noindex`. Dev server
dijalankan otomatis saat login Windows oleh
`%APPDATA%\Microsoft\Windows\Start Menu\Programs\Startup\AgenticBOS_DevServers.vbs`
(bukan folder di dalam repo ini). Aplikasi **belum punya auth sendiri**
(T-00c Fase 3) - basic auth adalah satu-satunya pelindung; jangan hapus
sebelum login aplikasi ada. Checkpoint rollback: tag `pre-fase-2`. **Catatan DNS:** Cloudflare Universal SSL hanya menutup satu tingkat subdomain (`*.nalar.army`); hostname dua tingkat seperti `mockup.agentic-bos.nalar.army` gagal TLS - karena itu dipakai `bos-mockup.nalar.army`.
## Kiro CLI sebagai Coding Executor (2026-09-16)

Kiro CLI dikonfigurasi sebagai delegate untuk eksekusi task coding dari Hermes bot.

| Item | Detail |
|---|---|
| Executable | `C:\Users\User\AppData\Local\Programs\Kiro\bin\kiro.cmd` |
| Startup | `%APPDATA%\...\Startup\Kiro_AgenticBOS.vbs` — buka PowerShell + `kiro chat` otomatis saat login |
| Working dir | `D:\PROJECTS\agentic-bos` |
| Kontrak prompt | `docs/KIRO_SKILL.md` (dibaca Kiro saat menerima delegasi) |
| Skill Hermes | `agentic-bos-coding-agent/skills/software-development/delegate-to-kiro/SKILL.md` |

**Cara kerja:** Hermes bot menerima perintah dari Telegram → baca `delegate-to-kiro` skill → susun prompt sesuai kontrak → kirim ke Kiro CLI → Kiro kerjakan task, commit, update STATUS → Hermes laporkan hasilnya balik ke Telegram.

**Auto-commit:** ✅ diizinkan (gate `HUMAN:COMMIT` sudah terbuka). Kiro commit tanpa tanya untuk pekerjaan lokal.
**Push:** ⛔ tetap butuh approval eksplisit.
**Paralel write:** ✅ diizinkan **bersyarat** — lihat `HERMES.md` §Parallel Writer Policy (6 syarat) dan `docs/EXECUTION_PLAN.md` §Matriks Grup Paralel untuk grup mana yang sedang boleh paralel. Merge ke main tetap serial.

## Worker Registry (Multi-Worktree Paralel)

| Worker | Path | DB |
|---|---|---|
| **main** | `D:\PROJECTS\agentic-bos` | `database\database.sqlite` |
| **worker-a** | `D:\PROJECTS\agentic-bos-worker-a` | `database\database.sqlite` (copy) |
| **worker-b** | `D:\PROJECTS\agentic-bos-worker-b` | `database\database.sqlite` (copy) |
| **worker-c** | `D:\PROJECTS\agentic-bos-worker-c` | `database\database.sqlite` (copy) |

**Assignment task tidak statis per worker** — cek `docs/EXECUTION_PLAN.md`
§Matriks Grup Paralel untuk grup mana yang sedang boleh paralel di fase
berjalan. **Klaim task = buat branch `task/{TASK-ID}`** di worktree yang
dipakai (bukan branch tetap `worker-a`/`worker-b`/`worker-c`); `git worktree
list` adalah registry klaim yang hidup — Git menolak dua worktree memakai
branch sama.

`vendor/` dan `node_modules/` = junction ke `main`. Jangan `composer install/update` dari worker.
Jangan tulis `docs/AUTOPILOT_STATUS.md` ini dari worker paralel — tulis
`docs/worker-reports/{TASK-ID}.md`; writer `main` yang merangkum ke sini
saat merge.

**Merge workflow setelah worker selesai:**
```bash
cd D:\PROJECTS\agentic-bos
php artisan test && vendor/bin/pint --test
git merge --no-ff task/{TASK-ID} -m "feat(scope): deskripsi"
```
Merge tetap serial — satu per satu. Cek `docs/KIRO_SKILL.md` §Worker Registry untuk detail lengkap.

## Verified Baseline (sumber tunggal)

| Item | Value | Cara verifikasi ulang |
|---|---|---|
| Laravel | 13.32.0 | `php artisan --version` |
| PHP | 8.3.30 | `php -v` |
| Livewire | 4.4 | `composer.json` |
| Tailwind | 4.3 (CSS-first `@theme`) | `package.json` |
| Test suite | **282 passed, 1199 assertions** | `php artisan test` |
| Style | **Pint clean, seluruh repo** | `vendor/bin/pint --test` |
| Build | Vite OK | `npm run build` |
| Business migrations | none (hanya `users/cache/jobs`) | `ls database/migrations` |
| Business models | none (hanya `User`) | `ls app/Models` |

## Gate Status

| Gate | Status |
|---|---|
| `HUMAN:COMMIT` | ✅ terbuka untuk pekerjaan lokal |
| `HUMAN:UI-LOCK` | ⛔ **belum** — menahan Fase 3+ |
| `HUMAN:SECRET` | ⛔ belum — fake/stub diizinkan di dev |
| `HUMAN:DEPLOY` | ⛔ belum |

## Keputusan OPEN yang Menahan Task

**Tidak ada.** Q-01..Q-08 sudah dijawab Bos 2026-09-16 dan dipromosikan menjadi
D-34..D-41 (lihat bagian OPEN di `00-DECISIONS.md`). Item OPEN berikutnya baru
muncul dari T-25 (pilihan modul Tier B). Aturan tetap: agent **tidak menebak**
item OPEN; tandai task `BLOCKED` lalu ambil task READY lain yang independen.

## Completion Ledger

| Task | State | Evidence |
|---|---|---|
| G-01..G-04 | DONE | `PRD_RECONCILIATION.md`, `EXECUTION_PLAN.md` rev 2026-09-16 |
| T-01 | DONE | stack terverifikasi |
| T-02 | DONE | `LobbyNavigationTest` (4). Kartu → route; tombol search punya handler + `aria-label` |
| T-03 | DONE (statis) | `DynamicMenuRegistryTest` (7) + `ModuleSidebarTest` (4). Unknown module → **nol DOM** |
| T-04 | DONE (dummy) | Alpine modal + `wire:model.live` |
| T-09 | DONE (merged ke T-00a) | — |
| T-07 | DONE | `ThemeContrastTest` + `SettingsThemeTest`; 5 tema x 36 token, per-usaha (D-43) |
| T-F1 | DONE | `DataSourceBindingTest`; kontrak `EntityRepository`/`PresetSource`/`CompanyContext` + `DATA_SOURCE` |
| T-F2 | DONE | `SchemaValidatorTest`; 15 `database/schemas/*.schema.json`, fail-closed |
| T-F3 | DONE | `JsonEntityRepository` + `JsonCompanyContext` + middleware; 3 folder tenant demo |
| T-F4 | DONE | `PresetDefinitionValidator` + `JsonPresetSource`; preset `bengkel`/`klinik`/`salon` |
| T-F4a | DONE (docs) | kontrak workflow + path kanonik `database/presets/{slug}.json` |
| T-F5 | DONE | `FeatureResolver` + `TerminologyResolver` + helper `term()`/`@term` |
| T-F6 | DONE | `WorkflowEngineTest` (11); `WorkflowEngine` + efek fake + log JSON |
| T-F7 | DONE | `DashboardTest`; `DashboardComposer` + `WidgetRegistry` |
| T-F8 | DONE | `ModuleSidebarTest` + `DynamicMenuRegistryTest` + `LobbyNavigationTest`; registry kapabilitas, 403/404 fail-closed |
| T-F9 | DONE | `ListScreenTest` (11); `ListScreen` + `<x-data-table>` + `<x-form-field>` dari schema |
| T-F9b | DONE | 48 fixture demo terisi + uji jumlah baris & integritas referensi; dua defect widget T-F7 diperbaiki |
| prep PG-1 | DONE | Dispatcher pola layar berbasis konvensi (`ModuleSidebarTest::test_screen_pattern_is_dispatched_to_a_component_by_convention`); membuka PG-1 (T-F10..T-F13) untuk paralel |
| T-F10 | DONE | `PipelineScreenTest` (12) + `CalendarScreenTest` (8); papan tahap dari `WorkflowEngine` + kalender harian/mingguan + `no_overlap` berbasis schema |
| T-F11 | DONE | Scope awal `084181f` + koreksi QA finansial T-F11R. |
| T-F12 | DONE | `SettingsCapabilityTabsTest` (10) + `OnboardingTest` (6); tab Fitur Bisnis/Istilah/Alur dari registry + PresetSource, onboarding form D-40 |
| T-F13 | DONE | `CommandPaletteDataSearchTest` (7); pencarian data lintas entity dari EntityRepository, scoped company, href nyata |
| T-F11R | DONE | Checkout server-authoritative; aggregate journaled/idempoten; identity fail-closed; invoice SaaS tidak masuk ledger operasional; dialog D-45+a11y; decimal bounded. |

## Arsitektur D-31 (dibaca sebelum menulis kode apa pun setelah UI-LOCK)

- Tidak ada kode/tabel/flag/komponen yang menyebut nama industri.
- Kapabilitas hanya dari katalog `INDUSTRY_PRESETS.md` §1 (D-32).
- Istilah via `term()`, alur via `WorkflowEngine`, dashboard via `DashboardComposer`.
- Fase 3b (mesin komposisi) **wajib selesai** sebelum tabel domain (Fase 3c).
- **D-42 (2026-09-16): Fase 2 = frontend-first dari JSON.** Semua layar dibangun dulu lewat `EntityRepository`/`PresetSource`/`CompanyContext` dengan implementasi `Json*`; Fase 3 mengganti ke `Eloquent*` via `DATA_SOURCE` tanpa mengubah Blade. JSON menggantikan tabel, bukan logika. Fase 2 = T-07 -> T-F1..T-F15 (kontrak, skema, resolver, WorkflowEngine, DashboardComposer, 6 pola layar, uji anti-hardcode, bukti `laundry.json`). T-05/T-06/T-03b/T-08b-e memindahkan **scope inti** ke Fase 2; adapter Eloquent, materialisasi workflow, dan persistence database tetap menjadi scope residual Fase 3. Stop line sekarang setelah T-F15. D-43 palet A (Slate+Emerald) locked, 5 tema tetap (A/B/C/D/E) dengan nilai lengkap di UX_UI_SPEC §7.3a. **Cakupan tema: per-USAHA (owner set, semua staf lihat sama), bukan per-user/localStorage** - koreksi dari draf awal T-07. Nilai CSS 5 tema siap dipakai T-07.
- T-21c membuktikan D-31 pada database nyata dengan men-seed ulang preset kanonik `laundry.json` yang sudah lolos bukti JSON T-F15, tanpa perubahan kode.

## Completed in Current Run

### T-07 — DONE (2026-09-16)

- **Scope:** design token `--erp-*`, lima tema, dan shell Settings.
- **Implemented:** registry 5 tema × 36 token; 11 pasangan kontras AA per tema; route `/app/settings`; tab WAI-ARIA dengan keyboard/deep-link; selector dari registry; persistensi JSON per-usaha; tema aktif diterapkan oleh shared layout.
- **Security:** properti Livewire sensitif dikunci; mutasi hanya owner dan fail-closed; query tidak dapat mengganti active company; penyimpanan memakai lock read/merge/write dan menolak JSON rusak tanpa overwrite.
- **Files:** `app/Livewire/Settings.php`, `app/Services/ThemeRegistry.php`, `app/Services/CompanySettingsStore.php`, `app/Providers/AppServiceProvider.php`, `resources/css/app.css`, `resources/views/livewire/settings.blade.php`, `resources/views/components/layouts/module.blade.php`, `resources/views/layouts/app.blade.php`, `routes/web.php`, `tests/Feature/SettingsThemeTest.php`, `tests/Unit/ThemeContrastTest.php`.
- **Evidence:** `php artisan test` → 79 passed / 239 assertions; `php vendor/bin/pint --test` → passed; `npm run build` → passed; route Settings → 1 route; `git diff --check` → clean; D-31 scan aplikasi/Blade → 0 temuan.
- **Review:** tiga lane read-only dijalankan; temuan tenant tampering, fail-open role, tema lintas halaman, dan lost-update/JSON korup diperbaiki serta diuji.
- **Remaining risk:** autentikasi/otorisasi produksi dan `CompanyContext` resmi masuk task fondasi Fase 2 berikutnya; T-07 tidak menambah migration/model bisnis.

### T-F1 — DONE (2026-09-16)

- **Implemented:** kontrak `EntityRepository`, `PresetSource`, `CompanyContext`; `DATA_SOURCE` config; provider binding deferred ke kelas `Json*`; binding `eloquent` fail dengan exception Fase 3 yang eksplisit; driver asing ditolak.
- **Files:** `.env.example`, `app/Contracts/*.php`, `app/Providers/DataSourceServiceProvider.php`, `bootstrap/providers.php`, `config/datasource.php`, `tests/Feature/DataSourceBindingTest.php`.
- **Evidence:** RED binding test 0/3; GREEN focused 3/3 (10 assertions); full `php artisan test` 82/82 (249 assertions); `php vendor/bin/pint --test` passed; `git diff --check` clean.
- **Review:** scope 8 file kecil, tanpa schema/auth/money/API; self-review D-31/D-42 lulus. Kelas `Json*` sengaja deferred ke T-F3/T-F4 sesuai dependency plan.

### T-F2 — DONE (2026-09-16)

- **Implemented:** 15 schema entitas netral industri, metadata migration-ready (type/nullable/default/length/precision/index/unique/reference), loader schema fail-closed, dan validator row untuk required/allowlist/type/enum/format/bounds/precision.
- **Security:** `company_id` dilarang di payload/schema (scope implisit folder), path traversal ditolak, key `attributes` asing ditolak, metadata schema rusak dan angka non-finite ditolak.
- **Files:** `database/schemas/*.schema.json` (15), `app/Services/Schema/EntitySchema.php`, `app/Services/Schema/SchemaValidator.php`, `tests/Unit/SchemaValidatorTest.php`.
- **Evidence:** RED focused 0/20; GREEN focused 43/43 (131 assertions); validator load `15 schemas valid`; full `php artisan test` 125/125 (380 assertions); `php vendor/bin/pint --test` passed; `git diff --check` clean.
- **Review:** tiga lane read-only + re-review; divergence terhadap `DATA_MODEL`, shallow schema metadata, list/object confusion, malformed enum, constraint storage, dan non-finite number diperbaiki. Tier B tetap dikecualikan dari Fase 2.
- **Remaining risk:** `invoices` mengikuti D-23/`DATA_MODEL` (billing SaaS); kebutuhan ledger invoice bisnis pada T-F11 harus direkonsiliasi sebelum T-F11 tanpa mengubah keputusan diam-diam.

### T-F3 — DONE (2026-09-16; review follow-up closed)

- **Decision:** D-41 diperjelas: adapter query+session hanya untuk `local`/`testing`, hanya tiga company demo allowlist; environment lain fail-closed dan Fase 3 tetap memakai `users.current_company_id`.
- **Implemented:** `JsonCompanyContext`; repository JSON tenant-scoped dengan read/find/save/filter/sort/pagination, schema validation, lock + atomic replacement; 45 fixture entitas + 3 fixture identitas usaha; Settings dan global theme memakai context tervalidasi; middleware melindungi seluruh `/app/*`.
- **Security:** unknown/traversal company ditolak; HTTP tenant route di luar demo environment fail-closed; repository dan mutating Livewire action memvalidasi ulang company aktif; role dibaca ulang server-side; short write, malformed JSON, object-root, row non-object, dan invalid schema row ditolak tanpa overwrite.
- **Files:** `app/Services/Json/*`, `app/Http/Middleware/EnsureCompanyContext.php`, `app/Contracts/EntityRepository.php`, `app/Livewire/Settings.php`, `app/Providers/AppServiceProvider.php`, `config/datasource.php`, `database/schemas/orders.schema.json`, `routes/web.php`, `storage/app/.gitignore`, `storage/app/json/*`, tests fitur terkait, `docs/00-DECISIONS.md`.
- **Evidence:** RED review follow-up 14/19 (HTTP production, stale Livewire, short write, identity fixture gagal); GREEN focused 19/19 (113 assertions); committed entity fixture 45/45 schema-valid + 3 identity reference valid; full `php artisan test` 139/139 (463 assertions); `php vendor/bin/pint --test` passed; `npm run build` passed (Vite 938 ms); `git diff --check` clean; HTTP Settings allowlisted 200 + `context-render-ok`.
- **Review:** dua lane read-only; seluruh HIGH ditutup. MEDIUM stale Livewire, HTTP production, short write, query boundaries, committed corpus, dan unresolved identity reference ditutup dengan negative/acceptance tests.
- **Remaining risk:** adapter ini sengaja demo-only; production tetap tidak dapat memakai context JSON sampai auth/company persistence Fase 3 tersedia.

## Detail Task Selesai (T-F4a)

### T-F4a — DONE (2026-09-16)

- **Implemented:** kontrak workflow menetapkan `stages[0]` sebagai awal, `terminal`, reachability/dead-end, arah transisi berdasar indeks `stages[]`, dan `requires_note: true` untuk transisi mundur; contoh rental valid dan key entity konsisten plural.
- **Canonical source:** seluruh dokumentasi memakai `database/presets/{slug}.json`; T-08 menambah tujuh preset awal tersisa di path yang sama lalu seeder membaca seluruh file tanpa salinan folder seeder.
- **Plan alignment:** acceptance T-F4 mengunci workflow bengkel/klinik/salon; state T-07/T-F1/T-F2/T-F3 diselaraskan; scope inti vs residual Eloquent T-08b-e diperjelas; bukti T-21c/T-23/T-24 tidak lagi membuat ulang preset.
- **Files:** `docs/INDUSTRY_PRESETS.md`, `docs/DATA_MODEL.md`, `docs/EXECUTION_PLAN.md`, `docs/AUTOPILOT_STATUS.md`.
- **Evidence:** tiga lane review read-only direkonsiliasi; full `php artisan test` 139/139 (463 assertions); `php vendor/bin/pint --test` passed; `git diff --check` clean; build N/A (docs-only).
- **Remaining risk:** enam preset D-01 + `custom` sengaja tetap dibuat pada T-08/Fase 3 di path kanonik; implementasi Fase 2 berikutnya hanya tiga preset demo T-F4.

## Task Aktif

**Tidak ada task berjalan. Fase 2 lengkap (T-07 -> T-F1..T-F15 semua `DONE`).**
Autopilot **berhenti** di gate `HUMAN:UI-LOCK` sesuai Stop Line `HERMES.md`.
Menunggu Bos melihat aplikasi utuh berjalan untuk `bengkel`, `klinik`, `salon`,
`laundry` (semua `DATA_SOURCE=json`) dan menyatakan Fase 2 `LOCKED` sebelum
Fase 3 (fondasi tenant database nyata) boleh dimulai. Ini persetujuan
visual + alur, bukan keputusan teknis - jangan dilanjutkan tanpa konfirmasi
eksplisit.

**URL demo per preset** (jalankan `php artisan serve` lalu buka):
- Bengkel: `/?company=bengkel-arka`
- Klinik: `/?company=klinik-sehat`
- Salon: `/?company=salon-ayu`
- Laundry (baru, T-F15): `/?company=laundry-bersih`

## Detail Task Selesai (T-F15)

### T-F15 — DONE (2026-09-17)

- **Latar:** bukti awal D-31 ("industri = data, kapabilitas = kode") pada industri **ke-4** yang belum pernah ada saat kode kapabilitas (`app/`, `resources/views/`) ditulis - satu-satunya cara task ini bisa `DONE` adalah kalau menambah industri baru benar-benar tidak butuh kode baru.
- **`database/presets/laundry.json` (baru):** Tier A murni, 7 kapabilitas dari katalog terkunci D-32 (`contacts`, `inventory`, `pos`, `approval_flow`, `finance.cashbook`, `hr.employees`, `system.ai_agent` - dependensi `system.ai_agent` -> `approval_flow` terpenuhi), terminologi (`contact->Pelanggan`, `item->Layanan`, `order->Cucian`, `staff->Karyawan`), workflow `orders` 5-stage (`terima -> proses -> siap_diambil -> diambil`, `dibatalkan` dari mana pun butuh approval, mundur `proses->terima` butuh catatan), widget dashboard (`low_stock`, `kpi_cashflow`, `pending_approvals`), urutan menu. Tidak ada kunci kapabilitas baru dibuat, tidak ada modul Tier B.
- **`storage/app/json/laundry-bersih/*.json` (baru, 9 file):** `business_identity`, `contacts` (5 pelanggan + 1 vendor), `employees` (4), `items` (4 jasa + 2 barang), `item_batches` (2 baris, salah satu di bawah `min_stock` supaya widget `low_stock` punya data nyata), `orders` (6 baris mengisi seluruh 5 stage termasuk `dibatalkan`), `order_lines` (8 baris, aritmetika `qty*unit_price-discount=line_total` diverifikasi), `cash_entries` (6), `assistant_report` (1). Seluruh referensi FK (`contact_id`, `order_id`, `item_id`, `source_id`) menunjuk baris yang benar-benar ada di file sibling.
- **Zero-code proof:** `git status` selama task ini hanya menunjukkan file baru di `database/presets/` dan `storage/app/json/laundry-bersih/`, plus 4 file test yang diadaptasi (bukan file `app/` atau `resources/views/` apa pun). Ini bukti langsung acceptance "diff di luar `database/presets/`, `storage/app/json/`, dan test = kosong".
- **Test yang diadaptasi (bukan dilemahkan):** (a) `JsonPresetSourceTest` - daftar 3 preset jadi 4 (urutan alfabetis `bengkel, klinik, laundry, salon`), tambah assertion transisi/terminal workflow laundry; (b) `JsonDataSourceTest` - test "48 file schema-valid" dipersempit ke glob eksplisit 3 company asli (komentar menjelaskan kenapa: laundry tidak mengisi seluruh 16 schema), ditambah test baru independen yang memvalidasi schema + integritas referensial khusus `laundry-bersih`; (c) `CommittedCapabilityFixtureTest` - tambah satu case `laundry-bersih` ke map yang sudah ada; (d) `tests/Architecture/RenderAllPresetsTest.php` - tambah `laundry-bersih` ke slug map preset + test baru yang membuktikan `/app/pos` (kasir menampilkan katalog jasa laundry), `/app/pos/pipeline` (kanban "Papan Cucian" dengan stage "Diterima"), `/app/contacts`, `/app/inventory` render 200 dengan konten nyata, dan `/app/projects`/`/app/bookings` tetap 403 (kapabilitas off, zero-bloat tetap tegak untuk company baru).
- **QA independen (`semantic_reviewer` sub-agent) dijalankan sebelum commit:** cross-check terhadap D-31/D-32/D-33, verifikasi manual seluruh FK fixture baru, retrace graf workflow terhadap aturan `PresetDefinitionValidator` (reachability + terminal-reachability), dan verifikasi keempat edit test tidak melemahkan assertion apa pun (semua additive/scoped dengan alasan tertulis). **Verdict: APPROVED.** Satu catatan non-blocking: transisi mundur `proses->terima` agak tidak umum untuk alur laundry (mekanis valid, sudah wajib catatan) - dicatat sebagai desain yang disengaja (analog rework QC di preset bengkel: pelanggan menambah cucian saat proses berjalan), bukan defect.
- **Files:** `database/presets/laundry.json` (baru), `storage/app/json/laundry-bersih/*.json` (baru, 9 file), `tests/Architecture/RenderAllPresetsTest.php`, `tests/Feature/{JsonPresetSourceTest,JsonDataSourceTest,CommittedCapabilityFixtureTest}.php`.
- **Evidence:** focused (`JsonPresetSourceTest`, `RenderAllPresetsTest`, `JsonDataSourceTest`, `CommittedCapabilityFixtureTest`) 33/33 (414 assertions); full `php artisan test` **300/300 (1293 assertions)**; `vendor/bin/pint --test` passed (100 files, tidak ada file PHP baru); `npm run build` passed (Vite ~1.7s, tidak ada Blade/CSS/JS yang berubah); `git diff --check` clean; `git status` sebelum staging mengonfirmasi nol perubahan di luar `database/presets/`, `storage/app/json/`, `tests/`; `bengkel-arka`/`klinik-sehat`/`salon-ayu` tidak tersentuh.
- **Remaining risk:** (a) transisi mundur `proses->terima` - dicatat di atas, bukan blocker; (b) glob 3-company yang dipersempit di `JsonDataSourceTest` memakai daftar eksplisit (`{bengkel-arka,klinik-sehat,salon-ayu}`), bukan pola exclude generik - company Tier A kelima di masa depan (bila punya fixture 16-schema lengkap) perlu ditambahkan manual ke daftar itu atau diberi test sibling seperti laundry, ini trade-off eksplisit yang didokumentasikan komentar, bukan gap diam-diam; (c) **Fase 2 sekarang lengkap** - autopilot berhenti di gate `HUMAN:UI-LOCK`, menunggu Bos.

## Detail Task Selesai (T-F14)

### T-F14 — DONE (2026-09-17)

- **Latar:** sesi sebelumnya (worker paralel, commit `37a131e` + `b9b379a` di `main`) sudah menambah `tests/Architecture/{NoIndustryHardcodeTest,NoLiteralTermsTest,RenderAllPresetsTest}.php` dan menandai T-F14 `DONE` di `EXECUTION_PLAN.md`, tapi meninggalkan defect yang tidak terlihat karena test barunya **tidak pernah benar-benar dijalankan** oleh `php artisan test` (lihat poin di bawah). Sesi ini melanjutkan resume dengan `git status`/`git diff` dulu (tidak ada perubahan uncommitted - semua sudah ter-commit di `main` sebelumnya), lalu menjalankan verifikasi wajib dan menemukan 3 test gagal.
- **Defect 1 - suite tidak terpasang:** direktori `tests/Architecture` tidak terdaftar di `phpunit.xml`, jadi `php artisan test` melewatkannya sama sekali walau filenya ada. **Diperbaiki:** menambah `<testsuite name="Architecture"><directory>tests/Architecture</directory></testsuite>`. Tanpa ini klaim "PASS" pada commit sebelumnya tidak pernah diverifikasi oleh command yang diwajibkan `HERMES.md`.
- **Defect 2 - capability key Tier B diganti tanpa otorisasi:** untuk meloloskan `NoIndustryHardcodeTest` (regex `pharmacy` cocok pada kata `pharmacy` di manapun), commit sebelumnya mengganti kunci kapabilitas `pharmacy.prescription` → `pos.prescription` di `FeatureResolver.php`, `PresetDefinitionValidator.php`, `DynamicMenuRegistry.php`. Ini **melanggar D-32/D-33** - `pharmacy.prescription` adalah kunci kapabilitas Tier B yang terkunci keputusan, bukan nama industri yang harus dihapus; D-31 menyasar cabang kode bernama industri (`if ($industry === 'apotek')`), bukan identifier kapabilitas yang kebetulan mengandung kata itu. Perubahan ini juga mematahkan `tests/Unit/PresetDefinitionValidatorTest::test_tier_b_dependency_is_fail_closed` yang masih memakai kunci lama. **Diperbaiki:** kunci dikembalikan ke `pharmacy.prescription` di tiga file tersebut; test regex diberi pengecualian eksplisit untuk literal `pharmacy.prescription` (dengan komentar yang merujuk D-32/D-33), bukan mengubah keputusan.
- **Defect 3 - nama produk terkunci diubah untuk lolos test:** untuk meloloskan `NoLiteralTermsTest` (kata "Karyawan" dianggap istilah kamus terminologi yang harus lewat `term()`), commit sebelumnya mengganti "Laporan Karyawan AI" -> "Laporan Asisten AI" di `dashboard.blade.php`. Ini salah: "Karyawan AI" adalah **nama produk tetap** (D-30, `COMMERCIAL_AND_AI_AGENTIC_SPEC.md`) untuk asisten Hermes, bukan istilah dictionary per-industri seperti `staff`/`contact` - tab Settings yang sudah ada tetap memakai "Karyawan AI", jadi perubahan itu membuat penamaan tidak konsisten dan mematahkan `DashboardTest::test_dashboard_is_composed_from_the_active_company_preset_and_json_data`. **Diperbaiki:** dikembalikan ke "Laporan Karyawan AI"; test regex diberi pengecualian eksplisit untuk frasa "Karyawan AI" (dengan komentar penjelas), bukan mengubah nama produk.
- **Prinsip perbaikan:** ketiga defect diperbaiki dengan mengoreksi TEST agar mengenkode pengecualian yang sudah didokumentasikan di keputusan terkunci (D-30, D-32, D-33), bukan dengan mengubah kode produksi/produk supaya cocok dengan regex naif. Kode produksi (`FeatureResolver`, `PresetDefinitionValidator`, `DynamicMenuRegistry`, `dashboard.blade.php`) dikembalikan ke keadaan benar dari T-F13R; hanya file test dan `phpunit.xml` yang berubah untuk menutup gap ini.
- **Files:** `phpunit.xml` (daftarkan suite Architecture), `app/Services/FeatureResolver.php`, `app/Services/Preset/PresetDefinitionValidator.php`, `app/Services/DynamicMenuRegistry.php`, `resources/views/livewire/dashboard.blade.php` (revert ke state T-F13R yang benar), `tests/Architecture/{NoIndustryHardcodeTest,NoLiteralTermsTest}.php` (pengecualian terdokumentasi), `docs/EXECUTION_PLAN.md`, `docs/AUTOPILOT_STATUS.md`.
- **Evidence:** `tests/Architecture` 3/3 (4 assertions, sekarang benar-benar berjalan lewat `php artisan test`); full `php artisan test` **298/298 (1234 assertions)**; `vendor/bin/pint --test` passed (100 files, 4 style issue di file Architecture baru diperbaiki via `vendor/bin/pint`); `npm run build` passed (Vite ~1.4s); `git diff --check` clean; `git status --short` sebelum mulai menunjukkan worktree bersih (tidak ada perubahan uncommitted dari sesi sebelumnya, semuanya sudah di `main`).
- **Acceptance T-F14 (4 poin) diverifikasi:** (1) `NoIndustryHardcodeTest` grep nama industri di `app/`+`resources/` = 0 (dengan pengecualian D-32 di atas); (2) `NoLiteralTermsTest` grep istilah kamus §3 literal di Blade = 0 (dengan pengecualian D-30 di atas); (3) `RenderAllPresetsTest` termasuk audit a11y dasar (test method `a11y attributes`); (4) `RenderAllPresetsTest::test_all_routes_render_for_all_presets_without_exception` merender lobby/dashboard/settings untuk 3 preset demo (`bengkel`, `klinik`, `salon`) -> 200, tanpa exception.
- **Remaining risk:** (a) audit a11y `RenderAllPresetsTest` mencakup pemeriksaan atribut dasar, bukan audit WCAG penuh — konsisten dengan batasan tool otomatis, verifikasi assistive-technology manual tetap disarankan sebelum rilis; (b) `NoLiteralTermsTest` memakai daftar literal manual (`Pelanggan, Klien, Pasien, ...`) - istilah baru yang ditambah ke kamus §3 di masa depan tidak otomatis terdeteksi kecuali daftar ini diperbarui; (c) T-F15 (bukti `laundry.json`) sekarang `READY` sesuai `EXECUTION_PLAN.md`, tapi berhenti di gate `HUMAN:UI-LOCK` setelah selesai sesuai Stop Line `HERMES.md`.

## Detail Task Selesai (T-F13R)

### T-F13R — DONE (2026-09-17)

- **Latar:** QA independen menemukan defect fungsional pada T-F13 yang sudah dicatat sejak awal tapi belum diperbaiki (lihat "Remaining risk (a)" pada catatan T-F13 di bawah), plus jalur error-handling yang menyamarkan kegagalan nyata sebagai "tidak ada hasil".
- **(1) Registry diperbaiki:** dua item `DynamicMenuRegistry` (`projects/timesheet`, `hrd/attendance`) yang merujuk entity `'timesheets'` (tanpa schema) diubah ke `'timesheet_entries'` (schema kanonik yang sudah ada sejak T-F2). Tidak ada schema/alias baru dibuat.
- **(2) Route `/app/hrd/attendance` dibuktikan render 200** lewat test acceptance baru (`test_hrd_attendance_route_renders_successfully_with_the_canonical_schema`) memakai datasource JSON terisolasi (`storage_path('framework/testing/palette-...')`), bukan fixture demo asli.
- **(3) Pencarian timesheet dibuktikan end-to-end:** baris unik ditambah ke `timesheet_entries`, diverifikasi kembali sebagai `type=Data` dengan `title`/`module`/`url` tepat dari `viewData('results')` langsung (bukan `preg_match` pada `<a>` pertama di HTML — pola lama diganti di seluruh file test, termasuk test href yang sudah ada), lalu `url` hasil di-GET dan `assertOk()`.
- **(4) `catch (Throwable)` generik dihapus dari `searchEntityData()`:** `EntitySchema::load()` dan `EntityRepository::query()` sekarang dibiarkan melempar apa adanya. Schema hilang (`InvalidArgumentException`), JSON company rusak (`JsonException`), atau kegagalan repository lain sekarang menggagalkan request secara terlihat, bukan diam-diam jadi hasil kosong. Satu-satunya `catch` yang tersisa di jalur ini adalah `CompanyContext::current()` melempar `LogicException` saat company belum dipilih sama sekali (kondisi normal, kontrak jelas, bukan korupsi). Dibuktikan negatif oleh dua test baru: schema hilang dari registry palsu, dan file JSON entity yang dirusak langsung — keduanya sekarang melempar exception yang sama seperti dilempar layer di bawahnya, tidak ditangkap.
- **(5) Negative test tenant stale-component ditambah:** `CommandPalette` di-mount saat company A aktif, data unik dibuat di A dan B, company aktif dipindah ke B **setelah** component hidup (tanpa remount), lalu `search` di-update pada component yang sama — hasil hanya menampilkan data B. Component tidak menyimpan company di properti manapun sehingga tidak ada state basi untuk dibocorkan; `EntityRepository::for()` tetap lapis kedua yang menolak company yang tidak cocok dengan context aktif saat ini.
- **(6) Komentar performa diperbaiki:** dokumentasi lama menyatakan `MAX_PER_ENTITY`/`MAX_DATA_RESULTS` mencegah "memindai berlebihan" — salah, karena `JsonEntityRepository::query()` selalu membaca seluruh isi file entity sebelum memfilter/memotong. Komentar baru menyatakan jujur bahwa kedua konstanta ini membatasi jumlah **hasil yang ditampilkan**, bukan jumlah baris yang dipindai. `JsonEntityRepository` tidak diubah/dioptimasi (di luar scope), tidak ada dependency/search engine ditambahkan.
- **(7) Batas hasil didokumentasikan jujur sebagai 16, bukan 8:** menu dan data adalah dua kuota terpisah (masing-masing dengan konstanta sendiri, `MAX_MENU_RESULTS = 8` baru ditambah menggantikan angka `8` yang sebelumnya hardcoded inline, dan `MAX_DATA_RESULTS = 8`), sehingga total tampilan bisa mencapai 16 baris. Ini bukan perubahan UX baru — perilaku render tidak berubah dari T-F13, hanya nama konstanta dan komentar yang sekarang jujur soal jumlahnya.
- **Files:** `app/Services/DynamicMenuRegistry.php`, `app/Livewire/CommandPalette.php`, `tests/Feature/CommandPaletteDataSearchTest.php`, `docs/EXECUTION_PLAN.md`, `docs/AUTOPILOT_STATUS.md`.
- **Evidence:** focused `CommandPaletteDataSearchTest` 12/12 (27 assertions); focused registry/sidebar/route regresi (`DynamicMenuRegistryTest`, `ModuleSidebarTest`, `ListScreenTest`, `LobbyNavigationTest`) 34/34 (220 assertions); full `php artisan test` **294/294 (1226 assertions)**; `vendor/bin/pint --test` passed (97 files, tidak ada file baru); `npm run build` passed (Vite ~1.2s, tidak ada Blade/CSS/JS yang berubah); `git diff --check` clean; `git status --short` sebelum staging hanya menunjukkan 3 file yang memang jadi target task ini (tidak ada perubahan asing dari writer paralel lain).
- **Self-review sebelum commit:** tenant isolation diverifikasi lewat test stale-component (poin 5) dan test scoped-company yang sudah ada; fail-closed/error visibility diverifikasi lewat dua test negatif baru (poin 4); D-31 (grep nama industri = 0 pada file yang disentuh) dan D-42 (tidak ada `DB::`/Eloquent/model/migration bisnis ditambahkan — hanya edit `EntityRepository`/`DynamicMenuRegistry` yang sudah ada) tetap terjaga; test baru menulis lewat `datasource.json_path` terisolasi, tidak menyentuh `storage/app/json/{bengkel-arka,klinik-sehat,salon-ayu}` asli; URL hasil pencarian (`/app/contacts`, `/app/hrd/attendance`) dibuktikan benar-benar route yang bisa dibuka via `$this->get(...)->assertOk()`, bukan string tidak kosong.
- **Remaining risk:** (a) href hasil data tetap mengarah ke halaman list module, bukan baris spesifik — jujur sesuai arsitektur Fase 2 saat ini, tidak berubah dari T-F13, akan berubah bila pola layar detail-per-baris ditambahkan; (b) Scout (U-07) tetap menyusul Fase 3 sesuai rencana awal T-F13; (c) test `test_missing_schema_from_a_broken_registry_entry_fails_visibly` memakai anonymous subclass `DynamicMenuRegistry` untuk mensimulasikan registry rusak — pola ini sengaja dipilih supaya test tidak bergantung pada defect nyata di registry produksi (yang sekarang sudah bersih), sehingga test ini tetap relevan sebagai regresi meskipun registry sudah benar.

## Detail Task Selesai (T-F13)

### T-F13 — DONE (2026-09-17, dikoreksi oleh T-F13R)

> **Catatan koreksi:** task ini sempat diturunkan ke status `PARTIAL` secara
> implisit setelah QA independen menemukan defect registry `timesheets` dan
> `catch (Throwable)` generik yang menyamarkannya (lihat "Remaining risk (a)"
> di bawah). Kedua defect itu sudah diperbaiki oleh **T-F13R** (lihat detail
> di atas); catatan asli di bawah ini dipertahankan sebagai riwayat, bukan
> kondisi kode saat ini.

- **Latar:** `CommandPalette` sejak T-F8 hanya mencari MENU statis dari `DynamicMenuRegistry`, belum data sungguhan -- tidak memenuhi acceptance "Universal Search dari repository" (U-07).
- **`searchEntityData()` (baru):** memakai `EntityRepository::query(['_search' => ..., '_per_page' => ...])` untuk setiap entity yang benar-benar tampil di menu company aktif (dari `registry->visibleModules()`/`menusFor()`) - bukan memindai seluruh 15 schema. Kapabilitas yang mati untuk company otomatis tidak tersentuh karena registry sudah menegakkan zero-bloat/D-31 lebih dulu.
- **Judul hasil data** memakai `SchemaPresenter::titleField()` (kolom `name`->`title`->string pertama) yang sama dipakai `ListScreen`/`PipelineScreen`/`CalendarScreen`, bukan logika baru.
- **`href` nyata, bukan deep-link palsu:** pola layar Fase 2 (`ListScreen`) belum punya route per-baris (list+inline edit, tidak ada halaman detail). Hasil data karena itu diarahkan ke route dasar module (mis. `/app/contacts`) yang benar-benar ada, dipilih via `searchableEntities()` yang memprioritaskan item menu berpola `list/ledger/pipeline/calendar`. Test membuktikan href hasil pencarian di-GET sungguhan dan `assertOk()`, bukan sekadar string tidak kosong.
- **Scoped company:** `searchEntityData()` mengambil `CompanyContext::current()` sendiri (fail-closed ke hasil kosong bila context invalid), lalu `EntityRepository::for($company, $entity)` -- repository sendiri menolak akses lintas company (`LogicException`), jadi kebocoran data antar tenant dicegah dua lapis.
- **Batasan volume:** `MAX_PER_ENTITY = 3` dan `MAX_DATA_RESULTS = 8` agar satu pencarian tidak memindai/menumpuk hasil tanpa batas saat banyak entity cocok.
- **Defect registry pre-existing ditemukan (dicatat, tidak diperbaiki - di luar file target task ini):** dua item `DynamicMenuRegistry` (`projects/timesheet`, `hrd/attendance`) merujuk entity `'timesheets'`, padahal schema yang ada adalah `timesheet_entries.schema.json`. `EntitySchema::load('timesheets')` melempar `InvalidArgumentException`. `searchEntityData()` menangkap ini per-entity (`try/catch` di sekitar `EntitySchema::load()`) sehingga satu entity yang rusak tidak menggagalkan seluruh pencarian - konsisten dengan pola `searchableMenus()` yang sudah menangkap `LogicException`. **Perlu task terpisah** untuk memperbaiki nama entity di registry atau menambah schema `timesheets`.
- **Defect nyata lain ditemukan & diperbaiki sebelum lanjut (commit terpisah `1ac52b8`, sebelum T-F13 dimulai):** `WidgetRegistry::upcomingSchedule()` memakai `new DateTimeImmutable('now')` yang mengabaikan `Carbon::setTestNow()`/`travelTo()` Laravel; test lolos hanya secara kebetulan selama jam server belum melewati tanggal fixture demo (Sept 2026) dan mulai gagal begitu waktu nyata melewatinya. Diganti `now()->toDateTimeImmutable()`.
- **Insiden proses (ditemukan & diperbaiki sebelum commit):** draft awal test menulis `EntityRepository::save()` tanpa mengisolasi `datasource.json_path`, sehingga tiga file demo asli (`storage/app/json/{bengkel-arka,klinik-sehat}/contacts.json`, `.../projects.json`) tercemar data uji. Terdeteksi dari `git status` sebelum commit, dipulihkan via `git checkout --`, dan test diperbaiki memakai path `storage_path('framework/testing/palette-...')` + `tearDown()` seperti pola `ListScreenTest`/`CashierScreenTest`. Tidak ada data demo yang ikut ter-commit.
- **Files:** `app/Livewire/CommandPalette.php`, `tests/Feature/CommandPaletteDataSearchTest.php` (baru).
- **Evidence:** focused `CommandPaletteDataSearchTest` 7/7 (12 assertions); regresi `ModuleSidebarTest` (test command palette lama) tetap hijau; full `php artisan test` **289/289 (1211 assertions)**; `vendor/bin/pint --test` passed (97 files); `git diff --check` clean; build dilewati (tidak ada Blade/CSS/JS yang berubah - diverifikasi via `git status --short`); scan D-31 pada file yang disentuh -> 0 nama industri, 0 `DB::`.
- **Remaining risk:** (a) mismatch entity `timesheets` di registry (lihat di atas) - task terpisah; (b) href hasil data mengarah ke halaman list module, bukan baris spesifik - jujur sesuai arsitektur Fase 2 saat ini, akan berubah bila pola layar detail-per-baris ditambahkan; (c) Scout (U-07) tetap menyusul Fase 3 sesuai rencana awal task ini, pencarian saat ini murni `EntityRepository::query()` substring.

## Detail Task Selesai (T-F12)

### T-F12 — DONE (2026-09-17)

- **`SettingsTabRegistry`:** dipakai apa adanya (sudah ada di worktree dari sesi sebelumnya). `Settings.php` tidak lagi mendeklarasikan `$tabs` statis; tab dan visibilitasnya diselesaikan lewat `visibleTo($role)` setiap `mount()`, konsisten dengan pola `DynamicMenuRegistry`. Role yang belum diketahui (`null`/lainnya) diperlakukan sebagai `staff` (paling terbatas), bukan tanpa tab sama sekali — analog `canManageTheme`.
- **Zero-bloat role-aware:** staff tidak melihat tab "Tim & Akses" sama sekali di DOM (bukan disabled) — dibuktikan test string HTML tidak memuat `id="tab-team"` untuk staff, dan memuatnya untuk owner.
- **Tab Fitur Bisnis:** dropdown preset dibaca dari `PresetSource::all()`; test membuktikan dropdown menampilkan preset palsu yang di-mock lewat container binding, dan bahwa `Settings.php`/`settings.blade.php` tidak memuat literal `'bengkel'`/`'klinik'`/`'salon'` (grep otomatis di test).
- **Sub-bagian Istilah:** form 10 pasangan kunci kamus (`INDUSTRY_PRESETS.md` §3). `updateTerminology()` menulis singular+plural sekaligus ke `settings.json['terminology']` lewat `CompanySettingsStore::update()`; guard identik `selectTheme()` (revalidasi company aktif dari context, revalidasi `company_role === owner` dari session, 403/404 fail-closed, tanpa tulisan diam-diam saat ditolak). Karena `TerminologyResolver`/`FeatureResolver` sudah cache-per-request via `CompanyPresetResolver::flushCache()` yang dipanggil `CompanySettingsStore::update()`, label baru langsung terlihat di render Livewire berikutnya tanpa reload penuh.
- **Sub-bagian Alur:** read-only, menampilkan stage + transisi (`from -> to`, roles, badge "Wajib catatan"/"Wajib persetujuan", badge "Tahap akhir") dari `workflows` preset aktif (`PresetSource::find()`), tanpa menyentuh `WorkflowEngine.php`.
- **Tab Profil:** ringkasan baca-saja (nama, preset, status PPN) dari `BusinessIdentityStore` — dibungkus try/catch agar identitas usaha yang belum lengkap tidak merusak seluruh halaman Pengaturan (tab lain tetap harus bisa dirender untuk fixture demo yang datanya minim).
- **`Onboarding.php` (baru):** form nama usaha (wajib, ditolak bila kosong/whitespace) + dropdown preset dari `PresetSource`. Submit sukses menulis `business_identity.json` (`id`, `name`, `preset`, `tax_mode: "non_taxable"` — default aman D-44, **bukan** `taxable`) dan `settings.json` awal valid lewat `CompanySettingsStore`, di bawah slug unik (`Str::slug` + increment `-2`, `-3`, ... bila folder sudah ada). Preset tak dikenal ditolak tanpa menulis apa pun.
- **Batas D-41 didokumentasikan (bukan bug):** folder company baru dari onboarding **tidak** otomatis reachable lewat `?company=` karena `JsonCompanyContext` fail-closed di luar tiga company demo allowlist `config/datasource.php` — allowlist ini **tidak diubah**. Dicatat di komentar kelas `Onboarding.php` dan dibuktikan test negatif (`/app/dashboard?company={slug-baru}` → 404).
- **Regresi ditemukan & diperbaiki sebelum lolos:** `SettingsTabRegistry::visibleTo()` menerima `session('company_role')` mentah; test lama (`ModuleSidebarTest`) memanggil rute Settings tanpa `company_role` di session, sehingga role `null` kehilangan **semua** tab (termasuk Tema) — `settings deep links...` gagal karena `id="tab-features"` hilang total. Diperbaiki dengan menormalkan role ke `staff` di `Settings::mount()` sebelum memanggil registry, bukan mengubah kontrak registry.
- **Files:** `app/Livewire/Settings.php`, `app/Services/SettingsTabRegistry.php` (dipakai, tidak diubah), `resources/views/livewire/settings.blade.php`, `app/Livewire/Onboarding.php`, `resources/views/livewire/onboarding.blade.php`, `routes/web.php`, `tests/Feature/{SettingsCapabilityTabsTest,OnboardingTest}.php`.
- **Evidence:** focused `SettingsCapabilityTabsTest` 10/10 (35 assertions), `OnboardingTest` 6/6 (18 assertions), `SettingsThemeTest` + `ModuleSidebarTest` (regresi) tetap hijau; full `php artisan test` **282/282 (1199 assertions)**; `vendor/bin/pint --test` passed (96 files); `npm run build` passed (Vite 1.22 s); `git diff --check` clean; scan D-31 pada file yang disentuh task ini → 0 nama industri; tidak ada migration/model Eloquent baru; tidak ada `href="#"` baru.
- **Remaining risk:** (a) tab Karyawan AI, Penggunaan & Paket, dan Tim & Akses masih placeholder kontrak (di luar scope T-F12 — bukan bagian acceptance task ini, hanya tab Profil/Fitur Bisnis/Istilah/Alur yang diisi); (b) tab Profil hanya baca-saja (ubah nama/alamat/mode pajak menyusul task terpisah); (c) onboarding belum terhubung ke auth/`users.current_company_id` (Fase 3) sehingga company baru murni catatan data sampai auth tersedia — sesuai D-41 di atas; (d) wawancara AI via WA (D-40) tetap menyusul setelah node API Hermes (T-17b).

## Detail Task Selesai (T-F11)

### T-F11 — DONE (2026-09-17)

- **`TaxRateService`:** implementasi persis kontrak `REQUIREMENTS.md` §1.3, termasuk normalisasi tarif (`11` dan `0.11` diperlakukan sama sehingga salah input persen tidak menggandakan pajak sebelas kali) dan pengambilan PPN inklusif sebagai **selisih** agar total yang dibayar pelanggan tidak bergeser karena pembulatan. Nilai uang/tarif negatif atau non-finite ditolak.
- **Profil pajak dari data (D-03):** `BusinessIdentityStore::taxProfile()` membaca `business_identity.json`. `tax_mode` yang **tidak ada** berarti non-PKP (default aman, mayoritas klien), tetapi nilai yang **ada tapi tidak dikenal ditolak keras** — menganggapnya non-PKP diam-diam berarti berhenti memungut PPN pada usaha yang sebenarnya PKP.
- **`CashierScreen`:** katalog dari `items`, keranjang tambah/ubah jumlah/hapus, total lewat `TaxRateService`, checkout menulis `orders` + `order_lines` lewat repository, metode pembayaran mock, dan tahap awal diambil dari alur kerja preset bila entitas itu punya alur (transaksi kasir langsung tampil di papan tahap).
- **D-44 ditegakkan:** `showsTax` dari profil adalah satu-satunya penentu apakah baris DPP/PPN dirender. Test menghitung `preg_match_all('/\b(?:DPP|PPN)\b/')` pada DOM company non-PKP dan mensyaratkan hasilnya **0** — bukan tampil `Rp 0`.
- **D-45 bertingkat lewat komponen bersama:** `<x-confirm-dialog>` menerima `level` (`type`/`simple`). Tingkat per aksi dideklarasikan di `CashierScreen::CONFIRM` (`checkout` => `type`, `clearCart` => `simple`), bukan per layar. Frasa dinormalkan `mb_strtoupper(trim())`: `"  ya  "` diterima, `"y"` ditolak; input memakai `autocapitalize=characters autocorrect=off`; tombol aksi `disabled` saat dialog muncul lalu aktif setelah 400 ms; teks dialog memuat nominal konkret.
- **`LedgerScreen`:** kolom nilai, kolom tanggal, dan arah masuk/keluar diturunkan dari schema (arah dikenali dari enum `["in","out"]`), sehingga buku kas dan buku tagihan memakai satu layar. Saldo berjalan dihitung menaik lalu ditampilkan terbaru lebih dulu; saldo negatif dilaporkan apa adanya.
- **Files:** `app/Services/{TaxRateService,TaxCalculationResult,TaxProfile,BusinessIdentityStore}.php`, `app/Livewire/Screens/{CashierScreen,LedgerScreen}.php`, `resources/views/livewire/screens/{cashier,ledger}.blade.php`, `resources/views/components/confirm-dialog.blade.php`, `app/Services/Schema/SchemaValidator.php`, `storage/app/json/*/business_identity.json`, `storage/app/json/salon-ayu/orders.json`, `tests/Unit/{TaxRateServiceTest,SchemaValidatorTest}.php`, `tests/Feature/{CashierScreenTest,LedgerScreenTest,ModuleSidebarTest}.php`.
- **Evidence:** focused `TaxRateServiceTest` 8/8 (31 assertions), `CashierScreenTest` 11/11, `LedgerScreenTest` 7/7; full `php artisan test` **254/254 (1109 assertions)**; `php vendor/bin/pint --test` passed (92 files); `npm run build` passed (Vite 949 ms); `git diff --check` clean.
- **Defect lama yang terpapar dan diperbaiki:** `SchemaValidator::fitsDecimal()` memakai `sprintf('%.14F')` sehingga galat representasi biner **menolak nilai dua desimal yang sah**. Terlihat begitu pemecahan PPN inklusif masuk: `684684.68` ditolak sebagai "Presisi field tidak valid", dan fixture salon yang sudah di-commit pun ikut gagal validasi. Artinya **tenant PKP mana pun tidak akan bisa menyimpan transaksi** sebelum ini diperbaiki. Kini dibandingkan lewat `round($value, $scale) === $value` + `number_format`; kasus negatif `1.234` pada scale 2 tetap ditolak, dan ada test positif untuk lima nilai hasil pembulatan.
- **Demo:** salon dijadikan PKP harga-inklusif (`tax_mode=taxable`, `price_includes_tax=true`, 11%) dan lima ordernya dihitung ulang, supaya kedua mode pajak terlihat saat review; bengkel dan klinik tetap non-PKP sesuai mayoritas klien.
- **Remaining risk:** (a) pembayaran masih state mock — belum ada gateway, sesuai batas Fase 2; (b) checkout belum membuat baris `cash_entries`, jadi buku kas belum otomatis bertambah dari kasir — perlu keputusan apakah itu otomatis atau posting manual; (c) diskon per baris dan per dokumen belum ada di layar (skema sudah menyiapkan kolomnya); (d) `<x-confirm-dialog>` belum dipakai `ListScreen` yang masih punya dialog sendiri — penyatuan ditunda agar diff T-F11 tetap fokus.

### T-F11R — DONE (2026-09-17, QA finansial)

- **Server-authoritative checkout:** state Livewire hanya membawa item id + qty; nama, harga, dan status aktif diambil ulang dari repository, lalu dijaga ulang di dalam lock aggregate. Metode bayar memakai allowlist.
- **Aggregate durable/idempoten:** `EntityRepository::saveAggregate()` menyimpan parent+children di bawah company transaction lock + entity locks, unique constraints, UUID `external_ref`, dan journal `prepared/committed` dengan rollback/roll-forward recovery. Journal hanya boleh menunjuk tepat dua schema file dalam folder company aktif; path lain ditolak.
- **Fiscal fail-closed:** `BusinessIdentityStore` memverifikasi company aktif; file, id, atau `tax_mode` hilang/invalid menolak transaksi. Tidak ada fallback identity id atau mode pajak diam-diam.
- **Ledger semantics:** schema `invoices` saat ini tetap invoice SaaS. Route kanonik invoice/progress billing dipertahankan sebagai fallback kontrak read-only; tidak dihitung sebagai saldo operasional dan tidak mendapat CRUD generik.
- **Money/a11y:** subtotal berasal dari jumlah line yang sudah dibulatkan; nilai di atas batas aman aritmetika float ditolak; decimal validator menjaga kapasitas whole digits; dialog semua tingkat memakai aksi merah, `x-trap.inert.noscroll`, Escape/cancel/confirm mengembalikan fokus.
- **Evidence:** focused 112/112 (686 assertions); full `php artisan test` **266/266 (1146 assertions)**; `vendor/bin/pint --test` passed; `npm run build` passed (Vite 1.38 s); `git diff --check` clean.
- **Review:** tiga putaran read-only menutup seluruh blocker: client-price tampering, write parsial, duplicate/replay, tenant identity, invoice semantics, precision, journal path injection, recovery self-lock, item-active TOCTOU, dan focus restoration.

## Detail Task Selesai (T-F10)

### T-F10 — DONE (2026-09-17)

- **Prep serial lebih dulu:** dispatcher pola layar diubah jadi konvensi (`screen` -> `App\Livewire\Screens\{Studly}Screen` -> `screens.{screen}-screen`) sesuai syarat PG-1 di `EXECUTION_PLAN.md` §Matriks Grup Paralel. Menambah pola layar sekarang = menambah satu kelas komponen, tanpa menyentuh Blade dispatcher. Commit terpisah `refactor(screens): dispatch screen patterns by convention`.
- **`PipelineScreen`:** kolom, transisi yang ditawarkan, kewajiban catatan, dan penahanan approval seluruhnya dari `WorkflowEngine`. Tombol hanya menampilkan transisi sah dari tahap saat ini (`availableTransitions`), sehingga "lompat tahap" adalah data. Penolakan menjadi toast `role="alert"` tanpa perubahan data; transisi ber-approval ditahan dan stage **tidak** disimpan; transisi mundur membuka dialog alasan (D-46) dan alasannya tercatat di `workflow_log`. Baris bertahap di luar alur dilaporkan, bukan disembunyikan.
- **`CalendarScreen`:** tampilan harian/mingguan dengan navigasi periode, slot diurutkan per hari, dan nama sumber daya diresolusi generik dari `references` schema (bukan id mentah).
- **Anti-double-booking sebagai data (bukan kode per entitas):** kunci `no_overlap {scope, start, end}` ditambahkan ke `bookings.schema.json`, divalidasi `EntitySchema`, dan ditegakkan `JsonEntityRepository::save()` **di dalam lock**. Batas bersentuhan (`end` == `start` berikutnya) tetap sah agar slot berurutan bisa dibuat; scope berbeda dan penyimpanan ulang baris yang sama tidak dianggap bentrok. Fase 3 dapat memasangnya sebagai constraint database.
- **Registry `requires_workflow`:** layar berbasis tahap hanya muncul bila preset company mendeklarasikan alur untuk entitas itu. Hasil nyata: bengkel dapat papan work order (`/app/pos/pipeline`), klinik dan salon dapat papan janji/jadwal (`/app/bookings/pipeline`); yang tidak punya alur kehilangan menu **dan** route (zero-bloat U-04), bukan error saat dibuka.
- **Preset klinik dapat alur `deals`** dengan kode netral D-38 (`new/qualified/proposal/negotiation/won/lost`) supaya pipeline kunjungan yang sudah dijanjikan widget dashboard punya layar; lolos integritas D-46 (reachability, tanpa dead end, mundur wajib beralasan).
- **Files:** `app/Livewire/Screens/{PipelineScreen,CalendarScreen}.php`, `resources/views/livewire/screens/{pipeline,calendar}.blade.php`, `app/Services/DynamicMenuRegistry.php`, `app/Services/Schema/{EntitySchema,SchemaPresenter}.php`, `app/Services/Json/JsonEntityRepository.php`, `database/schemas/bookings.schema.json`, `database/presets/klinik.json`, `tests/Feature/{PipelineScreenTest,CalendarScreenTest,JsonDataSourceTest}.php`.
- **Evidence:** focused `PipelineScreenTest` 12/12 (45 assertions), `CalendarScreenTest` 8/8 (24 assertions); full `php artisan test` **227/227 (999 assertions)**; `php vendor/bin/pint --test` passed (83 files); `npm run build` passed (Vite 921 ms); `git diff --check` clean; scan D-31 pada sumber kedua layar → 0 nama industri, 0 kode tahap literal, 0 `DB::`.
- **Defect yang saya buat sendiri lalu perbaiki:** implementasi pertama `CalendarScreen` menghitung "hari ini" dari `now()` (server UTC) padahal data memakai offset usaha — mengulang kelas bug yang sama dengan T-F9b. Sekarang zona waktu dibaca dari `settings.timezone` dengan fallback `app.timezone`, dan dikunci test yang membekukan waktu pada 06:00 WIB (masih tanggal sebelumnya di UTC).
- **Remaining risk:** (a) **zona waktu per company belum menjadi keputusan** — `settings.timezone` sekadar opsional dengan default `app.timezone` yang saat ini `UTC`, sehingga highlight "hari ini" pada demo masih memakai UTC sampai Bos memutuskan sumber resmi zona waktu (kandidat keputusan baru, bukan ditebak di sini); (b) perpindahan tahap memakai tombol per transisi, bukan tarik-lepas — dipilih supaya dapat diakses keyboard, tarik-lepas dapat ditambahkan di atasnya tanpa mengubah kontrak; (c) papan membaca seluruh baris entitas (belum berhalaman), cukup untuk data demo tetapi perlu batas sebelum data nyata besar; (d) test yang memanggil `CompanySettingsStore::update` **wajib** `Storage::fake('company-json')` — tanpa itu ia menulis `settings.json` nyata yang gitignored sehingga kerusakannya tidak terlihat di `git status` (terjadi dalam sesi ini dan sudah dibersihkan).

## Detail Task Selesai (T-F9b)

### T-F9b — DONE (2026-09-17, permintaan Bos di luar rencana awal)

- **Latar:** risiko (a) pada T-F9 — seluruh entitas masih berisi stub `{"id":1,"name":"Name"}` dari T-F3 sehingga layar tampak kosong menjelang gate `HUMAN:UI-LOCK`.
- **Implemented:** 48 fixture pada tiga company demo diisi data operasional yang koheren dan saling terhubung: `bengkel-arka` (8 pelanggan, 5 mekanik, 8 sparepart, 7 work order + 12 baris, 3 pekerjaan + 4 termin, 10 transaksi kas), `klinik-sehat` (10 pasien, 4 staf, 8 janji temu, 6 kunjungan lintas tahap, 8 transaksi kas), `salon-ayu` (9 pelanggan, 5 terapis, 7 produk, 8 jadwal, 5 order POS + 9 baris, 9 transaksi kas). Nilai `stage` hanya memakai kode yang dideklarasikan workflow preset; pajak nol konsisten dengan mayoritas non-PKP (D-44).
- **Dua defect T-F7 yang baru terlihat setelah data nyata masuk, diperbaiki dengan RED lebih dulu:**
  - `WidgetRegistry::formatDateTime()` memakai `date()` sehingga dirender pada timezone server (`app.timezone=UTC`); booking `08:00+07:00` tampil **01:00**. Kini dirender pada offset yang tercatat di data. RED: `Expected: 17 Sep 2026 · 01:00 / To contain: 08:00`.
  - Kartu `upcoming_schedule` berjudul "Agenda mendatang" tetapi menghitung dan menampilkan agenda yang sudah lewat (item teratas klinik/salon adalah booking kemarin, termasuk yang dibatalkan). Kini difilter `starts_at >= now` dan meta menjadi "… mendatang".
- **Guard baru (permanen):** `test_committed_demo_fixtures_have_enough_rows_for_review` (ambang minimum per entitas) dan `test_committed_demo_fixtures_keep_referential_integrity` (setiap FK non-null wajib menunjuk baris yang ada; entitas Fase 3 seperti `users`/`business_identities` dikecualikan eksplisit).
- **Files:** `storage/app/json/{bengkel-arka,klinik-sehat,salon-ayu}/*.json` (48), `app/Services/Dashboard/WidgetRegistry.php`, `tests/Feature/JsonDataSourceTest.php`, `tests/Feature/DashboardTest.php`, `docs/EXECUTION_PLAN.md`, `docs/AUTOPILOT_STATUS.md`.
- **Evidence:** `JsonDataSourceTest` 17/17 (302 assertions); `DashboardTest` 4/4 (34 assertions); full `php artisan test` **205/205 (913 assertions)**; `php vendor/bin/pint --test` passed (79 files); `npm run build` passed (Vite 957 ms); `git diff --check` clean. Widget terverifikasi menghasilkan angka nyata per company (mis. bengkel arus kas bersih Rp 2.520.000, 2 sparepart di bawah batas, 3 dokumen menunggu; klinik 10 pasien, pipeline 6 kunjungan; salon 2 produk di bawah batas).
- **Remaining risk:** tanggal fixture **dipaku** di sekitar 15-19 September 2026, jadi kartu "mendatang" akan menyusut menjadi kosong bila aplikasi ditinjau jauh setelah tanggal itu — perlu keputusan Bos apakah data demo dibuat relatif terhadap hari ini pada task terpisah. Skrip generator sekali-pakai sengaja tidak disimpan; regenerasi berarti menulis ulang fixture.

## Detail Task Selesai (T-F9)

### T-F9 — DONE (2026-09-17)

- **Implemented:** pola layar daftar generik `ListScreen` + komponen `<x-data-table>` (tabel padat desktop / kartu ponsel) dan `<x-form-field>`; kolom, field form, tipe input, batas panjang, dan nilai awal seluruhnya diturunkan `SchemaPresenter` dari `{entity}.schema.json`, sehingga entitas baru mendapat layar tanpa perubahan kode.
- **Data layer:** `EntityRepository::delete()` ditambahkan ke kontrak dan implementasi JSON dengan lock + atomic replacement; `save()` tanpa `id` menetapkan id berikutnya **di dalam lock** agar pembuatan row tidak balapan; `query()` menerima `_search` yang dicocokkan sebagai substring pada properti string yang diturunkan dari schema (Fase 3 dapat menerjemahkannya ke `LIKE`).
- **Terminologi:** `routeDefinition()` kini juga mengembalikan istilah murni (`term`) sehingga judul, tombol tambah, label pencarian, dan empty-state disusun layar sendiri lewat `TerminologyResolver` — bukan literal. Terbukti untuk tiga preset (`Pelanggan`/`Pasien`/`Pelanggan`) dan `employees` salon (`Terapis`).
- **Fail-closed:** entitas tidak pernah berasal dari input — diturunkan ulang dari `DynamicMenuRegistry` pada setiap render; `module`/`submodule`/`company` locked; kapabilitas yang dicabut menutup layar 403; **company dipaku saat mount** sehingga komponen basi tidak dapat mengedit/menghapus row company lain dengan id yang sama (RED terbukti: sebelum guard, aksi mengembalikan 200).
- **D-45 tingkat 2:** konfirmasi hapus memakai dua tombol (bukan ketik `YA`, karena belum berdampak fiskal), `role="dialog"`/`aria-modal`/`aria-labelledby`/`aria-describedby`, tombol merah `disabled` saat dialog muncul lalu aktif setelah 400 ms, dan teks menyebut baris yang akan hilang.
- **Files:** `app/Contracts/EntityRepository.php`, `app/Services/Json/JsonEntityRepository.php`, `app/Services/Schema/SchemaPresenter.php`, `app/Services/DynamicMenuRegistry.php`, `app/Livewire/Screens/ListScreen.php`, `app/Livewire/DummyModule.php`, `resources/views/livewire/screens/list.blade.php`, `resources/views/components/{data-table,form-field}.blade.php`, `resources/views/livewire/dummy-module.blade.php`, `tests/Feature/{ListScreenTest,JsonDataSourceTest}.php`.
- **Evidence:** focused `ListScreenTest` 11/11 (70 assertions); `JsonDataSourceTest` 15/15 (90 assertions); full `php artisan test` **202/202 (697 assertions)**; `php vendor/bin/pint --test` passed (79 files); `npm run build` passed (Vite 1.07 s); `git diff --check` clean; scan D-31 pada sumber layar → 0 nama industri, 0 istilah kamus literal, 0 `DB::`.
- **Remaining risk:** (a) fixture demo masih 1 baris per entitas dari T-F3, sehingga layar nyata terlihat kosong saat review UI-LOCK — pengayaan data demo belum dijadwalkan di baris task mana pun; (b) label field memakai humanisasi nama kolom (`Wa Number`, `Base Salary`) karena schema belum punya kunci `label` — `SchemaPresenter` sudah membacanya bila ada, jadi perbaikan cukup menambah data; (c) fokus belum dipindahkan ke form saat mode ubah dibuka; (d) `DynamicMenuRegistry::accentFor()` masih dead code dari catatan T-F8.

## Detail Task Selesai (T-F8)

### T-F8 — DONE (2026-09-17)

- **Implemented:** `DynamicMenuRegistry` memakai capability, terminology, `CompanyContext`, dan `PresetSource`; komposisi/urutan modul berasal dari preset, sementara katalog menutup seluruh 28 path kanonik §9 tanpa kondisi nama industri.
- **Authorization:** unknown module/path 404; capability nonaktif 403; state route `DummyModule` dan `Sidebar` dikunci; metadata layar diturunkan ulang dan capability diperiksa lagi pada setiap render Livewire.
- **Shell/navigation:** drawer responsif dengan inert/focus trap/scroll lock/focus restore, active navigation tunggal, global Settings/lobby links, Command Palette native dialog yang capability-aware dan tetap terbuka saat morph (`wire:ignore.self`), touch trigger, tema company-scoped, dan `lang="id"`.
- **Negative tests:** salon tanpa projects tidak menerbitkan route; nested route nonaktif 403; tampering locked property ditolak; capability yang dicabut setelah mount menghasilkan 403; invalid company/preset fail-closed.
- **Evidence:** focused 27/27 (167 assertions); full `php artisan test` 188/188 (617 assertions); `php vendor/bin/pint --test` passed; `npm run build` passed (Vite 868 ms); `git diff --check` clean; perbandingan otomatis §9 menemukan 28/28 path, 0 missing.
- **D-31/D-42:** scan source tidak menemukan nama industri di registry/UI atau `DB::`; dua nama domain yang tersisa hanya capability key canonical Tier B dari keputusan.
- **Remaining risk:** verifikasi interaksi browser nyata dan audit aksesibilitas menyeluruh tetap dijadwalkan pada T-F14/T-F15; dependency browser automation tidak ditambahkan.
- **Audit lanjutan (2026-09-17):** item `pos.tables` masih menanam istilah kamus `orders` sebagai literal (`Meja & Pesanan`) sehingga override terminologi company tidak diikuti (bengkel memetakan `orders` ke `Work Order`). Diperbaiki menjadi `['term' => 'orders', 'prefix' => 'Meja & ']` dan dikunci acceptance test `test_menu_labels_resolve_dictionary_terms_instead_of_hardcoded_defaults`. RED terbukti lebih dulu: `Failed asserting that an array contains 'Meja & Work Order'`.
- **Files audit:** `app/Services/DynamicMenuRegistry.php`, `tests/Feature/ModuleSidebarTest.php`.
- **Evidence audit:** focused 22/22 (135 assertions); full `php artisan test` 189/189 (619 assertions); `php vendor/bin/pint --test` passed (76 files); `npm run build` passed (Vite 904 ms); `git diff --check` clean.
- **Dicatat, sengaja tidak diubah:** `DynamicMenuRegistry::accentFor()` menjadi dead code setelah T-F8 (0 pemanggil di `app/`, `resources/`, `tests/`) dan parameter `$module` tidak terpakai; dihapus pada task berikutnya yang memang menyentuh registry agar diff T-F8 tidak melebar.

## Detail Task Selesai (T-F7)

### T-F7 — DONE (2026-09-16)

- **Implemented:** `DashboardComposer` generik merakit tiga KPI universal, laporan asisten per-company, dan zona widget dari `preset.dashboard`; `WidgetRegistry` menghitung `upcoming_schedule`, `low_stock`, `kpi_cashflow`, `pending_approvals`, dan `deals_pipeline` hanya melalui `EntityRepository`/resolver capability.
- **Frontend:** route nyata `/app/dashboard`, Livewire dashboard + widget card, layout responsif berbasis token, loading/empty/recoverable-error + retry, `aria-live`, heading/landmark, dan fokus keyboard.
- **Data:** schema `assistant_report` dan tiga fixture `{company}/assistant_report.json`; inventory demo menjadi 48 file tervalidasi schema tanpa migration, Eloquent, atau `DB::`.
- **D-31/D-42:** scan production dashboard tidak menemukan literal nama industri atau `DB::`; preset memilih widget, terminologi resolver memberi label, dan Blade tidak menyimpan angka bisnis statis.
- **Files:** `app/Services/Dashboard/*`, `app/Livewire/{Dashboard,Widgets/DashboardWidget}.php`, dua Blade dashboard/widget, route, schema + tiga report fixture, dua test fitur, `docs/{EXECUTION_PLAN,AUTOPILOT_STATUS}.md`.
- **Evidence:** RED focused gagal karena composer belum ada; GREEN focused 3/3 (30 assertions); full `php artisan test` 182/182 (617 assertions); `php vendor/bin/pint --test` passed; `npm run build` passed (Vite 962 ms); scan D-31 dashboard 0 temuan.
- **Remaining risk:** registry T-F7 sengaja mengimplementasikan lima widget yang dipakai tiga preset demo; katalog lain tetap tervalidasi tetapi renderer datanya ditambahkan saat preset yang memakainya masuk scope.

## Detail Task Selesai (T-F6, T-F5, T-F4)

### T-F6 — DONE (2026-09-16)

- **Implemented:** `WorkflowEngine` generik dari `preset.workflows`, daftar stage/transisi sah untuk UI, `HasWorkflow` + adapter row JSON, transisi maju/lompat, guard role, alasan wajib untuk rework, approval tertahan, serta efek `notify.owner_wa` fake.
- **Fail-closed:** record lintas company, entity/stage/transisi asing, role salah, log rusak, dan audit tenant mismatch ditolak; perubahan stage in-memory di-rollback bila append log gagal; append memakai lock dan atomic replacement.
- **Files:** `app/Contracts/HasWorkflow.php`, `app/Services/Workflow/{WorkflowEngine,JsonWorkflowLog,ArrayWorkflowRecord}.php`, `app/Services/Workflow/Effects/*.php`, `tests/Feature/WorkflowEngineTest.php`, `docs/{EXECUTION_PLAN,AUTOPILOT_STATUS}.md`.
- **Evidence:** RED focused gagal karena kontrak belum ada; GREEN focused 11/11 (37 assertions); full `php artisan test` 179/179 (584 assertions); `php vendor/bin/pint --test` passed; `git diff --check` clean; D-31 scan production scope 0 literal industri; build N/A (tanpa Blade/CSS/JS).
- **Review:** tiga lane read-only; snapshot TOCTOU, validasi payload audit, API stage untuk T-F10, adapter row, dan coverage wildcard/malformed log diperbaiki. Risiko auth role trusted dan atomic persistence/idempotency tetap scope Fase 3 (`T-00c`/`T-08d`); Fase 2 hanya engine in-memory dengan efek fake.

### T-F5 — DONE (2026-09-16)

- **Implemented:** `FeatureResolver` dan `TerminologyResolver` generik dengan urutan company override → preset tervalidasi → default global; helper `term()` dan directive Blade `@term`; preset company dibaca melalui `CompanyContext`; disk `company-json` menyatukan settings runtime dengan fixture JSON kanonik.
- **Fail-closed:** settings non-object, preset hilang/asing, override bertipe salah, dependensi kapabilitas putus, dan aktivasi Tier B pada preset Tier A ditolak; key istilah asing exception di local/testing dan fallback key di production.
- **Files:** resolver + helper/provider, kontrak/implementasi `CompanyContext`, `CompanySettingsStore`, `ThemeRegistry`, disk config, metadata preset tiga fixture, dua acceptance test baru, regression test tema, serta status/plan.
- **Evidence:** RED focused 0/5; GREEN focused 26/26 (145 assertions); full `php artisan test` 168/168 (547 assertions); `php vendor/bin/pint --test` passed; `git diff --check` clean; build N/A (tanpa Blade/CSS/JS).
- **Review:** tiga lane read-only; temuan storage-root nyata, settings malformed yang fail-open, cache lintas request, dan bypass dependensi kapabilitas diperbaiki serta diuji.
- **Remaining risk:** invalidasi cache otomatis terhubung ke `CompanySettingsStore::update`; writer lain yang mengubah file langsung dalam request yang sama wajib memanggil `flushCache()`.

### T-F4 — DONE (2026-09-16)

- **Implemented:** tiga preset kanonik (`bengkel`, `klinik`, `salon`), validator katalog D-32/D-33 dan integritas workflow D-46, serta `JsonPresetSource` deterministik dari `database/presets`.
- **Fail-closed:** key katalog asing, bentuk JSON salah, direktori hilang, filename/key mismatch, dependensi kapabilitas, stage tak terjangkau/dead-end/tanpa jalur terminal, terminal tak sah, dan transisi mundur tanpa catatan ditolak.
- **Files:** `app/Services/Preset/PresetDefinitionValidator.php`, `app/Services/Json/JsonPresetSource.php`, `database/presets/{bengkel,klinik,salon}.json`, dua acceptance test, `docs/EXECUTION_PLAN.md`, `docs/AUTOPILOT_STATUS.md`.
- **Evidence:** RED 0/15 lalu review RED 15/22; GREEN focused 22/22 (52 assertions); full `php artisan test` 161/161 (515 assertions); `php vendor/bin/pint --test` passed; `git diff --check` clean; build N/A (tanpa Blade/CSS/JS).
- **Review:** tiga lane read-only; seluruh temuan valid ditutup: shape `effects`/`props`, object-vs-list JSON, missing directory, regex stage, dependensi AI→approval, dan siklus non-terminal tanpa jalur ke terminal.
- **Remaining risk:** katalog berupa konstanta validator mengikuti spesifikasi terkunci; penambahan capability baru tetap wajib keputusan D-32.

## Next READY

**Tidak ada task `READY` lagi. Fase 2 selesai (T-07 -> T-F1..T-F15 semua `DONE`).**

Baris berikutnya di `EXECUTION_PLAN.md` (Fase 3a dst.) semuanya `BLOCKED` di
belakang gate `⛔ HUMAN:UI-LOCK` - lihat section Matriks Grup Paralel. Sesuai
Stop Line `HERMES.md`: *"after Fase 2 (T-07 -> T-F1 ... T-F15), stop. Fase 3+
is behind HUMAN:UI-LOCK."* Ini persetujuan visual + alur produk (lihat Bos
melihat aplikasi utuh berjalan untuk `bengkel`, `klinik`, `salon`, `laundry`),
bukan sesuatu yang bisa diputuskan lewat test/lint/build. **Autopilot berhenti
di sini menunggu konfirmasi Bos** bahwa Fase 2 `LOCKED` sebelum Fase 3
(fondasi tenant database nyata, migration, adapter Eloquent) boleh dimulai.

## Perubahan D-31 (2026-09-16, setelah review ke-3)

Pertanyaan Bos: "memungkinkan 50 jenis bisnis dengan UI/UX & alur sesuai?"
Jawaban: **bisa, tetapi desain lama akan mengunci pola 1 industri = N tabel + N flag
+ N komponen.** Dokumen diubah agar tujuan itu tercapai:

- `00-DECISIONS.md`: +D-31 (prinsip), D-32 (katalog kapabilitas v1 dikunci), D-33 (Tier A/B); D-01/D-02 diperluas.
- `INDUSTRY_PRESETS.md`: **ditulis ulang** — katalog ~20 kapabilitas generik (ganti flag ber-nama industri), skema `definition` JSON, kamus terminologi, katalog widget & efek workflow, 6 preset awal sebagai data, **§7 peta pasar 63 bisnis potensial (9 sektor) dipetakan ke kapabilitas — 95% Tier A tanpa kode, +§7.11 prioritas GTM**, registry menu per kapabilitas.
- `DATA_MODEL.md`: `business_presets.definition` JSON + `tier`; `companies.business_preset` VARCHAR (bukan ENUM); +`workflow_definitions`, `workflow_transitions_log`, konvensi `attributes JSON`; **tabel per industri (`crm_*`, `rental_*`, `eo_*`, `pharmacy_*`, `contractor_*`, `hr_*`) diganti tabel kapabilitas generik** (`contacts`, `deals`, `projects`, `project_milestones`, `resources`, `bookings`, `items`, `item_batches`, `orders`, `employees`…) + 2 modul Tier B (`prescriptions`, `retentions`); `stage` VARCHAR bukan ENUM.
- `EXECUTION_PLAN.md`: +**Fase 3b Mesin Komposisi**; resolver/engine/composer inti dipindahkan ke T-F5/T-F6/T-F7, sedangkan T-08b-e mempertahankan adapter Eloquent, materialisasi, persistence, dan regression parity pada Fase 3; T-15 digabung ke T-08b; +T-13b..f, T-17b (MCP tenant bot), T-19b (NalarPesan webhook), **T-21c bukti D-31**; T-05/T-06/T-07 dilarang hardcode industri; +**Fase 6 Ekspansi Pasar** (T-24..T-24d preset gelombang 1-4 dari §7.11, T-25 keputusan Tier B manufaktur/angsuran, T-26 halaman publik `/industri`).
- `REQUIREMENTS.md` §2: prinsip 4 resolver, §2.2 WorkflowEngine, §2.3 `term()`.
- `UX_UI_SPEC.md` §5: kartu per industri → widget per kapabilitas; §4.3 preset dinamis + sub-tab Istilah/Alur; empty-state via `term()`.
- `PRD.md` Pilar 3 → Composable Capability.
- `HERMES.md`: guard D-31 untuk autopilot.

## Perbaikan Review ke-3 (2026-09-16)

Dari 3 delegate paralel (kontradiksi antar-dokumen, drift dok-vs-kode,
kesiapan autopilot) ditemukan 34 + 30 + 20 temuan. Yang diperbaiki:

**Kode**
- `AGENTS.md`/`CLAUDE.md`: hapus perintah install Laravel Boost → pointer ke `HERMES.md`.
- Sidebar: hapus `<p>` placeholder untuk modul tak dikenal (zero-bloat nyata); test diperketat.
- Lobby: tombol search diberi handler `Ctrl+K` + `aria-label` + focus ring.
- Layout modul: `<main id="main-content">`.
- Pint: 3 file pre-existing diperbaiki; repo bersih.

**Dokumen**
- `00-DECISIONS.md`: +D-24..D-30, +Q-01..Q-08 dengan default, path folder & klaim "sudah live" dikoreksi.
- `EXECUTION_PLAN.md`: ditulis ulang — definisi `READY`/`BLOCKED`/`DONE`, gate, command per tipe, kebijakan gagal, **Fase 3a** (T-00a/b/c), T-03b, T-14b, T-21b, dependency graph, kolom Depends On/Decisions/Gate per task.
- `DATA_MODEL.md`: §1 → `CREATE` (bukan `ALTER` tabel yang tidak ada), `module_settings` sesuai D-19, `+company_id` di `journal_lines`, `-image_path`, `invoices` +`type`/`period_*`, penomoran 0..14 rapi, ledger pindah ke §11.3.
- `INDUSTRY_PRESETS.md`: +flag `finance.*`/`hr.*`, preset `custom` didefinisikan, §2 ditulis ulang ke `/app/{module}` flag-aware, bug `ops.rental_checkin` diperbaiki.
- `UX_UI_SPEC.md`: +§7 **36 token** + 11 pasangan kontras + nilai default; path/kelas yang tidak ada dikoreksi; `/settings`→`/app/settings`, `/dashboard`→`/app/dashboard`.
- `REQUIREMENTS.md`: `module_settings` shape, stage CRM, PIC, `YA <kode>`, NalarPesan.
- `COMMERCIAL_AND_AI_AGENTIC_SPEC.md`: nama tabel, slug 64, model tidak dibatasi paket, `YA <kode>`, 6 lapis pengaman dinomori ulang, Q-04.
- `PRD.md`: stack line, `FinancialBook` dihapus, prioritas dokumen, daftar 9 dokumen.
- `PRD_RECONCILIATION.md`: G-06/G-07 → RESOLVED, +G-08..G-11, kutipan historis ditandai.

## Temuan Terbuka (dicatat, diselesaikan di task yang tepat)

| Temuan | Task | Status |
|---|---|---|
| `README.md` stock Laravel + Boost; `composer.json name: laravel/laravel` | T-22 | Belum |
| `welcome.blade.php` tidak dipakai; dua layout tanpa skip-link | T-05 | Belum |

QA review 2026-09-17 menutup 3 temuan lama (kode dicek langsung, bukan asumsi):
- ~~`CommandPalette` dummy `href="#"` ×5~~ — **SELESAI** (T-F8): grep `href="#"` di seluruh `resources/` = 0 hit, `CommandPalette` memakai URL nyata dari `DynamicMenuRegistry::menusFor()`.
- ~~`DummyModule` `href="#"` di tabel~~ — **SELESAI** (T-F8): route sudah nyata, re-validasi capability/path setiap render.
- ~~`/app/{unknown}` → 200 (harus 404)~~ — **SELESAI** (T-F8): `EnsureFeatureEnabled` → `abort_unless(hasModule, 404)`.
- ~~Registry statis tanpa `Company`/flag~~ — **SELESAI** (T-F8): `DynamicMenuRegistry` sudah preset/company-driven via `FeatureResolver`.

## Correction Log

- 2026-09-16 (1): Larangan Livewire dibatalkan setelah kode diperiksa.
- 2026-09-16 (2): Klaim "T-03 zero-bloat" sebelumnya **salah** — view masih merender placeholder. Diperbaiki + test diperketat.
- 2026-09-16 (3): Klaim "T-02 a11y terpenuhi" sebelumnya **salah** — tombol search inert. Diperbaiki.
- 2026-09-16 (4): Fase 3 sebelumnya tidak punya task pembuat `companies`/`module_settings`. Ditambah Fase 3a.
