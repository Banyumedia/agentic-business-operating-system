# PLAN — Halaman "Pilih Paket" + Pembayaran Manual (Transfer / Static QRIS)

## Tujuan
Klien yang kuotanya habis (atau dari paywall) bisa memilih paket, melihat instruksi bayar via transfer bank + static QRIS, lalu menunggu Bos mengaktifkan paket dari admin. Tidak butuh payment gateway.

## Batasan keras
- D-61: setelah kuota habis WAJIB berbayar — halaman ini adalah jalur bayarnya.
- D-26: `company_id` pada setiap permintaan/langganan. D-31: tanpa nama industri. D-52: paket dari `membership_plans` (data).
- Fail-closed: paket TIDAK aktif otomatis; hanya aktif setelah Bos konfirmasi (admin action). Tidak ada aktivasi otomatis dari sisi klien.
- Config-driven: nomor rekening, nama bank, dan gambar QRIS statis dibaca dari config/env (Bos bisa ganti tanpa ubah kode). JANGAN hardcode nomor rekening di kode.
- Tidak ada migration baru tanpa izin; pakai tabel yang ada (membership_plans, company_memberships) + bila perlu 1 tabel `payment_requests` (minta izin Bos dulu bila butuh migration).

## Scope (file disjoint, lane tunggal karena menyentuh alur bayar)
- `app/Livewire/Paywall.php` (atau halaman paket baru) — daftar paket + pilih
- `resources/views/livewire/paywall.blade.php` (atau `choose-plan.blade.php`)
- `app/Models/PaymentRequest.php` + migration (PERLU IZIN Bos bila baru)
- `app/Http/Controllers/Admin/**` atau Livewire admin untuk konfirmasi pembayaran (aktivasi paket)
- `config/billing.php` — tambah `manual_payment` (bank, account_number, account_name, qris_image_path) dari env
- `tests/Feature/**` — negative test: klien tidak bisa mengaktifkan paket sendiri; paket baru aktif setelah admin konfirmasi

## Alur
1. Klien di paywall/penggunaan → klik "Pilih paket" → lihat daftar paket + harga.
2. Pilih paket → buat `payment_requests` (status `pending`) → tampil instruksi: nomor rekening + nominal + QRIS statis + kode unik.
3. Klien transfer/scan QRIS sesuai nominal.
4. Bos (admin) melihat daftar `payment_requests` pending → konfirmasi → `company_memberships` dibuat/diupdate jadi `active` dengan kuota paket.
5. Klien otomatis bisa pakai lagi (kuota paket berlaku).

## Negative test wajib
- Klien tidak bisa memaksa status `active` (fail-closed).
- Konfirmasi admin pada payment_request yang salah/duplikat tidak mengkredit dua kali (idempoten).
- Non-owner tidak bisa membuat payment_request untuk company orang lain.

## Verifikasi
- `DATA_SOURCE=json php artisan test` (full), Pint, build, diff-check.
- Smoke: alur pilih paket → instruksi bayar tampil dengan data dari config.

## Pertanyaan untuk QA
- Apakah migration `payment_requests` boleh dibuat, atau harus pakai tabel yang sudah ada?
- Apakah konfirmasi pembayaran cukup di admin area, atau perlu notifikasi ke klien?
- Apakah QRIS statis cukup sebagai gambar dari config, atau perlu upload via admin?
