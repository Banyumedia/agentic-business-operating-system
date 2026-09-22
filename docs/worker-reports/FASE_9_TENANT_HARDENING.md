# Laporan Worker — Fase 9: Pengerasan Sisi Tenant (T-48..T-58)

Status: **T-48..T-58 selesai**, commit lokal (belum push).
Sumber antrean: audit sisi tenant 2026-09-22 setelah Fase 8 mendarat.
Keputusan yang mengunci lingkup: **D-63, D-64, D-65, D-66**.

## Masalah yang ditutup

Fase 8 memberi tenant kemampuan menerbitkan tagihan, tetapi meninggalkan
rangkaian itu tanpa pengerasan. Sebelas temuan yang semuanya berbukti di kode:

- Jatuh tempo disimpan dan dikirim ke view tapi **tidak pernah dirender**, dan
  tidak ada konsep "terlambat" untuk piutang tenant sama sekali.
- **Tidak ada pemeriksaan peran di layar mana pun** kecuali transisi workflow.
  Siapa pun yang bisa membuka layar tagihan bisa menerbitkan tagihan dan
  mencatat pembayaran.
- Tab Tim & Akses masih stub "Segera", jadi usaha ber-staf menjalankan semuanya
  dari satu akun owner.
- Tagihan tidak bisa keluar dari sistem — pemilik masih mengetik ulang ke
  pelanggan.
- `finance.accounting` dan `hr.payroll` aktif di **0 dari 40 preset**, dan kalau
  dinyalakan pun layarnya menampilkan entity yang berbeda dari judulnya.
- Ekspor data hanya memuat empat file; data uang Fase 8 tidak ikut.
- SOP bawaan Karyawan AI **menjanjikan pengingat piutang** yang belum punya
  implementasi apa pun.
- Dua cacat produksi di lapisan WhatsApp (lihat bagian tersendiri di bawah).

## Yang berubah untuk pemilik usaha

| Kemampuan baru | Di mana | Batasan yang ditegakkan |
|---|---|---|
| Lihat tunggakan beserta umurnya, terurut terlama di atas | Keuangan → Tagihan | Lunas dan draf tidak pernah menunggak |
| Pengingat piutang otomatis lewat WhatsApp | scheduler `bos:remind-receivables` | Dikirim ke **pemilik usaha**, bukan pelanggan |
| Cetak / simpan PDF dokumen tagihan | `/app/invoices/{invoice}/print` | Draf ditolak; tagihan usaha lain 404 |
| Penawaran disetujui menjadi tagihan | Keuangan → Tagihan | Satu penawaran tidak bisa ditagih dua kali |
| Undang staf dengan peran, kuota per paket | Pengaturan → Tim & Akses | Kuota diperiksa sebelum undangan dibuat |
| Aksi uang jadi owner-only | layar tagihan & daftar | Ditegakkan server-side, bukan sembunyi tombol |
| Panel Kesehatan Usaha | Dashboard | Owner-only; analyzer gagal = dashboard tetap hidup |
| Bagan Akun, Jurnal, Payroll, Laporan | Akuntansi, HRD | Entitas benar; penyalaan preset ikut D-52 |
| Ekspor seluruh data usaha | Pengaturan → Data | Hanya baris company pemohon, owner-only |
| Bot mengenali siapa yang japri | WhatsApp | Japri staf **baca-saja** |

## Keputusan yang diambil di tengah jalan

**Pengingat piutang dikirim ke pemilik usaha, bukan ke pelanggan.** Mengirim
langsung ke pelanggan menyentuh persetujuan pihak ketiga dan reputasi nomor WA
tenant. Itu keputusan bisnis tersendiri, bukan efek samping sebuah task
pengingat.

**Peran tim tetap dua (`owner|staff`).** Peran per-modul ditolak karena
`PresetDefinitionValidator` (baris 209) mengunci kosakata
`['owner','staff','system']` dan 40 preset memakainya — memperluasnya sekelas
D-32. Izin yang lebih halus nanti lewat kolom izin per anggota, bukan peran baru.
Hasilnya: **nol sentuhan ke 40 preset**.

