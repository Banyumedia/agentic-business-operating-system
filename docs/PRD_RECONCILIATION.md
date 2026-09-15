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

## G-06: Kontrak Database Bertentangan (BLOCKER Fase 3)

| Sumber | Nilai |
|---|---|
| `.env` | `DB_CONNECTION=sqlite` |
| `phpunit.xml` | `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:` |
| `docs/DATA_MODEL.md` | DDL MySQL: `ENUM(...)`, `BIGINT UNSIGNED AUTO_INCREMENT`, `ALTER TABLE ... ADD COLUMN` |
| `database/database.sqlite` | belum ada |

SQLite tidak mendukung `ENUM` dan memiliki keterbatasan `ALTER TABLE`. Menulis
migration mengikuti DDL MySQL mentah akan gagal pada environment saat ini.

**Keputusan yang dibutuhkan Bos (salah satu):**
1. Pakai MySQL/MariaDB untuk dev dan test, samakan dengan produksi.
2. Tetap SQLite untuk dev, dan tulis semua migration memakai Laravel Schema
   Builder portabel (`string` + validasi/`check` alih-alih `ENUM` mentah).

Sampai ini diputuskan, seluruh task migration Fase 3 berstatus BLOCKED.

## G-07: Workspace Bukan Git Repository (BLOCKER Proses)

`D:\PROJECTS\agentic-bos\.git` tidak ada, meskipun `.gitignore` dan
`.gitattributes` tersedia. Konsekuensi nyata:

- Tidak ada `git diff` untuk review independen.
- Tidak ada baseline SHA, sehingga strategi *isolated worktree* untuk writer
  paralel tidak dapat dipakai.
- Gate commit/push tidak dapat dijalankan.

Sebelum diperbaiki, review harus berbasis snapshot file dan hasil test, dan
paralelisme writer dibatasi menjadi satu writer saja.

## Baseline Terverifikasi (2026-09-16)

| Item | Status |
|---|---|
| Laravel | 13.32.0 |
| PHP | 8.3.30 |
| Livewire | v4.4 terpasang, Fase 1 sudah berjalan |
| Tailwind | v4.3.3 (CSS-first `@theme`) |
| Test suite | `php artisan test` → **2 passed, 2 assertions** (hanya stub `ExampleTest`) |
| Migration bisnis | belum ada; hanya `users`, `cache`, `jobs` bawaan |
| Model bisnis | belum ada; hanya `App\Models\User` |
| Build Vite | `public/build/` tersedia |
| Git | tidak terinisialisasi |

## Status Task Fase 1 Berdasarkan Kode Nyata

| ID | Klaim PRD | Status Aktual |
|---|---|---|
| T-01 | Setup Laravel + Livewire + Tailwind | **SELESAI** (Livewire v4.4, Tailwind v4.3) |
| T-02 | Lobby & App Switcher | **PARSIAL** — grid aplikasi dirender, tetapi setiap kartu memakai `href="#"` sehingga tidak berpindah modul; acceptance criteria belum terpenuhi |
| T-03 | Dynamic Sidebar terisolasi | **PARSIAL** — menu per modul ada, tetapi masih array hardcoded di `Sidebar::getMenusProperty()`, belum lewat `DynamicMenuRegistry` maupun feature flag |
| T-04 | Universal Search `Ctrl+K` | **SELESAI (dummy)** — modal Alpine + `wire:model.live` berfungsi dengan data dummy sesuai acceptance criteria |

## Temuan Tambahan

- `AGENTS.md` di root masih berisi bootstrap Laravel Boost yang menyuruh agent
  menjalankan `composer require laravel/boost`. Ini instruksi yang bersaing
  dengan `HERMES.md`; `HERMES.md` berprioritas lebih tinggi pada Hermes.
- `resources/views/welcome.blade.php` bawaan Laravel masih ada dan tidak dipakai
  route mana pun.
- Token `--erp-*` pada `UX_UI_SPEC.md` belum ada sama sekali di `app.css`.
- Karena test memakai SQLite `:memory:` (terisolasi per proses), shard test
  paralel **aman** dan tidak berbagi database yang sama.

## Non-Blocking Review Notes

- The six initial industry presets, dynamic feature flags, zero-DOM rendering,
  server-side feature guards, role-scoped bot tools, and two-step approval for
  high-risk actions are consistent across the reviewed documents.
- UI task acceptance criteria must include the WAI-ARIA tab model, keyboard
  behavior, 44px mobile touch targets, modal focus trapping, and zero-bloat DOM
  checks from `UX_UI_SPEC.md`; visual completion alone is insufficient.
- Payment-webhook, token-balance, WhatsApp role, and financial mutation tasks
  require tenant-isolation, authorization, idempotency, and negative tests
  before any implementation is considered ready.
- Node API dan webhook secret disimpan sebagai referensi ke secret manager atau
  encrypted secret store; migration tidak boleh menyimpan nilai secret plaintext.
