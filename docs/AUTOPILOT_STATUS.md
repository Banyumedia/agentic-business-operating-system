# Agentic BOS Autopilot Status

**Updated:** 2026-09-17 (Fase 2 berjalan - T-F9 + T-F9b selesai, T-F10 berikutnya)
**Mode:** FASE 2 BERJALAN - T-07 + T-F1..T-F9 DONE; next READY T-F10
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
**Paralel write:** ✅ diizinkan via worker worktrees (lihat tabel di bawah). Merge ke main tetap serial.

## Worker Registry (Multi-Worktree Paralel)

| Worker | Path | Branch | Assigned Cluster | DB |
|---|---|---|---|---|
| **main** | `D:\PROJECTS\agentic-bos` | `main` | T-07, T-F1, T-F2, T-F5, T-F9, T-F14, T-F15 | `database\database.sqlite` |
| **worker-a** | `D:\PROJECTS\agentic-bos-worker-a` | `worker-a` | T-F4 | `database\database.sqlite` (copy) |
| **worker-b** | `D:\PROJECTS\agentic-bos-worker-b` | `worker-b` | T-F6, T-F7, atau T-F8 | `database\database.sqlite` (copy) |
| **worker-c** | `D:\PROJECTS\agentic-bos-worker-c` | `worker-c` | T-F10, T-F11, T-F12, atau T-F13 | `database\database.sqlite` (copy) |

`vendor/` dan `node_modules/` = junction ke `main`. Jangan `composer install/update` dari worker.

**Merge workflow setelah worker selesai:**
```bash
cd D:\PROJECTS\agentic-bos
git merge --no-ff worker-x -m "feat(scope): deskripsi"
```
Merge tetap serial — satu per satu. Cek `docs/KIRO_SKILL.md` §Worker Registry untuk detail lengkap.

## Verified Baseline (sumber tunggal)

| Item | Value | Cara verifikasi ulang |
|---|---|---|
| Laravel | 13.32.0 | `php artisan --version` |
| PHP | 8.3.30 | `php -v` |
| Livewire | 4.4 | `composer.json` |
| Tailwind | 4.3 (CSS-first `@theme`) | `package.json` |
| Test suite | **205 passed, 913 assertions** | `php artisan test` |
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

**Tidak ada task berjalan.** Writer berikutnya mengambil T-F10 (lihat Next READY).

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

**T-F10 — `PipelineScreen` (kanban dari `WorkflowEngine`) + `CalendarScreen`.**

T-F9 membuka T-F10, T-F11, T-F12, dan T-F13 sekaligus (semuanya `READY` di
`EXECUTION_PLAN.md`); urutan yang dianjurkan tetap T-F10 lebih dulu karena
menutup pola layar yang dipakai T-F11/T-F12.

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
