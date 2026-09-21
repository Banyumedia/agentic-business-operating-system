# Laporan Worker — Fase 8: Tagihan Pelanggan & Profitabilitas Proyek (D-62)

Status: **T-41..T-47 selesai**, commit lokal (belum push).

## Masalah yang ditutup

Tenant tidak bisa menerbitkan tagihan ke pelanggannya. Entity `invoices` adalah
tagihan langganan platform (D-23), bukan piutang tenant, sehingga menu
"Keuangan → Tagihan" sempat terjangkau di 29 preset tapi hanya menampilkan
kartu kontrak developer. Sembilan preset jasa tanpa POS tidak punya tempat
menagih sama sekali. Dan `LedgerScreen` baca-saja, jadi pengeluaran pun tidak
bisa diinput.

## Hasil akhir yang bisa dipakai pemilik usaha

| Jalur | Menu | Terjangkau di |
|---|---|---|
| Catat penerimaan & pengeluaran, bebankan ke proyek | Keuangan → Entri Kas | semua preset dengan `finance.cashbook` |
| Terbitkan tagihan berbaris ke pelanggan | Keuangan → Tagihan | **29 preset** |
| Kelola termin proyek | Proyek → Termin & Opname | 3 preset |
| Laba-rugi proyek basis kas | Proyek → Laba-Rugi | **10 preset** |

Delapan preset mendapat rangkaian penuh tagihan + laba-rugi: `agency`,
`bengkel`, `contractor`, `desain_interior`, `fotografi`, `it_support`,
`kantor_hukum`, `mebel_custom`.

## Aliran uang yang dibangun

Kunci desainnya: **laba-rugi hanya membaca `cash_entries`**. Tagihan adalah
dokumen penagihan; uang diakui saat pembayaran dicatat, dan pencatatan itu
menulis satu baris buku kas. "Basis kas" karena itu bukan label, tapi
konsekuensi struktural — mustahil laba muncul dari uang yang belum masuk.

```
termin (project_milestones)
   └─ terbitkan → customer_invoices (+ customer_invoice_lines)
                     └─ catat pembayaran → cash_entries (in, project_id)
                                              └─ Laba-Rugi Proyek (basis kas)
pengeluaran → cash_entries (out, project_id) ──┘
```

Piutang berjalan (terbit − terbayar) tampil di layar tagihan dan di kolom
tersendiri pada laba-rugi, **di luar** perhitungan laba.

## Per task

**T-41 jalur input Buku Kas** (`2cc6c96`). Submenu `accounting/entries` memakai
`ListScreen` generik. Hambatan: `SchemaPresenter::columns()` membuang semua
foreign key, jadi `project_id` tidak pernah muncul di form. Diperbaiki
schema-driven: referensi bisa ditandai `"assignable": true` di schema dan
`SchemaPresenter::relations()` mengangkatnya jadi field bertipe `relation`;
labelnya lewat `term()`. Kolom sistem (`journal_id`, `created_by_user_id`)
tetap tersembunyi. 9 test.

**T-42 entitas tagihan** (`e663b2c`, merged `20464ed`, worktree `task/T-42`).
`customer_invoices` + `customer_invoice_lines`, `company_id` wajib, migration
portabel, baris cascade. Meniru `quotations`/`quotation_lines`. 15 test negatif
lebih dulu. Temuan presisi SQLite dicatat di bawah.

**T-43 layar tagihan.** `ContractScreen` + view. Item `accounting/invoices`
di-repoint ke `customer_invoices` dan navigasinya dinyalakan; label tetap
`term('invoices')`. Nilai selalu dihitung ulang dari baris lewat
`TaxRateService` dengan profil `BusinessIdentityStore` — total dari klien tidak
pernah dipercaya. Penomoran `INV-YYYYMM-NNNN` berjalan per company. Draf bisa
diubah; begitu diterbitkan, nilainya terkunci.

**T-44 pencatatan pembayaran.** Menulis `cash_entries` (`direction = in`,
`project_id` dari tagihan, `source_type = 'customer_invoice'`, `source_id`).
`paid_amount` **tidak ditambahkan** melainkan dihitung ulang dari jumlah entri
kas yang menunjuk tagihan itu — angka lunas karena itu tidak bisa menyimpang
dari uang yang tercatat. Overpayment ditolak, draf tidak bisa dibayar, tagihan
lunas tidak bisa dibayar lagi, klik kedua tidak menggandakan.

