# Prompt Maraton Fase 6b — Add-on Komersial (untuk claude-cli)

Salin blok di bawah ini persis ke claude-cli.

---

Kerjakan seluruh katalog add-on Fase 6b (D-56) secara maraton, SATU per SATU,
serial (bukan paralel) — setiap add-on adalah task migration + fitur baru,
dan HERMES.md mewajibkan migration selalu serial.

## Prinsip wajib untuk SEMUA add-on (non-negotiable)

1. **Config-driven, bukan hardcode.** Setiap add-on WAJIB bisa
   nyala/mati per company lewat mekanisme kapabilitas yang sudah ada:
   `ModuleSetting` (module_name='features') + `PlanCapabilityGate`/
   `FeatureResolver` (`app/Services/FeatureResolver.php`), persis pola
   `addon.branches` yang sudah dipakai di T-28
   (app/Services/CompanyGroup/GroupReportService.php,
   app/Http/Controllers/App/GroupReportController.php). TIDAK BOLEH ada
   `if (config('app.enable_efaktur'))` di file .env-level atau flag statis
   di kode — semua company yang tidak mengaktifkan add-on tersebut
   (`addon.xxx` = false/tidak ada di module_settings) harus mendapat
   404/403/hidden UI secara otomatis lewat gate yang sama, TANPA deploy
   ulang atau ubah kode.
2. **D-31 tetap berlaku**: nama industri TIDAK BOLEH muncul di flag,
   tabel, model, komponen, atau kondisi apa pun. Add-on adalah kapabilitas
   generik yang BISA dipakai industri manapun yang membutuhkannya (mis.
   e-Faktur bukan hanya untuk "toko retail", tapi untuk company manapun
   berstatus PKP).
3. **`company_id` wajib di setiap tabel bisnis baru (D-26).**
4. Setiap add-on baru = 1 key kapabilitas baru didaftarkan di
   `FeatureResolver` (lihat `SENSITIVE_CAPABILITIES` jika datanya
   sensitif — data pajak/payroll SANGAT SENSITIF, wajib masuk kategori
   ini + `AiDataSharingPolicy` withheld default).
5. Uang/saldo/token/otorisasi: fail-closed + negative test WAJIB sebelum
   fitur dianggap selesai (per HERMES.md).

## Urutan maraton (serial, migration/dependency change tidak boleh paralel)

Kerjakan dalam urutan ini. Setiap selesai satu add-on: commit lokal
bertema (bukan `git add -A`), jalankan full `php artisan test` +
`vendor/bin/pint --test` + `npm run build` (jika ada perubahan Blade/CSS/JS),
lalu update `docs/AUTOPILOT_STATUS.md` dengan SHA commit ASLI (jalankan
`git rev-parse --short HEAD` dan salin angka sebenarnya — placeholder
mentah dilarang keras, ini sudah 2x jadi insiden sebelumnya).

1. **T-29 — Nomor WA disediakan platform (kapabilitas `addon.platform_wa_number`)**
   Paling sederhana secara skema (tidak ada tabel bisnis baru, hanya
   kolom + gate) — cocok jadi pemanasan pola sebelum yang lebih besar.
   - Tambah kolom pada `hermes_profiles` atau tabel provisioning WA yang
     relevan untuk menandai nomor disediakan platform vs BYO (bring-your-
     own-number).
   - Gate: hanya company dengan `addon.platform_wa_number=true` (dan plan
     yang mengizinkan) bisa memilih opsi ini di UI settings.
   - Test: company tanpa kapabilitas ini tidak bisa provisioning nomor
     platform (403/hidden); company dengan kapabilitas bisa.

2. **T-30 — Payroll lanjutan: BPJS/PPh21 (kapabilitas `addon.payroll_advanced`)**
   - Perluas modul payroll yang sudah ada (`app/Models/Payroll` dkk, cek
     T-13f) dengan perhitungan komponen BPJS Kesehatan/Ketenagakerjaan +
     PPh21 sebagai data KONFIGURASI (tarif/persentase disimpan sebagai
     data, BUKAN hardcode angka di kode — tarif bisa berubah per tahun
     pajak, simpan di tabel/config bertanggal-efektif).
   - Gate: company tanpa `addon.payroll_advanced` tetap dapat payroll
     dasar (gaji pokok) seperti sebelumnya, tanpa komponen BPJS/PPh21.
   - Data ini SENSITIF (gaji, NIK jika ada) — WAJIB masuk
     `SENSITIVE_CAPABILITIES` di `FeatureResolver` + `AiDataSharingPolicy`
     default withheld, ikuti pola persis `pharmacy.prescription` yang
     sudah ada (lihat `tests/Feature/Ai/AiContextControllerTest.php`).
   - Test: perhitungan BPJS/PPh21 benar untuk minimal 2 skenario gaji;
     company tanpa addon tidak melihat komponen ini; data tidak bocor ke
     AI tanpa opt-in eksplisit owner (negative test wajib, pola sama
     seperti `pharmacy.prescription`).

