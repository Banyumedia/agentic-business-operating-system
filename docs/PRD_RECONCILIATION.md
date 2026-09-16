# PRD Reconciliation Findings

**Reviewed:** 2026-09-16  
**Scope:** `PRD.md`, `00-DECISIONS.md`, `REQUIREMENTS.md`, `DATA_MODEL.md`,
`INDUSTRY_PRESETS.md`, `UX_UI_SPEC.md`, and `EXECUTION_PLAN.md`.

This document records the pre-execution review. The documented resolutions
below were accepted on 2026-09-16.

## G-01: Canonical Repository Resolution

**Resolved:** the Linux path was obsolete after the development environment
moved to Windows. The canonical repository is `D:\PROJECTS\agentic-bos`, the
workspace root containing the active Laravel application. Before each writer
starts, it must still record the current branch, worktree, remote, and baseline
test state; these are execution evidence, not repository-identity blockers.

## G-02: Tenant Database Strategy Resolution

| Source | Statement |
|---|---|
| `PRD.md` | New external SaaS clients must use one database per tenant through `TenantProvisioner`. |
| `00-DECISIONS.md` D-06 | Describes a multi-tenant ERP database using `company_id` scoping for 480 clients. |
| `COMMERCIAL_AND_AI_AGENTIC_SPEC.md` | Specifies the same shared database for clients 1-480, with dedicated databases only for 1-20 enterprise clients. |

**Resolved:** SaaS reguler memakai database bersama dengan isolasi
`company_id` yang fail-closed. Klien Enterprise dapat memakai database/VPS
dedicated melalui `TenantProvisioner`. Migrations, policies, global scopes,
foreign keys, dan test harus membuktikan tenant A tidak dapat membaca atau
menulis data tenant B.

## G-03: Tax Configuration Scope Resolution

| Source | Statement |
|---|---|
| `00-DECISIONS.md` D-03 | Tax mode is configured per `BusinessIdentity`. |
| `REQUIREMENTS.md` | Tax mode must be dynamic per `BusinessIdentity` and per transaction. |
| `DATA_MODEL.md` | Tax is rigid at company level; a non-tax invoice requires a shadow company. |
| `00-DECISIONS.md` D-17 | Mixed inclusive/exclusive, per-item, per-transaction, or multi-rate tax is an Enterprise add-on. |

**Resolved:** simpan konfigurasi dasar pajak pada `BusinessIdentity`; seluruh
transaksi fase dasar mengikuti mode tersebut. Override per item/transaksi atau
multi-tax rate adalah add-on Enterprise dan tidak diimplementasikan sebelum
kontraknya ditetapkan. Shadow company bukan mekanisme pajak fase dasar.

## G-04: UI Runtime Strategy Resolution (DIKOREKSI 2026-09-16)

**Koreksi:** resolusi sebelumnya yang melarang Livewire adalah **keliru** karena
diputuskan tanpa memeriksa codebase. Verifikasi aktual membuktikan sebaliknya:

| Bukti | Nilai |
|---|---|
| `composer.json` | `livewire/livewire: ^4.4` terpasang sebagai dependency utama |
| `app/Livewire/` | `Lobby.php`, `Sidebar.php`, `CommandPalette.php`, `DummyModule.php` sudah ada |
| `routes/web.php` | route `/` dan `/app/{module}/{path?}` di-bind langsung ke komponen Livewire |
| `php artisan route:list` | endpoint internal `livewire-b29f7fbd/*` aktif |
| `package.json` | tidak ada `alpinejs`; Alpine berasal dari bundel Livewire |
| `resources/js/app.js` | kosong (`//`), dan `public/build/assets/app-*.js` berukuran 0 byte |

**Resolusi final:** gunakan **Laravel 13 + Livewire v4 + Blade + Tailwind v4 +
Vite**. Melarang Livewire akan membuang implementasi Fase 1 yang sudah berjalan.
Jangan menambahkan paket `alpinejs` terpisah: dua instans Alpine merusak
reaktivitas Livewire.

## G-05: Versi Tailwind Tidak Cocok

`UX_UI_SPEC.md` semula menyebut Tailwind v3.4.19, sedangkan `package.json`
memasang `tailwindcss: ^4.3.3` dengan `@tailwindcss/vite`. Tailwind v4 memakai
konfigurasi CSS-first (`@import 'tailwindcss'` + `@theme`) dan tidak memakai
`tailwind.config.js`. Spesifikasi sudah dikoreksi ke v4.