**T-45 termin bertahap.** `project_milestones.invoice_id` → `customer_invoice_id`
dengan referensi ke `customer_invoices`. Kolomnya ternyata **tidak punya foreign
key** ("No FK yet, invoices table is T-12"), jadi migration cukup
`renameColumn` — tanpa rebuild tabel SQLite. `projects/billing` jadi `list` atas
`project_milestones`. Di layar tagihan ada pilihan "Tagih dari termin" yang
memuat nilai termin dari basis data; termin yang sudah tertaut hilang dari
pilihan dan ditolak bila dipaksa.

**T-46 laba-rugi basis kas.** `MarginScreen` + view. Pendapatan = `in` per
`project_id`, biaya = `out`, laba = selisih. Layar menyebut "basis kas" eksplisit
dan memuat peringatan bahwa tagihan belum dibayar tidak dihitung. Entri tanpa
proyek tidak dibuang diam-diam: dilaporkan terpisah sebagai belum terbebani.
11 test, termasuk test inti bahwa tagihan terbit-belum-dibayar tidak menaikkan
pendapatan.

**T-47 rangkai & dokumentasi.** Fixture demo `project_milestones.json` untuk
tiga company ikut di-rename mengikuti schema baru (tertangkap
`JsonDataSourceTest`, bukan oleh review manual).

## Keputusan dan temuan yang perlu diketahui

1. **`InvoiceCreatePartial` tidak dihapus.** Rencana awal menyebutnya kelas
   mati; ternyata dipakai `Project::checkAndTriggerMilestones()` dan diuji
   `ProjectWorkflowTest`. Yang dibereskan komentarnya: ia menandai termin
   sebagai sudah ditagih, tidak membuat dokumen, dan tidak menyentuh `invoices`.
2. **Presisi uang di SQLite.** Kolom decimal disimpan sebagai REAL, jadi di atas
   ~13 digit signifikan sen hilang ke mantissa float: `1234567890123.45` kembali
   sebagai `1234567890123.40`, dan batas kolom `9999999999999999.99` kembali
   sebagai `10000000000000000.00`. Itu batas SQLite dev/test, bukan batas schema;
   kapasitas kolom diuji di tingkat validator. Paritas MySQL tetap urusan T-21b.
3. **Jebakan setup worktree.** `vendor` sebagai junction tidak bisa dipakai —
   autoloader Composer memetakan `App\`/`Tests\` ke proyek induk sehingga
   `database_path()` menunjuk ke luar worktree. Harus `composer install` sendiri.
   `public/build` juga wajib disalin: tanpa manifest Vite, 61 test view gagal 500.
4. **Pesan validasi lebih spesifik dari rencana.** Baris kosong menghasilkan
   "Setiap rincian wajib punya keterangan." alih-alih pesan umum; test
   disesuaikan ke perilaku yang lebih informatif itu, bukan sebaliknya.

## Di luar lingkup (sengaja)

Jurnal akuntansi untuk tagihan (`finance.accounting` aktif di 0 preset),
laba-rugi akrual, pembebanan biaya bahan/jam kerja ke proyek, cetak/PDF
tagihan, dan pengingat jatuh tempo otomatis. Semua bisa menyusul tanpa
membongkar yang ini.

## Verifikasi

```
DATA_SOURCE=json php artisan test
→ 993 passed, 4809 assertions

php artisan migrate:fresh --seed  → OK
vendor/bin/pint (file Fase 8)     → PASS
npm run build                     → PASS
```

Pint masih menyisakan satu pelanggaran pre-existing di
`tests/Feature/LobbyNavigationTest.php` milik writer lain — dicatat, tidak
disentuh (§0.2).

## Sisa risiko

- Seluruh rangkaian belum pernah dijalankan dari HP pada tenant produksi;
  buktinya masih level test + render Livewire.
- Belum ada cetak/kirim tagihan ke pelanggan, jadi pengiriman dokumen masih di
  luar sistem.