3. **T-31 — Domain & struk ber-merek sendiri (kapabilitas `addon.custom_domain`)**
   - Field `custom_domain` per company + validasi ownership domain
     (minimal: field tersimpan, ditampilkan di dokumen/struk jika di-set;
     verifikasi DNS penuh boleh manual/luar scope kalau butuh biaya
     eksternal — LAPORKAN sebagai kebutuhan approval Bos jika perlu
     provider berbayar, JANGAN diam-diam beli domain/DNS service).
   - Gate: hanya tampil di UI settings company yang punya
     `addon.custom_domain=true`.

4. **T-32 — e-Faktur/Coretax (kapabilitas `addon.efaktur`)**
   - Ini INTEGRASI EKSTERNAL BERBAYAR (API resmi DJP/Coretax butuh
     kredensial perusahaan sungguhan). Kerjakan HANYA lapisan lokal:
     model data faktur pajak (nomor seri, NPWP lawan transaksi, PPN),
     status "belum dikirim/terkirim/gagal", DAN interface/contract kelas
     untuk pengiriman (mis. `EFakturGatewayContract`) dengan implementasi
     dummy/stub untuk test. JANGAN benar-benar memanggil API eksternal
     nyata atau menyimpan kredensial asli — itu perlu approval eksplisit
     Bos (biaya + secret, di luar wewenang otomatis).
   - Gate: `addon.efaktur`, hanya company berstatus PKP yang bisa nyala.
   - Test: model + gate + kontrak stub, TANPA panggilan jaringan nyata.

5. **T-33 — Integrasi marketplace/ojol (kapabilitas `addon.marketplace_sync`)**
   - Sama seperti e-Faktur: bangun kontrak webhook/adapter generik
     (`MarketplaceOrderAdapterContract`) mengikuti pola
     `NalarPesanWebhookController` yang SUDAH ADA (T-19b) — JANGAN
     hardcode nama marketplace/ojol tertentu di kode (itu D-31 juga
     berlaku ke integrasi eksternal: platform marketplace = data
     konfigurasi per company, bukan nama di flag/model).
   - Gate: `addon.marketplace_sync`.
   - Test: adapter kontrak + stub, order masuk dari sumber X ter-map ke
     `orders` company yang benar, order dari company lain tidak bocor.

6. **T-34 — Storage terkelola (kapabilitas `addon.managed_storage`)**
   - Perluas mekanisme upload/attachment (`app/Models/Attachment`, T-14)
     agar company dengan `addon.managed_storage=true` memakai disk
     terkelola (kuota lebih besar / non-BYOS) vs default. Implementasi
     disk switching via Laravel filesystem config per company (bukan
     hardcode nama provider storage tertentu).
   - Gate: `addon.managed_storage`.
   - Test: company tanpa addon tetap pakai disk default/BYOS; company
     dengan addon dapat kuota/disk berbeda; kuota dilanggar → ditolak
     (negative test).

7. **T-35 — Loyalty pelanggan (poin/voucher) — BLOCKED sampai keputusan D-32**
   JANGAN DIKERJAKAN DULU. Ini kapabilitas generik BARU (bukan modifikasi
   modul yang sudah ada), dan HERMES.md eksplisit: kapabilitas baru butuh
   keputusan pemilik (D-32). STOP di sini, tulis di
   `docs/AUTOPILOT_STATUS.md` sebagai BLOCKED dengan pertanyaan konkret:
   "Loyalty butuh tabel baru (loyalty_points/loyalty_vouchers) +
   kapabilitas generik `addon.loyalty` — apakah Bos mengesahkan ini
   sebagai kapabilitas resmi generik (D-32), dan struktur poin seperti
   apa (per-transaksi nominal? per-item? expiry policy)?" — JANGAN
   menebak desainnya sendiri.

## Verifikasi WAJIB per add-on (bukan di akhir maraton saja)

Setelah SETIAP add-on (T-29 s/d T-34), sebelum lanjut ke add-on berikutnya:
```
php artisan test
vendor/bin/pint --test
npm run build   # jika ada perubahan Blade/CSS/JS
```
Tempel output ASLI (bukan ringkasan/klaim) di laporan commit. Commit lokal
per file/tema. Update `docs/AUTOPILOT_STATUS.md` dengan SHA nyata
(`git rev-parse --short HEAD`) — JANGAN placeholder teks.

Setelah T-29 s/d T-34 selesai dan T-35 dilaporkan BLOCKED, STOP dan lapor
ke Bos untuk keputusan D-32 (loyalty) sebelum lanjut lagi. Push, deploy,
kredensial API eksternal, biaya layanan berbayar (domain/storage
provider/API pajak) TETAP butuh izin eksplisit Bos — jangan mengasumsikan
persetujuan dari prompt ini.