**Pola layar `accounting/journals` diganti `ledger` → `list`.** Rencana awal
salah: `LedgerScreen::amountField()` akan fallback ke `'id'` untuk entitas ini
dan **menjumlahkan id baris sebagai "saldo"**. Itu angka finansial palsu, bukan
sekadar tampilan yang kurang tepat.

**T-56 dikerjakan sebagai rebase, bukan merge.** Branch `task/bi-b` dibangun
pra-MQ-01 (`mount(DashboardComposer)`) sedangkan `main` sudah punya `loadError`/
`themeError`. Markup panel dipindah ke partial
`resources/views/livewire/dashboard/health-panel.blade.php` supaya rebase
berikutnya tidak bertabrakan lagi di satu berkas besar.

**`InvoiceCreatePartial` tidak dihapus.** Rencana Fase 8 menyebutnya kelas mati;
ternyata dipakai `Project::checkAndTriggerMilestones()` dan diuji
`ProjectWorkflowTest`. Hanya komentarnya yang dibereskan.

## Dua cacat produksi di lapisan WhatsApp

Keduanya lolos test lama **karena test memakai nilai fabrikasi**, bukan karena
aturannya benar. Pola ini yang perlu diingat, bukan dua barisnya.

1. **T-57 — owner tidak pernah bisa japri bot-nya sendiri.** Filter membaca
   `$profile->owner?->phone`; `User` tidak punya kolom `phone`, yang ada
   `wa_number` + `wa_is_verified` (migration `2026_09_17_222052`). Pembandingnya
   selalu kosong sehingga cabang fail-closed selalu menyala. Test
   `primary profile allows dm from owner` hijau karena menulis `$owner->phone`
   pada model **belum tersimpan** — Eloquent menerima atribut sembarang di
   memori, jadi test itu membuktikan kolom yang tidak ada di skema.

2. **T-58 — setiap pesan grup melempar exception.** Filter memanggil
   `$company->moduleSettings()`; relasinya bernama `settings()`. Setiap pesan
   grup dengan konteks company menghasilkan `BadMethodCallException`. Test lama
   **selalu** mengirim `$company = null`, jadi cabang itu tidak pernah dieksekusi.

Penjagaan yang dipasang: test negatif T-57 sengaja mengisi nomor **hanya** di
`phone`, sehingga implementasi yang kembali membacanya langsung merah. Test T-58
dipindah ke `tests/Feature/WhatsApp/` dengan `RefreshDatabase` supaya jalur
"bukan owner" benar-benar menyentuh basis data.

## Aturan identitas WA (D-66)

Tiga penjaga di `WhatsAppSenderIdentity`:

1. `wa_is_verified` wajib benar. Nomor cocok saja tidak cukup karena nomor WA
   berpindah tangan.
2. Keanggotaan `company_user` wajib aktif. Pencabutan keanggotaan menutup akses
   WA seketika tanpa menyentuh apa pun di sisi WA.
3. Satu nomor yang menjadi anggota di lebih dari satu company **ditolak**
   (fail-closed), bukan ditebak, sampai T-37 menghadirkan pemilih konteks.
   Menebak company berarti berisiko menjawab dengan data usaha yang salah.

Japri staf diizinkan tetapi **baca-saja**. Tidak ada tabel izin terpisah untuk
WA: aksi bot melewati pintu otorisasi yang sama dengan web, sehingga WA tidak
bisa menjadi jalan memutar aturan owner-only T-50. Aturan grup WA-04 tidak
dilonggarkan.

## Idempotensi pengingat

`customer_invoice_reminders` menyimpan satu baris per (tagihan, tahap). Kunci
itulah yang membuat replay scheduler tidak bisa mengirim dua kali — bukan
pemeriksaan waktu, yang akan gagal begitu jam mesin bergeser. Tahap dan batas
harian dibaca dari `config/receivables.php` (default H-3, H, H+3, H+7), jadi
mengubah kebijakan pengingat tidak menyentuh kode.

