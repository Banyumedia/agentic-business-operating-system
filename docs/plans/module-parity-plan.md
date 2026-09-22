# MP — Paritas Modul Klien terhadap Mockup UI/UX

Status: **usulan antrean**, belum ada task yang di-claim. Konvensi mengikuti
`docs/plans/module-quality-dashboard.md` (lajur MQ) — satu modul, satu lajur,
satu worktree.

Sumber banding: worktree `D:\PROJECTS\agentic-bos-mockup`
(`mockup/ux-dummy` @ `b61a1d1`), 22 layar di
`resources/views/livewire/mockup/`. Mockup adalah **referensi visual**, bukan
kode yang disalin (`docs/HANDOFF-OPENCODE.md`).

## 0. Yang TIDAK boleh diambil dari mockup

Ini alat peraga demo, bukan fitur produk:

- Toggle PKP/Non-PKP dan toggle tax-inclusive di `order-detail`, `pos-cashier`,
  `receipt`, `quotation-builder`. Mode pajak adalah setelan usaha (D-03/D-44),
  bukan tombol di layar transaksi.
- `preset-switch.blade.php` — pemilih preset runtime. Preset dipilih saat
  onboarding, bukan ditukar dari layar.
- Kartu "Preferensi Mode Gelap/Terang Personal" di `settings` — D-43
  **sudah menyatakan kartu ini salah**; tema per usaha, bukan per orang.
- Pola data per-layar milik mockup (`$t[...]`, `$data[...]` hardcode per
  screen) bertentangan dengan D-42/D-31.

`states.blade.php` dan `search.blade.php` bukan modul; keduanya katalog pola
(empty/error/lifecycle state, hasil pencarian) yang dipakai sebagai checklist
lintas lajur.

## 1. Temuan utama: dua implementasi yang tidak bertemu

Bukti: `OrderService`, `StockService`, `BookingService`, `RetentionService`,
`PrescriptionGuard`, `LoyaltyPointsCalculator` **nol pemanggil** di `app/` dan
`routes/` — hanya dipanggil dari `tests/`. Layar generik menulis langsung lewat
`EntityRepository`.

Akibat yang terukur, bukan kosmetik:

1. `CashierScreen::checkout()` (`app/Livewire/Screens/CashierScreen.php:196`)
   menyimpan `orders` + `order_lines` lewat `saveAggregate()`. Ia **tidak**
   memanggil `StockService` → penjualan tidak mengurangi stok, tidak menulis
   `stock_movements`. Dan tidak memanggil `JournalService` → penjualan tidak
   memposting jurnal, padahal `finance.accounting` dijual (D-64).
2. `RetentionService` punya `computeAndHold()` + `ensureCanBeInvoiced()` dan
   test hijau, tapi menu `projects/retention` cuma `list` generik atas
   `project_milestones` — tidak ada jalur pencairan retensi dari UI.
3. `BookingService` tidak pernah dipanggil; `CalendarScreen` hanya membaca.

## 2. Matriks paritas per modul

Kolom "real" = hasil inventaris `wire:*` pada `resources/views/livewire/screens/*`
dan `app/Livewire/Screens/*`.