## G-06: Kontrak Database Bertentangan — RESOLVED (B-01)

`.env`/`phpunit.xml` memakai SQLite, `DATA_MODEL.md` awalnya berisi DDL MySQL
mentah. **Resolusi:** semua migration wajib Laravel Schema Builder portabel
(`DATA_MODEL.md` §0). Dev/test tetap SQLite; paritas MySQL diverifikasi di
**T-21b** sebelum release. `database/database.sqlite` kini ada.

## G-07: Workspace Bukan Git Repository — RESOLVED (B-02)

`git init` dijalankan 2026-09-16 setelah memverifikasi `.gitignore` mengecualikan
`.env`, `vendor`, `node_modules`, `auth.json`, `storage/*.key`. Baseline commit
`7151104`. **Remote belum dikonfigurasi** — push tetap butuh remote + approval.

## G-08: Fondasi Tenant Diasumsikan Ada Padahal Tidak (ditemukan audit paralel 2026-09-16)

`DATA_MODEL.md` §1 semula berjudul "Perubahan Tabel Eksis" dan menyebut
`companies`, `module_settings`, `business_identities`, `project_center`,
`subcontractor_spk`, `crm_leads` "sudah ada". Semua ditulis untuk codebase ERP
Prime warisan. Repo canonical hanya punya `users/cache/jobs`.

**Resolusi:** §1 ditulis ulang menjadi `CREATE`; ditambah **Fase 3a** (T-00a/b/c)
di `EXECUTION_PLAN.md`. T-13 tidak lagi "drop `crm_leads`". Klaim D-08 "webhook
sudah live" dikoreksi.

## G-09: Dua Skema Routing Bertentangan — RESOLVED (D-24)

`INDUSTRY_PRESETS.md` §2 memakai nama route Laravel (`crm.leads.index`,
`settings.ai-agent.index`) yang **tidak ada**; `UX_UI_SPEC.md` memakai `/settings`
dan `/dashboard`; D-20 memakai `/operations/*`. Kode nyata hanya punya
`/` dan `/app/{module}/{path?}`.

**Resolusi:** D-24 mengunci skema `/app/{module}/...`. §2 INDUSTRY_PRESETS ditulis
ulang ke bentuk flag-aware dengan URL path; UX_UI_SPEC dan D-20 dikoreksi.

## G-10: `module_settings` Dua Bentuk — RESOLVED (D-25)

D-19 (LOCKED) = satu baris per modul dengan `settings_json`; DATA_MODEL/REQUIREMENTS
lama = baris key-value per flag. **Resolusi:** D-19 menang; flag disimpan di
`module_settings[module_name='features'].settings_json`. Kedua dokumen dikoreksi.

## G-11: Keputusan yang Belum Pernah Dicatat — DICATAT sebagai Q-01..Q-08

Autopilot sebelumnya akan menebak: payment gateway, driver Scout, palet 36 token,
kardinalitas `hermes_profiles`, nama stage CRM, daftar vendor string, mekanisme
onboarding, konteks tenant. Semua kini tercatat di `00-DECISIONS.md` §OPEN
**dengan rekomendasi default** agar Bos cukup menyetujui.

## Baseline Terverifikasi

Lihat `AUTOPILOT_STATUS.md` §Verified Baseline (sumber tunggal, selalu terbaru).
Baseline **awal** repo (2 test, tanpa Git) adalah historis.

## Status Task Fase 1 (final)

| ID | Status | Bukti |
|---|---|---|
| T-01 | DONE | `composer.json`, `package.json`, build hijau |
| T-02 | DONE | `LobbyNavigationTest` (4). Kartu → `route('app.module')`, tombol search punya handler + `aria-label` |
| T-03 | DONE (statis) | `DynamicMenuRegistryTest` (7) + `ModuleSidebarTest` (4). Modul tak dikenal → **nol DOM**. Flag-aware di T-03b |
| T-04 | DONE (dummy) | Modal Alpine + `wire:model.live`. Dummy `href="#"` diselesaikan T-20 |

## Temuan yang Sudah Diperbaiki (2026-09-16, review paralel ke-3)

- `AGENTS.md`/`CLAUDE.md` memerintahkan `composer require laravel/boost` — **diganti**
  dengan pointer ke `HERMES.md`.
