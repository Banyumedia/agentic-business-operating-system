# UX-C — Layar Operasional & Settings (report)

Branch: `task/ux-c` · Head: `288f05a` · Date: 2026-09-20

## Scope review result

Lima layar operasional (Cashier, List, Ledger, Pipeline, Calendar) dan Settings 9 tab
sudah memenuhi target lane saat audit mulai: tabel padat mobile-first (desktop table
`hidden md:block` + kartu `md:hidden`), aksi utama menonjol (tombol aksen `min-h-11`),
empty state berikon + istilah `term()`, loading state di aksi. Konfirmasi berisiko
sudah bertingkat sesuai D-45 (ketik YA checkout/erasure, dua tombol + inert 400ms
untuk hapus baris, tanpa konfirmasi untuk reversibel). Yang ditemukan: tiga cacat
nyata di bawah — semua diperbaiki.

## Changes

1. `resources/views/livewire/settings/data-erasure.blade.php`
   **Defek:** blade mengikat `contactName` yang tidak pernah ada di komponen —
   form penghapusan tidak bisa dipakai sama sekali. Dibangun ulang mengikat state
   nyata: pencarian nama (debounce 300ms, PHP-side `contactMatches()` karena nama
   terenkripsi D-42), daftar hasil klik untuk memilih ID, input ID, konfirmasi
   ketik YA dengan `autocapitalize="characters" autocorrect="off"` tetap utuh.

2. `app/Livewire/Settings/DataExport.php` + blade + test
   **Defek fail-closed:** `export()` hanya mengandalkan visibilitas tab owner
   (registry) — tidak ada revalidasi owner di dalam aksi, berbeda dengan
   `DataErasure`. Sekarang `abort_unless(isOwnerOfCompany($companyId), 403)` via
   `CompanyRoleResolver`, pola identik dengan erasure. Blade ekspor juga
   ditarik ke token `--erp-*` (sebelumnya warna Tailwind mentah
   `bg-green-50 text-green-700 rounded-lg`). Test negatif baru: staff
   memanggil `export` → 403, job `BuildCompanyExport` tidak ter-dispatch.

3. `resources/views/livewire/screens/calendar.blade.php`
   Papan kalender diberi isyarat loading (`wire:loading.class` opacity +
   pointer-events-none, target `setView, shift, today`). Ledger sengaja tidak —
   murni render baca tanpa aksi.

## Verification (all run in this worktree)

Test runner butuh `DATA_SOURCE=json` (driver dari `.env` dibaca suite ini;
`DATA_SOURCE=eloquent` bikin seluruh tes JSON-driver merah — bukan regresi
kode). `.env` dikembalikan ke nilai asli (`eloquent`) setelah tiap run.

```
php artisan test --filter=Screen      → {"result":"passed","tests":63,"passed":63,"assertions":364}
php artisan test --filter=Settings    → {"result":"passed","tests":23,"passed":23,"assertions":92}
php artisan test --filter=DataExport  → {"result":"passed","tests":4,"passed":4,"assertions":9}
php artisan test                      → {"result":"passed","tests":584,"passed":584,"assertions":3161}
php vendor/laravel/pint/builds/pint --test → {"tool":"pint","result":"passed"}
npm run build                          → ✓ built in 1.84s
git diff --check                       → clean
```

## Notes / environment

- Worktree tidak punya `vendor`/`node_modules`/`public/build` sendiri (semua
  ter-`.gitignore`). Instalasi lokal diperlukan supaya PHPUnit tidak memuat
  kode dari checkout utama (junction `vendor` mengarahkan `App\` ke
  `D:\PROJECTS\agentic-bos\app` — sudah diganti salinan penuh).
- Tab assistant/usage/team masih placeholder "Segera" — membangunnya di luar
  lane ini (butuh task fitur terkait).
