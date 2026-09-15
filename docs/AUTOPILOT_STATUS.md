# Agentic BOS Autopilot Status

**Updated:** 2026-09-16 (D-31 Composable Capability Architecture)  
**Mode:** FASE 1 DONE — FASE 2 READY (T-07 first)  
**Arsitektur target:** puluhan jenis bisnis — industri = data, kapabilitas = kode (D-31..D-33)  
**Canonical workspace:** `D:\PROJECTS\agentic-bos`  
**Git:** branch `main`, HEAD lihat `git rev-parse --short HEAD`; **remote belum dikonfigurasi**.

> Agent yang resume: baca file ini, lalu `EXECUTION_PLAN.md` §0 untuk definisi
> `READY` dan command verifikasi. Jangan pakai angka/SHA dari ingatan sesi.

## Verified Baseline (sumber tunggal)

| Item | Value | Cara verifikasi ulang |
|---|---|---|
| Laravel | 13.32.0 | `php artisan --version` |
| PHP | 8.3.30 | `php -v` |
| Livewire | 4.4 | `composer.json` |
| Tailwind | 4.3 (CSS-first `@theme`) | `package.json` |
| Test suite | **17 passed, 135 assertions** | `php artisan test` |
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

Lihat `00-DECISIONS.md` §OPEN (Q-01..Q-08). Setiap item punya **rekomendasi
default**. Task yang bergantung padanya berstatus `BLOCKED` sampai Bos menyetujui
default atau memilih alternatif. **Agent tidak menebak.**

## Completion Ledger

| Task | State | Evidence |
|---|---|---|
| G-01..G-04 | DONE | `PRD_RECONCILIATION.md`, `EXECUTION_PLAN.md` rev 2026-09-16 |
| T-01 | DONE | stack terverifikasi |
| T-02 | DONE | `LobbyNavigationTest` (4). Kartu → route; tombol search punya handler + `aria-label` |
| T-03 | DONE (statis) | `DynamicMenuRegistryTest` (7) + `ModuleSidebarTest` (4). Unknown module → **nol DOM** |
| T-04 | DONE (dummy) | Alpine modal + `wire:model.live` |
| T-09 | DONE (merged ke T-00a) | — |

## Arsitektur D-31 (dibaca sebelum menulis kode apa pun setelah UI-LOCK)

- Tidak ada kode/tabel/flag/komponen yang menyebut nama industri.
- Kapabilitas hanya dari katalog `INDUSTRY_PRESETS.md` §1 (D-32).
- Istilah via `term()`, alur via `WorkflowEngine`, dashboard via `DashboardComposer`.
- Fase 3b (mesin komposisi) **wajib selesai** sebelum tabel domain (Fase 3c).
- T-21c membuktikan D-31: tambah `klinik.json` + `salon.json` → produk berjalan **tanpa diff kode**.

## Next READY

**T-07** — token `--erp-*` (36, `UX_UI_SPEC.md` §7), `ThemeRegistry::passesAa()`,
`ThemeContrastTest` (11 pasangan), Settings tab WAI-ARIA di `/app/settings`,
theme toggle. Ini membuka T-05 dan T-06. D-36 (ex-Q-03) locked (turunkan dari
palet Tailwind v4, buktikan AA dengan test).

Setelah T-07 → T-05 → T-06 → laporkan → **berhenti di `HUMAN:UI-LOCK`**.

T-05/T-06 wajib memakai stub `DashboardComposer`/`term()` (bukan hardcode
industri) agar T-08c/T-08e cukup mengganti sumber data, bukan menulis ulang view.

## Perubahan D-31 (2026-09-16, setelah review ke-3)

Pertanyaan Bos: "memungkinkan 50 jenis bisnis dengan UI/UX & alur sesuai?"
Jawaban: **bisa, tetapi desain lama akan mengunci pola 1 industri = N tabel + N flag
+ N komponen.** Dokumen diubah agar tujuan itu tercapai:

- `00-DECISIONS.md`: +D-31 (prinsip), D-32 (katalog kapabilitas v1 dikunci), D-33 (Tier A/B); D-01/D-02 diperluas.
- `INDUSTRY_PRESETS.md`: **ditulis ulang** — katalog ~20 kapabilitas generik (ganti flag ber-nama industri), skema `definition` JSON, kamus terminologi, katalog widget & efek workflow, 6 preset awal sebagai data, **§7 peta pasar 63 bisnis potensial (9 sektor) dipetakan ke kapabilitas — 95% Tier A tanpa kode, +§7.11 prioritas GTM**, registry menu per kapabilitas.
- `DATA_MODEL.md`: `business_presets.definition` JSON + `tier`; `companies.business_preset` VARCHAR (bukan ENUM); +`workflow_definitions`, `workflow_transitions_log`, konvensi `attributes JSON`; **tabel per industri (`crm_*`, `rental_*`, `eo_*`, `pharmacy_*`, `contractor_*`, `hr_*`) diganti tabel kapabilitas generik** (`contacts`, `deals`, `projects`, `project_milestones`, `resources`, `bookings`, `items`, `item_batches`, `orders`, `employees`…) + 2 modul Tier B (`prescriptions`, `retentions`); `stage` VARCHAR bukan ENUM.
- `EXECUTION_PLAN.md`: +**Fase 3b Mesin Komposisi** (T-08 validator+seeder JSON, T-08b `FeatureResolver`, T-08c `TerminologyResolver`, T-08d `WorkflowEngine`, T-08e `DashboardComposer`) **sebelum** Fase 3c tabel kapabilitas; T-15 digabung ke T-08b; +T-13b..f, T-17b (MCP tenant bot), T-19b (NalarPesan webhook), **T-21c bukti D-31**; T-05/T-06/T-07 dilarang hardcode industri; +**Fase 6 Ekspansi Pasar** (T-24..T-24d preset gelombang 1-4 dari §7.11, T-25 keputusan Tier B manufaktur/angsuran, T-26 halaman publik `/industri`).
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

| Temuan | Task |
|---|---|
| `README.md` stock Laravel + Boost; `composer.json name: laravel/laravel` | T-22 |
| `welcome.blade.php` tidak dipakai; dua layout tanpa skip-link | T-05 |
| `CommandPalette` dummy `href="#"` ×5 | T-20 |
| `DummyModule` `href="#"` di tabel | T-06 |
| `/app/{unknown}` → 200 (harus 404) | T-16 |
| Registry statis tanpa `Company`/flag | T-03b |

## Correction Log

- 2026-09-16 (1): Larangan Livewire dibatalkan setelah kode diperiksa.
- 2026-09-16 (2): Klaim "T-03 zero-bloat" sebelumnya **salah** — view masih merender placeholder. Diperbaiki + test diperketat.
- 2026-09-16 (3): Klaim "T-02 a11y terpenuhi" sebelumnya **salah** — tombol search inert. Diperbaiki.
- 2026-09-16 (4): Fase 3 sebelumnya tidak punya task pembuat `companies`/`module_settings`. Ditambah Fase 3a.
