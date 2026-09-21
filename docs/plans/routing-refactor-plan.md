# PLAN — Refactor Routing Modul (hilangkan wrapper DummyModule yang rapuh)

## Masalah yang ditutup (terbukti, bukan tebakan)
`DummyModule` adalah Livewire component yang jadi route `/app/{module}` (top-level), lalu merender komponen layar (`ListScreen` dsb) sebagai nested component via `@livewire()`. Karena parent re-render tiap request, `wire:id` anak berubah → klik tombol anak dikirim dengan id basi → Livewire salah arahkan ke `DummyModule` yang tidak punya method `create` → error "MethodNotFoundException". Ini membuat SEMUA modul tidak bisa dipakai (bukan cuma inventory).

## Solusi jangka panjang (benar, bukan tambal)
Route `/app/{module}/{submodule?}` harus **langsung ke komponen layar yang sebenarnya** (ListScreen, CashierScreen, dsb), bukan ke wrapper. Hilangkan lapisan `DummyModule` sepenuhnya.

## Cara yang benar (Livewire v4)
Livewire v4 mendukung **route model binding ke komponen** dan **route ke komponen dengan parameter**. Pola yang benar:
- `Route::get('/app/{module}/{submodule?}', ...)` harus resolve ke komponen layar berdasarkan `DynamicMenuRegistry`, TANPA wrapper.
- Karena nama komponen diturunkan dari data (screen pattern), kita butuh **resolver** yang memetakan `{module}/{submodule}` → kelas komponen, lalu meneruskannya sebagai route component.

## Opsi implementasi (pilih yang paling bersih untuk Livewire v4)
A. **Route ke komponen via closure yang me-resolve kelas** — Laravel route bisa mengembalikan komponen secara dinamis. Tapi Livewire route harus tahu kelasnya saat compile.
B. **Satu route per pola layar** — `Route::get('/app/{module}/{submodule?}', ListScreen::class)` untuk modul berpola `list`, dst. Registry menentukan pola → kita daftarkan route per pola. Paling eksplisit dan stabil.
C. **DummyModule jadi plain controller (bukan Livewire)** yang merender Blade berisi `@livewire(screenComponent)` — sehingga komponen layar jadi top-level Livewire component di halaman. Wrapper bukan lagi Livewire component.

## Batasan keras
- D-31 (tanpa industri), D-26 (company_id), fail-closed tetap terjaga.
- Tidak ada migration/dependency.
- Semua modul yang sudah ada harus tetap berfungsi (POS, kontak, stok, HRD, akuntansi, settings).
- Test harus mensimulasikan KLIK aksi (Livewire::test()->call()), bukan hanya render.

## Scope file (lane tunggal, menyentuh routing)
- `routes/web.php` — ubah route `/app/{module}` sesuai opsi terpilih
- `app/Livewire/DummyModule.php` — refactor atau hapus sesuai opsi
- `resources/views/livewire/dummy-module.blade.php` — refactor atau hapus
- Komponen layar yang ada (`app/Livewire/Screens/*.php`) — pastikan bisa jadi top-level route component (terima `{module}` dari route)
- `tests/Feature/` — tambah test yang mensimulasikan klik aksi untuk tiap pola layar

## Negative test wajib
- Klik "Tambah" di inventory → method `create` terpanggil pada komponen yang benar (bukan wrapper).
- Modul dengan kapabilitas dicabut → 403 (fail-closed).
- Route tidak dikenal → 404.

## Verifikasi
- `DATA_SOURCE=json php artisan test` (full)
- Test baru yang mensimulasikan klik aksi
- `php vendor/laravel/pint/builds/pint --test`
- `npm run build`
- Smoke: klik Tambah di inventory dari browser nyata → berfungsi.

## Pertanyaan untuk QA
- Opsi mana yang paling sesuai Livewire v4 dan paling sedikit risiko regresi?
- Apakah menghilangkan wrapper merusak konvensi tema/layout yang sudah ada?
- Bagaimana cara paling aman meneruskan `{module}/{submodule}` ke komponen layar?