- Sidebar merender `<p>` placeholder untuk modul tak dikenal — **dihapus**; test
  kini memverifikasi `<nav>` benar-benar kosong.
- Tombol search di Lobby inert tanpa `aria-label` — **diberi handler + label**.
- `<main>` tanpa `id="main-content"` — **ditambah**.
- Pint gagal di `routes/web.php`, `CommandPalette.php`, `DummyModule.php` — **diperbaiki**;
  seluruh repo kini lolos `pint --test`.
- `accounting_journal_lines` tanpa `company_id` — **ditambah** (D-26).
- `pharmacy_prescriptions.image_path` melanggar BYOS — **dihapus** (D-29).
- `invoices` hanya top-up padahal D-23 butuh subscription — **ditambah `type`, `period_*`**.
- `operations.visible` melupakan `ops.rental_checkin` — **diperbaiki**.
- Flag `finance.*`/`hr.*` tidak ada di matriks — **ditambah**.
- Penomoran section DATA_MODEL duplikat — **dirapikan** 0..14.
- 36 token `--erp-*` tidak pernah didefinisikan — **ditambah** `UX_UI_SPEC.md` §7.

## Temuan Terbuka (tidak diperbaiki, dicatat)
> **Rujukan task diperbarui 2026-09-16** mengikuti D-42: T-05/T-06/bagian UI T-20 dipindah ke Fase 2 menjadi T-F7/T-F9/T-F13.

- `README.md` masih stock Laravel + promosi Boost. Diperbaiki saat T-22.
- `composer.json` `name: laravel/laravel`. Diperbaiki saat T-22.
- `resources/views/welcome.blade.php` tidak dipakai. Dihapus saat **T-F7**.
- `CommandPalette` dummy `href="#"` (5 item). Diselesaikan **T-F13** (UI), lalu T-20 (Scout nyata).
- `DummyModule` `href="#"` di baris tabel. Diselesaikan **T-F9**.
- `/app/{module-tak-dikenal}` masih 200. Menjadi 404 di **T-F8**, ditegakkan ulang T-16.
- Dua layout (`layouts/app.blade.php`, `components/layouts/module.blade.php`) tanpa
  skip-link. Disatukan/ditambah skip-link di **T-F7**.

---

## G-12: Review Bisnis Ujung-ke-Ujung (2026-09-16) - RESOLVED

Review menyeluruh seluruh dokumen atas permintaan Bos. Ditemukan & diperbaiki:

**Kontradiksi internal (7):** D-32 salah hitung kapabilitas (18 -> 21); D-36 dan
U-06 bertabrakan dengan D-43 (ditandai digantikan); PRD memakai nama warisan
"ERP Nalarin" (D-30) dan masih menyatakan "1 bot per perusahaan" padahal D-37
menetapkan per owner; `companies.theme` (D-43) dan `admin_impersonation_sessions`
(D-47) disebut keputusan tetapi belum ada di skema.

**Kesalahan skema (4):** `workflow_transitions_log.approval_ticket_id` adalah
**FK menggantung** - tabel `approval_tickets` tidak pernah didefinisikan padahal
D-27/D-45 bergantung penuh padanya (kini DATA_MODEL 1.8); tabel hilang
`cash_entries` (4.4), `quotations` + `quotation_lines` (4.5) yang dituntut
D-32/T-F2.

**Celah kebijakan bisnis (9) -> D-48..D-56:** perilaku saat token habis, tangga
penurunan layanan saat menunggak, kepatuhan UU PDP, trial + ekspor data, gerbang
kapabilitas per paket, kuota grup vs bot, penghapusan janji on-premise,
kepemilikan nomor WhatsApp, dan katalog add-on resmi.

**Celah spesifikasi (3):** `TaxRateService` tidak menangani `non_taxable`
(mayoritas klien); `FeatureResolver` belum memuat gerbang paket D-52; tidak ada
spesifikasi UI untuk status akun trial/menunggak/hanya-baca/beku (kini UX 6.11).

**Risiko dokumen:** judul REQUIREMENTS 4 "Spesifikasi 6 Modul Industri Spesifik"
berpotensi dibaca autopilot sebagai izin membangun modul per industri -
dikoreksi menjadi "Contoh Komposisi Kapabilitas" dengan peringatan D-31.

Semua keputusan kini memiliki task pelaksana (T-10c, T-10d, T-12b, T-18
diperluas, Fase 4b T-27..T-27e, Fase 6b).
