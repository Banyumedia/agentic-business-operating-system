# Laporan Worker — Pasca-Fase 9: Penutupan Sisa Temuan

Status: **6 dari 10 item selesai**, commit lokal (belum push).
Empat sisanya bukan pekerjaan yang tertunda, melainkan hal yang hanya bisa
dibuka Bos atau memuat keputusan pihak ketiga.

## Ringkasan

| # | Item | Hasil |
|---|---|---|
| 1 | Unique index `account_code` + `journal_number` | `DONE` `7ee7402` — plus dua temuan tambahan |
| 2 | `accounting_journal_lines.schema.json` | `DONE` `860db02` — plus layar Rincian Jurnal |
| 3 | Fixture demo akuntansi & payroll | `DONE` — 4 tenant demo, jurnal seimbang, diuji |
| 4 | Penyalaan `finance.accounting` + `hr.payroll` | `DONE` `86505b0` — 35 dan 31 preset |
| 5 | Pelanggaran Pint pre-existing | `DONE` `c3f44a2` — Pint bersih 516 berkas |
| 6 | Review `b0ef5b0` | `DONE` — **5 cacat ditemukan dan ditutup** |
| 7 | Paritas MySQL (T-21b) | `DONE` `5595738` — diverifikasi ulang di MySQL 8.4.3 |
| 8 | Kredensial Hermes produksi | `HUMAN:SECRET` — butuh Bos |
| 9 | Remote git / push | butuh Bos |
| 10 | Kirim tagihan ke pelanggan (UR-05) | keputusan bisnis, bukan task |

## Penjaga umum, bukan tambalan

Item #1 diminta sebagai "tambahkan dua unique index". Yang dikerjakan adalah
penjaga yang menurunkan pemeriksaannya dari katalog schema
(`SchemaMigrationParityTest`), karena dua index yang hilang itu **gejala**, bukan
penyakit: tidak ada apa pun yang memaksa deklarasi `unique` di schema menjadi
index nyata di basis data.

Penjaga itu langsung menemukan dua hal lain yang tidak ada di daftar:

**`approval_tickets.operation_id` tidak punya kolom.** Schema menyatakannya
sebagai kolom tingkat atas dengan unique ter-scope company, dan jalur JSON memang
menyimpannya begitu — tetapi jalur Eloquent menyimpannya **di dalam** `payload`.
Idempotensi permintaan approval hanya dijaga `Company::lockForUpdate()`. Benar,
tapi satu lapis, dan tidak sesuai janji schema. Kolomnya kini ada, dibackfill
dari payload, dan index unique menjadi lapis kedua. Nilai tetap ditulis ke
payload supaya pembaca lama tidak kehilangan apa pun.

**Dua schema ditolak loader tanpa ada yang tahu.** `production_orders` dan
`production_order_lines` tidak punya bagian `attributes`, sehingga
`EntitySchema::load()` menolaknya. Tidak pernah terdeteksi karena
`SchemaValidatorTest` memakai daftar entitas yang **ditulis tangan** — schema yang
lupa didaftarkan di sana tidak diperiksa sama sekali. Keduanya kini valid dan
terdaftar, dan ada test yang memuat seluruh katalog.

**Satu hal sengaja tidak diubah.** `invoices.order_id` unique **global** di
migration, sementara schema menyatakannya per company. Yang benar adalah
migration-nya: `order_id` adalah referensi order dari gateway pembayaran, dan
unique global itulah yang mencegah webhook satu usaha mengkreditkan pembayaran
usaha lain. Penjaga paritas karena itu menerima unique atas **bagian** dari kunci
yang diminta — unique yang lebih ketat sudah menjamin yang lebih longgar.

## Aturan penyalaan kapabilitas (item #4)

Ini satu-satunya item yang butuh keputusan, dan keputusannya dibuat tanpa
memasukkan penilaian industri:

- `hr.payroll` menyala di setiap preset yang sudah punya `hr.employees` — 31
  preset. Payroll adalah kelanjutan wajar dari daftar karyawan.
- `finance.accounting` menyala di setiap preset yang sudah punya
  `finance.cashbook` — 35 preset. Akuntansi adalah lapisan formal di atas buku kas.

Yang membatasi akses tetap **gerbang paket D-52**: `hr.payroll` di Pro+Enterprise,
`finance.accounting` di Enterprise, keduanya sudah terdaftar di `BosSeedPlans`.

Alasan memilih aturan ini: memutuskan di lapisan preset bahwa sebuah jenis usaha
tidak akan pernah butuh buku besar adalah penilaian bisnis yang dikodekan per
industri — persis yang dilarang D-31. Paket adalah tempat yang benar untuk
membatasi, karena di situlah keputusan komersialnya hidup.

`PresetCapabilityReachTest` mengunci aturan itu **dua arah**: prasyarat tanpa
kapabilitas, dan kapabilitas tanpa prasyarat (menu yang datanya tak bersumber).

## Review `b0ef5b0`: lima cacat

Commit WIP writer lain disimpan apa adanya atas instruksi Bos dan belum pernah
direview. Semua temuan terlihat pengguna.

1. **Navigasi bawah ponsel menawarkan modul yang tidak dimiliki usaha.** Tab
   Kasir (`/app/pos`) dan Buku Kas (`/app/accounting`) ditanam di layout tanpa
   memeriksa kapabilitas. Preset `klinik` tidak punya `pos` — klinik yang membuka
   aplikasi dari ponsel melihat tab Kasir dan mendapat **403**. Isinya kini
   diturunkan dari `DynamicMenuRegistry` lewat `MobileQuickNav`, sumber yang sama
   dengan sidebar.