| Modul / layar mockup | Real sekarang | Celah berbukti |
|---|---|---|
| `order-detail`, `project-detail`, `contact-detail` | **tidak ada pola `detail`** | `list.blade.php` tidak punya satu pun `href` → tidak ada drill-down ke mana pun. Tab ringkasan/linimasa/lampiran, log perubahan tahap, dokumentasi foto: belum ada tempat. |
| `pos-cashier` | `cashier` (add/qty/remove/checkout/clearCart, konfirmasi D-45) | tanpa pencarian produk, tanpa filter kategori, checkout tidak menyentuh stok maupun jurnal (§1) |
| `receipt` (struk 58mm + faktur A4 + kirim WA) | hanya cetak tagihan pelanggan (`/app/invoices/{id}/print`, T-52) | tidak ada struk POS sama sekali |
| `quotation-builder` (baris RAB, kirim WA, ubah jadi proyek) | `projects/quotations` = `list` generik | tidak ada builder baris, tidak ada konversi penawaran→proyek, tidak ada kirim WA |
| `retention-preview` | `projects/retention` = `list` generik | tidak ada pencairan retensi (D-45 tingkat 1) meski servisnya ada |
| `inventory-stock` (penyesuaian stok + filter) | `inventory` = `list` generik | tidak ada penyesuaian stok fisik lewat `StockService`, tidak ada filter status stok |
| `reports` (pilih periode + 3 grafik) | `report` — **nol `wire:*`** | tidak bisa memilih periode, tidak ada komposisi kas / peringkat item |
| `calendar` (klik slot → buat janji) | `calendar` (ganti tampilan + geser tanggal) | tidak bisa membuat booking dari kalender |
| board (`checkin`, `tables`) | `board` — **nol `wire:*`** | papan hanya tampil; check-in/deposit tidak bisa dijalankan |
| `fin-invoicing` | `contract` (paling matang: terbit, bayar, jatuh tempo, cetak) | filter status + aksi pengingat WA per tagihan belum ada di layar |
| ledger / `margin` | **nol `wire:*`** | tanpa filter periode; Buku Kas tidak punya penyaring apa pun |
| `pipeline`, `project-pipeline` | `pipeline` (transisi sah + catatan) | belum diverifikasi: tampilan transisi yang butuh persetujuan (`ApprovalRequest`), dan filter tahap untuk layar sempit |
| `settings-terms` | tab Pengaturan: profile, theme, features, assistant, usage, team, export, erasure | tidak ada tab kustomisasi istilah / pratinjau alur |
| `states` | — | empty/error state perlu diaudit per layar (board, ledger, report, margin paling telanjang) |

## 3. Bisa paralel? Bisa, dengan satu commit persiapan lebih dulu

Uji enam butir HERMES terhadap lajur di bawah: tidak ada dependency baru, tidak
ada migration (kecuali dicatat), tiap lajur punya worktree + branch sendiri.
Yang **tidak** lolos otomatis adalah butir 5 (file target disjoint), karena tiga
berkas dipakai hampir semua lajur:

- `app/Services/DynamicMenuRegistry.php` (katalog menu)
- `routes/web.php`
- `resources/views/livewire/screens/list.blade.php`

### MP-00 — commit persiapan, SERIAL di `main`

Satu commit yang memasang seluruh sambungan: item menu baru, route dokumen,
dan tautan baris daftar → layar detail. Setelah ini mendarat, lajur MP-01..MP-12
tidak lagi bersinggungan file.

### Lajur paralel

| Lajur | Isi | Target file utama | Catatan |
|---|---|---|---|
| MP-01 | pola layar `detail` (order/project/contact) | `app/Livewire/Screens/DetailScreen.php` + view + test | fondasi drill-down |
| MP-02 | integritas POS: stok + jurnal saat checkout | `CashierScreen`, `OrderService`, `StockService` | **uang & stok** → negative test lebih dulu, worktree sendiri |
| MP-03 | struk POS 58mm + faktur A4 | controller + view cetak baru | butuh seam route MP-00 |
| MP-04 | laporan: pilih periode + komposisi | `ReportScreen` + view | |
| MP-05 | board: check-in & deposit | `BoardScreen` + view | pakai effect deposit yang sudah ada |
| MP-06 | Buku Kas: filter + ringkasan | `LedgerScreen` + view | |
| MP-07 | buat booking dari kalender | `CalendarScreen` + `BookingService` | |
| MP-08 | builder penawaran + konversi ke proyek | layar baru | |
| MP-09 | pencairan retensi | layar baru + `RetentionService` | konfirmasi D-45 tingkat 1 |
| MP-10 | penyesuaian stok fisik | layar/aksi + `StockService` | |
| MP-11 | tab istilah di Pengaturan | `SettingsTabRegistry` + komponen baru | |
| MP-12 | sapuan empty/error state + `margin` periode | per layar | jalankan terakhir |

Selalu serial: MP-00, seluruh merge ke `main`, dan lajur mana pun yang ternyata
butuh migration.

## 4. Urutan yang disarankan

MP-00 → MP-02 (paling berbahaya: uang dan stok salah lebih buruk daripada layar
kosong) → MP-01 → MP-10 → MP-03 → MP-08 → MP-09 → MP-04/05/06/07 paralel →
MP-11 → MP-12.

## 5. Definition of Done per lajur

Sama dengan MQ: acceptance + negative test, `DATA_SOURCE=json php artisan test`
penuh, `vendor/bin/pint --test`, `npm run build`, bukti di
`docs/worker-reports/{MP-xx}.md` (**bukan** `AUTOPILOT_STATUS.md` selama ada
writer paralel), tanpa nama industri di kode (D-31).