Pengiriman lewat `HermesNodeClient::sendWhatsAppMessage()` yang company-scoped
dan fail-closed (D-63). Dilarang memakai `DunningLadder`/`BillingCheckExpiring`:
itu jalur dunning tagihan langganan platform (D-23).

## Temuan di luar lingkup, sengaja tidak diperbaiki

Empat temuan dari T-54, masing-masing layak task sendiri:

1. `unique` di schema `chart_of_accounts.account_code` dan
   `accounting_journals.journal_number` **belum punya unique index di
   migration**. Jalur Eloquent/MySQL masih menerima duplikat. Ini gap data
   finansial dan yang paling berisiko dari keempatnya.
2. `accounting_journal_lines` belum punya schema JSON, jadi debit/kredit tidak
   terlihat oleh layar generik.
3. Belum ada fixture demo untuk tiga entitas baru — layar Bagan Akun, Jurnal,
   dan Payroll kosong di tenant demo.
4. Penyalaan `finance.accounting` dan `hr.payroll` di preset belum dilakukan.
   Itu memang milik gerbang paket D-52, bukan T-54.

## Catatan teknis yang berguna nanti

- **`max_users` `NOT NULL` tanpa default** membuat tiga jalur provisioning gagal
  (`TrialProvisioner`, `InvoiceCreationService`, `InvoiceConfirmationService`).
  Ditutup dengan `$plan->max_users ?? 1`. Kolom wajib pada tabel yang sudah
  dipakai jalur provisioning selalu perlu jejak balik seperti ini.
- **Kuota diperiksa sebelum undangan dibuat**, bukan saat penerimaan. Kalau
  tidak, kuota bisa terlampaui oleh undangan yang sudah beredar.
- **Helper bernama `run()` di kelas test tidak boleh.**
  `PHPUnit\Framework\TestCase::run()` final — dinamai `runAt()`.
- `storage/app/json/1/workflow_log.json` bertambah setiap kali suite dijalankan.
  Artefak test, bukan perubahan kode; dikembalikan sebelum commit.
- Suite **wajib** dijalankan dengan `DATA_SOURCE=json`.

## Gate

```
DATA_SOURCE=json php artisan test   → 1.082 passed / 5.128 assertions, 0 gagal
php artisan migrate:fresh --seed --force → OK
vendor/bin/pint (file T-49 + T-58)  → PASS
npm run build                       → PASS
```

Pint masih menyisakan satu pelanggaran pre-existing
`tests/Feature/LobbyNavigationTest.php` (`class_attributes_separation`) milik
writer lain. Dicatat, tidak disentuh.

## Commit

| Task | Commit |
|---|---|
| Antrean Fase 9 | `c0afe59` |
| T-48, T-50, T-52, T-53, T-55 | `39aafb3` + `b6b9ee2` |
| WIP writer lain (bukan sesi ini) | `b0ef5b0` |
| T-56 | `3524598` + `8a6433f` |
| D-63 + D-64 | `d425c38` |
| D-65 + D-66, antrean T-57/T-58 | `0a5bb00` |
| T-57 | `5967ca1` |
| T-51 | `b6bce50` |
| T-54 (sub-agent) | `ed84b7f` + `5849035` |
| T-49 | `7986662` |
| T-58 | `2ba77c7` |

## Sisa risiko

- Seluruh rangkaian **belum pernah dijalankan dari HP pada tenant produksi**.
- Aktivasi Hermes produksi untuk T-49, T-51 (undangan WA), dan T-58 menunggu
  `HUMAN:SECRET`. Sampai itu ada, ketiganya hanya terbukti lewat
  `FakeHermesNodeClient` — belum ada satu pesan pun yang terkirim dari tenant
  nyata.
- Empat temuan T-54 masih terbuka.
- `b0ef5b0` belum direview.
