# MQ-01 — Peningkatan Kualitas Modul Dashboard

## Tujuan

Membawa Dashboard ke status FUNCTIONAL, SECURE, MOBILE-PASS, A11Y-PASS,
NEGATIVE-TESTED, REGRESSION-PASS, dan LIVE-SMOKE-PASS tanpa perubahan
arsitektur, dependency, migration, atau logika berbasis nama industri.

## Kontrak wajib

- D-31: preset/jenis bisnis adalah data; kode hanya memakai capability dan istilah generik.
- Data selalu melalui `CompanyContext`, `PresetSource`, `EntityRepository`, dan resolver yang sudah ada.
- Data uang wajib fail-closed: baris kas dengan arah/jumlah tidak valid tidak boleh diam-diam mengubah angka Dashboard.
- Tenant aktif tidak boleh membaca data company lain.
- Tidak ada push/deploy; commit lokal dan merge serial saja.

## Part kecil yang dapat didelegasikan

### MQ-01A — Audit kontrak (read-only)

**Scope:** `app/Livewire/Dashboard.php`, `app/Services/Dashboard/**`, view Dashboard,
route/middleware Dashboard, dan test Dashboard.

**Keluaran:** temuan berbukti `file:line`, tingkat risiko, reproduksi, dan test yang
harus ditambah. Pisahkan defect nyata dari penilaian visual browser-only.

### MQ-01B — Acceptance dan test RED

**Scope utama:** `tests/Feature/DashboardTest.php`; tambah file test baru hanya jika
kontrak tidak cocok ditempatkan di sana.

Acceptance minimum:

1. Reload setelah kegagalan dapat pulih dan menghapus pesan error.
2. Data kas dengan `direction` selain `in|out`, amount non-numerik, negatif, atau
   non-finite ditolak; Dashboard tidak menampilkan total finansial menyesatkan.
3. KPI dan widget arus kas memakai aturan kalkulasi yang sama.
4. Data lintas-company tidak masuk ke KPI/widget.
5. Preset/widget tanpa capability tetap tidak dirender.

Jalankan focused test dan buktikan test defect RED sebelum implementasi.

### MQ-01C — Implementasi terkecil (writer)

**Allowed paths:**

- `app/Services/Dashboard/**`
- `app/Livewire/Dashboard.php`
- `resources/views/livewire/dashboard.blade.php`
- `resources/views/livewire/widgets/dashboard-widget.blade.php`
- test Dashboard terkait

Larangan: migration, model baru, dependency, route umum, nama industri pada kode,
atau refactor di luar Dashboard. Gunakan service/helper generik hanya jika perlu
untuk mencegah dua kalkulasi uang berbeda.

### MQ-01D — QA diff (read-only)

Review independen untuk correctness, tenant isolation, fail-closed uang, D-31,
a11y/mobile, dan kualitas negative test. Temuan HIGH/MEDIUM wajib selesai sebelum
merge; browser-only dicatat untuk smoke.

### MQ-01E — Gate dan merge

Di branch worker:

```text
DATA_SOURCE=json php artisan test tests/Feature/DashboardTest.php tests/Feature/DashboardEloquentTest.php
DATA_SOURCE=json php artisan test
php vendor/laravel/pint/builds/pint --test
npm run build
git diff --check
```

Setelah merge serial ke `main`, ulangi full test, Pint, build, lalu smoke route
Dashboard aktual. Pulihkan fixture test yang berubah sebelum commit.

## Definition of Done

- Semua acceptance teruji, termasuk negative test uang dan tenant.
- Full suite, Pint, build, dan diff-check hijau dengan output nyata.
- Tidak ada hardcode nama industri atau dependency/migration baru.
- QA independen tidak menyisakan HIGH/MEDIUM.
- Commit lokal terpisah dan `AUTOPILOT_STATUS.md` mencatat evidence serta modul
  berikutnya.