2. **Nomor WhatsApp contoh `6281234567890` di tiga tombol ajakan utama halaman
   publik.** Halamannya terlihat normal, jadi setiap klik calon pelanggan
   mengarah ke nomor milik orang lain tanpa ada yang tahu. Nomor kini dari
   `config('app.sales_whatsapp')`; tanpa nilai, ajakan mengarah ke pendaftaran.
3. **Quick action dashboard punya cabang mati.** Kandidat "order baru" memeriksa
   kapabilitas `orders` yang tidak ada di `FeatureResolver::CAPABILITIES`, dan
   menunjuk `/app/orders` yang bukan rute terdaftar.
4. **Kredensial di dalam repo.** `scripts/smoke_settings.py` menuliskan email
   pilot beserta password apa adanya, menyasar port 8010 — port server produksi
   di mesin ini. Kredensial kini wajib dari environment, tanpa nilai bawaan.
5. **Duplikat test.** `tests/Feature/Livewire/Public/IndustryListTest.php`
   himpunan bagian dari `tests/Feature/Public/IndustryListTest.php`.

Sekalian ditutup: `DashboardComposer` memakai `InvalidArgumentException` tanpa
meng-import-nya, di dua cabang fail-closed yang tidak pernah diuji. Data runtime
yang menyimpang dari preset akan memunculkan "kelas tidak ditemukan" alih-alih
pesan fail-closed — tepat di jalur yang seharusnya menjelaskan masalah.

**Catatan positif:** pin `DATA_SOURCE=json` di `phpunit.xml` membuat suite
deterministik tanpa menyetel environment lebih dulu. Diverifikasi dengan
menjalankan suite tanpa variabel itu.

## Pola yang berulang tiga kali sesi ini

Tiga cacat berbeda lolos karena testnya memakai nilai atau konteks fabrikasi:

- T-57: test menulis `$owner->phone` pada model belum tersimpan, jadi ia
  membuktikan kolom yang tidak ada di skema.
- T-58: test grup selalu mengirim `$company = null`, jadi cabang yang melempar
  exception tidak pernah dieksekusi.
- Quick action: test hanya memakai preset yang menghasilkan tepat tiga tab, jadi
  cabang keempat yang mati tidak pernah tersentuh.

Pelajarannya sama: test yang tidak pernah menyentuh jalur nyata tidak
membuktikan apa pun tentang jalur nyata. Empat test baru sesi ini sengaja
ditulis sebagai penjaga lintas-katalog (`SchemaMigrationParityTest`,
`PresetCapabilityReachTest`, `MobileQuickNavTest`, `MysqlParityTest`) supaya ia
memeriksa kenyataan, bukan contoh yang dipilih sendiri.

## Paritas MySQL (T-21b, diverifikasi ulang)

T-21b sudah `DONE` sejak sebelum Fase 8/9, jadi `customer_invoices`,
`cash_entries`, tiga entitas akuntansi, unique index baru, dan
`approval_tickets.operation_id` **belum pernah** diuji di MySQL.

Diuji pada **MySQL 8.4.3**:

- Seluruh migration jalan di MySQL, bukan hanya SQLite.
- Kolom uang menyimpan sen secara utuh, termasuk `1234567890123.45` yang di
  SQLite kembali sebagai `1234567890123.40`.
- Unique ter-scope company yang ditambahkan lewat `Schema::table()` — bukan saat
  pembuatan tabel — benar-benar ditegakkan. Itu tempat perbedaan MySQL/SQLite
  paling mungkin muncul, dan yang dijaga adalah data finansial.

**Kesimpulan presisi:** batas sen yang tercatat sejak T-42 memang milik SQLite,
bukan schema. Dikarakterisasi eksplisit di test, bukan disembunyikan.

Koneksi `mysql_parity` dipisah dari `mysql` supaya pemeriksaan ini tidak pernah
menyentuh basis data aplikasi; basis data ujinya (`agentic_bos_parity`) dibuat
sendiri bila belum ada. Test melewati dirinya bila MySQL tidak tersedia, jadi
mesin dan CI tanpa MySQL tetap hijau. `migrate:fresh` dijalankan sekali per kelas
(16s, bukan 50s).

## Gate

```
DATA_SOURCE=json php artisan test   → 1.118 passed / 5.474 assertions, 0 gagal
vendor/bin/pint --test              → PASS 516 berkas (pertama kali bersih penuh)
php artisan migrate:fresh --seed --force → OK
npm run build                       → PASS
php artisan test --filter=MysqlParityTest → 4 passed (MySQL 8.4.3)
```

## Commit

| Item | Commit |
|---|---|
| Paritas unique + `operation_id` + dua schema | `7ee7402` |
| Schema rincian jurnal + menu | `860db02` |
| Penyalaan kapabilitas 40 preset | `86505b0` |
| Fixture demo akuntansi & payroll | (menyusul `86505b0`) |
| Pint bersih | `c3f44a2` |
| Lima perbaikan hasil review `b0ef5b0` | (setelah `c3f44a2`) |
| Paritas MySQL T-21b | `5595738` |

## Sisa risiko

- **Belum pernah dijalankan dari HP pada tenant produksi.** Ini yang terbesar:
  1.118 test hijau tidak pernah salah menekan tombol di layar sempit. Saran:
  satu putaran manual — terbitkan tagihan, catat pembayaran, cetak, undang satu
  staf, lalu japri botnya.
- Aktivasi Hermes produksi (T-49/T-51/T-58) menunggu `HUMAN:SECRET`.
- Remote git belum ada; seluruh pekerjaan masih commit lokal.
- Pengiriman tagihan langsung ke pelanggan (UR-05) menunggu keputusan Bos.
