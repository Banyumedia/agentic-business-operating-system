# PLAN — Registrasi Mandiri (Self-Serve Signup)

## Masalah yang ditutup
Saat ini TIDAK ada cara user baru membuat akun — tidak ada route `/register`, onboarding butuh `auth()->user()`. Flow "dari user daftar" terputus di langkah pertama. Ini blocker untuk produk self-serve.

## Tujuan
User baru bisa daftar sendiri (nama, email, password) → akun terbuat → langsung diarahkan ke onboarding untuk membuat usaha pertama.

## Batasan keras
- D-26: user baru belum punya company — company dibuat di onboarding, BUKAN saat registrasi.
- Fail-closed: email harus unik; password minimal kuat; tidak ada auto-login ke company orang lain.
- D-31: tanpa nama industri di kode.
- Tidak ada migration baru tanpa izin (tabel `users` sudah ada — cukup).
- Registrasi TIDAK membuat company — itu tugas onboarding.

## Scope (file disjoint, lane tunggal karena menyentuh auth)
- `app/Livewire/Auth/Register.php` (baru) — komponen registrasi
- `resources/views/livewire/auth/register.blade.php` (baru) — form
- `routes/web.php` — tambah `GET /register` (guest only)
- `resources/views/livewire/auth/login.blade.php` — tambah tautan "Belum punya akun? Daftar"
- `resources/views/livewire/public/industry-list.blade.php` — CTA "Masuk atau daftar" arahkan ke register bila belum punya akun
- `tests/Feature/Auth/RegisterTest.php` (baru)

## Alur
1. Guest buka `/register` → isi nama, email, password + konfirmasi.
2. Validasi: email unik, password min 8 + konfirmasi cocok.
3. Akun `users` terbuat (tanpa company).
4. Auto-login setelah daftar.
5. Redirect ke `/onboarding` untuk membuat usaha pertama.

## Negative test wajib
- Email yang sudah terdaftar ditolak (bukan duplikat diam-diam).
- Password < 8 atau konfirmasi beda → ditolak dengan pesan jelas.
- User yang sudah login membuka `/register` → diarahkan ke dashboard (bukan form).
- Registrasi TIDAK membuat company (company_count tetap 0 setelah daftar).

## A11y + mobile-first + token `--erp-*` + form error fokus ke field pertama yang salah (pola dari I6).

## Verifikasi
- `DATA_SOURCE=json php artisan test` (full) + test baru
- `php vendor/laravel/pint/builds/pint --test`
- `npm run build`
- `git diff --check`
- Smoke: daftar akun baru → auto-login → sampai /onboarding.

## Pertanyaan untuk QA
- Apakah auto-login setelah daftar aman, atau perlu verifikasi email dulu?
- Apakah perlu rate-limit / anti-bot di registrasi (misal throttle)?
- Apakah registrasi harus memicu persetujuan privasi di sini atau di onboarding (saat ini onboarding punya langkah persetujuan)?
