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

### MQ-01C — Implementasi terkecil (writer, serial per slice)

Temuan audit ditutup dalam commit kecil berikut. Hanya satu slice boleh memiliki
writer aktif pada satu waktu.

#### MQ-01C1 — Integritas uang dan recovery

**Allowed paths:** `app/Services/Dashboard/**`, `app/Livewire/Dashboard.php`, view
Dashboard/widget, serta test Dashboard. Tutup acceptance MQ-01B nomor 1–4 dan
pastikan settings/theme failure juga masuk error state terkontrol.

#### MQ-01C2 — Tenant authorization fail-closed

**Allowed paths:** middleware/company context, registrasi persistent middleware
Livewire, dan negative test tenant terkait. Jadikan company ID yang benar-benar
dikembalikan `CompanyContext` sebagai ID yang diotorisasi. Buktikan mismatch
`current_company_id` versus `active_company`, ownership yang dicabut setelah
mount, dan capability yang dicabut setelah mount ditolak tanpa membocorkan data.
Request Livewire nyata diperlukan untuk klaim middleware persistence.

#### MQ-01C3 — Kontrak preset-widget-capability

**Allowed paths:** `WidgetRegistry`, validator preset, data preset terkait, dan
test matriks seluruh preset. Satu kontrak widget→capability harus dipakai runtime
dan validator. Deklarasi widget yang capability-nya tidak aktif harus gagal jelas,
bukan hilang diam-diam. Penyelesaian mismatch dilakukan sebagai data preset,
tanpa cabang nama industri di kode.

#### MQ-01C4 — Parity identitas dan error state

Pastikan datasource Eloquent menampilkan nama company, bukan ID numerik, dan
JSON/Eloquent memiliki kontrak tampilan setara. Tambahkan test terfokus.

#### MQ-01C5 — Browser/mobile/a11y smoke

Buktikan reload/loading, fokus setelah navigasi, viewport 360–390 px, serta aksi
keyboard dengan browser aktual. Temuan shell/Command Palette yang bukan milik
Dashboard dipindahkan ke boundary modul shell berikutnya dan tidak disisipkan ke
commit Dashboard.

Larangan seluruh slice: migration, dependency, route umum, nama industri pada
kode, atau refactor di luar acceptance slice.

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
