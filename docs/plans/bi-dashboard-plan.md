# PLAN FASE BERIKUTNYA — Business Intelligence Ringan di Dashboard

## Tujuan
Mengubah panel "Laporan AI" di dashboard dari template statis menjadi **analisis nyata berbasis data** perusahaan: omzet, biaya, tren, dan sorotan yang bisa ditindaklanjuti. Config-driven, tanpa nama industri (D-31).

## Batasan keras
- D-31: tidak ada nama industri di kode; istilah via `term()`.
- D-26: semua query bisnis scoped `company_id`.
- D-50: ini membaca data keuangan → kategori `sensitive`; hanya owner; tercatat di `access_logs`.
- Config-driven: metrik yang dihitung dan ambang peringatan dibaca dari config/preset, bukan hardcode.
- Tidak ada migration baru (pakai tabel yang sudah ada: cash_entries, orders, invoices, dll).
- Fail-closed: bila data tidak cukup, tampilkan pesan sopan, bukan angka bohong.

## Lane paralel (file disjoint)

### Lane BI-A — Mesin analisis (backend)
- Scope: `app/Services/Dashboard/**`, `app/Services/Analytics/**` (baru), `config/analytics.php` (baru), test.
- Tugas: service `BusinessHealthAnalyzer` yang menghitung dari data nyata:
  - omzet periode (dari cash_entries/orders),
  - biaya (cash_entries negatif),
  - margin/laba sederhana,
  - tren (naik/turun/datar vs periode sebelumnya),
  - sorotan (mis. pengeluaran terbesar, pelanggan paling aktif) — generik, bukan industri-spesifik.
- Ambang (mis. "omzet turun >20%") dari config, bukan hardcode.
- Test: positive (data cukup → analisis benar), negative (data kosong → pesan sopan, bukan error), tenant isolation (data company lain tidak ikut).

### Lane BI-B — Panel dashboard (frontend)
- Scope: `resources/views/livewire/dashboard.blade.php`, `app/Livewire/Dashboard.php`, widget terkait, test.
- Tugas: tampilkan hasil BI-A sebagai kartu/panel yang rapi:
  - "Kesehatan Usaha" (omzet, biaya, margin, tren dengan ikon naik/turun),
  - sorotan yang bisa ditindaklanjuti,
  - loading skeleton, empty state sopan,
  - mobile-first + a11y + token `--erp-*`.
- Jangan ubah `DashboardComposer` struktur — konsumsi service baru.

## Verifikasi wajib (tiap lane)
- `DATA_SOURCE=json php artisan test` (full)
- `php vendor/laravel/pint/builds/pint --test`
- `npm run build`
- `git diff --check`

## QA & gate
- Writer: Claude Code (paralel, file disjoint).
- QA independen: Codex CLI (review diff read-only per lane).
- Smoke: Hermes jalankan test + build + smoke route.
- Merge serial ke main setelah QA + gate hijau.

## Risiko
- Data demo mungkin tipis → analyzer harus fail-graceful (pesan sopan) dan test memakai fixture minimal.
- D-50 sensitive → pastikan hanya owner + access_logs.
