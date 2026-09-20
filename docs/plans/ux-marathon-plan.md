# PLAN MARATON UI/UX — Agentic BOS

## Tujuan
Memoles UI/UX agar lancar dipakai user nyata (mobile-first dari HP), sebelum WA agent.

## Prinsip
- D-31: tidak ada nama industri di kode/UI — semua via `term()` dan preset.
- D-24: semua route di bawah `/app/{module}/...`.
- D-20: Midnight Command (dashboard) + Operator Grid (operasional).
- Mobile-first: Bos dan klien utama pakai HP.
- Fail-closed untuk uang/tenant.

## Matriks lane paralel (file disjoint, tidak ada irisan)

### LANE A — Onboarding & Publik (writer: Claude, worktree: worker-a)
File scope (hanya ini):
- `app/Livewire/Onboarding.php`
- `resources/views/livewire/onboarding.blade.php`
- `resources/views/livewire/public/**`
- `routes/web.php` HANYA bagian route publik/onboarding (koordinasi: edit terpisah, commit duluan)
- `tests/Feature/Onboarding*`, `tests/Feature/Public*`

Task:
1. Onboarding owner baru: alur daftar → pilih preset → identitas usaha → jalan. Mobile-friendly, progress jelas, empty state informatif.
2. Halaman publik `/industri` ("Cocok untuk bisnis apa?"): daftar preset dari registry, bukan hardcode; CTA ke daftar.
3. Empty state & error state yang membantu di semua halaman onboarding/publik.

### LANE B — Dashboard & Command Palette (writer: Claude, worktree: worker-b)
File scope:
- `app/Livewire/Dashboard.php`, `app/Livewire/CommandPalette.php`
- `resources/views/livewire/dashboard.blade.php`
- `resources/views/livewire/command-palette.blade.php`
- `resources/views/livewire/widgets/**`
- `app/Livewire/Widgets/**`
- `tests/Feature/Dashboard*`, `tests/Feature/CommandPalette*`, `tests/Feature/Widget*`

Task:
1. Dashboard: hierarki visual KPI, loading skeleton, error recoverable (sudah ada, poles), responsif HP.
2. Command palette (Ctrl+K): hasil nyata dari repository, keyboard navigable, a11y (focus trap, aria).
3. Widget: konsistensi kartu, angka tabular, empty state.

### LANE C — Layar Operasional & Settings (writer: Claude, worktree: worker-c)
File scope:
- `app/Livewire/Screens/*.php` (CashierScreen, ListScreen, LedgerScreen, PipelineScreen, CalendarScreen)
- `resources/views/livewire/screens/**`
- `app/Livewire/Settings*.php`, `resources/views/livewire/settings/**`
- `tests/Feature/Screens*`, `tests/Feature/Settings*`

Task:
1. Layar operasional (kasir, daftar, ledger, pipeline, kalender): tabel padat mobile, aksi utama jelas, konfirmasi bertingkat sesuai D-45.
2. Settings 9 tab: konsistensi form, validasi inline, feedback sukses/gagal.
3. Aksi berisiko (void/hapus): pola konfirmasi seragam (D-45 tingkat 1/2/3).

### LANE BERSAMA (serial, saya yang kerjakan, commit duluan sebelum paralel)
- `resources/views/components/layouts/module.blade.php` + `guest.blade.php` + `sidebar.blade.php` + `branch-switcher.blade.php`
  → karena dipakai semua lane, harus stabil dulu sebagai commit terpisah sebelum paralel dimulai.
- `resources/css/app.css` (design token) — disentuh terakhir, serial, setelah semua lane merge.

## Urutan eksekusi
1. **Serial dulu (saya):** rapikan layout bersama + pastikan baseline hijau (test/pint/build) → commit.
2. **Paralel:** Lane A, B, C di worktree masing-masing, branch `task/ux-a`, `task/ux-b`, `task/ux-c`.
3. **Reviewer:** OpenCode/Kiro read-only per lane setelah writer selesai.
4. **Final gate (saya):** per lane → test + pint + build + diff-check → merge satu per satu ke main (serial) → full suite setelah tiap merge.
5. **Terakhir (serial):** design token CSS bila perlu, lalu full regression + build final.

## Gate & batasan
- Tidak ada migration, tidak ada dependency baru, tidak ada push/deploy.
- Tidak menyentuh logika bisnis/backend selain yang perlu untuk UI.
- Setiap lane: RED/acceptance test untuk perubahan perilaku, lalu implementasi.
- Money/auth/tenant: negative test wajib.
- Berhenti dan lapor jika menemukan kebutuhan di luar scope UI.

## Verifikasi akhir maraton (wajib, saya jalankan sendiri)
- `php artisan test` (full)
- `php vendor/laravel/pint/builds/pint --test`
- `npm run build`
- `git diff --check`
- Smoke ulang dari HP: dashboard, POS, inventory, HRD, settings, export.
