# Laporan Worker — Perbaikan Sisi Tenant: Navigasi & Pola Layar `board`

Status: selesai, commit lokal (belum push).
Basis: HEAD `8a40ef1` → dua commit: `fb9e435` (navigasi) + commit pola layar `board`.

## Ringkasan

Dua cacat sisi tenant ditutup. Satu rencana dibatalkan setelah pembacaan model
data membuktikan premisnya salah — dicatat di bawah supaya tidak diulang.

## Bagian 1 — Navigasi (commit `fb9e435`)

### Koreksi temuan sebelumnya

Laporan audit saya sebelumnya menyatakan empat preset punya "capability mati"
(aktif tapi tidak terjangkau). **Itu salah.**
`DynamicMenuRegistry::modules()` meng-append setiap modul katalog yang tidak
didaftarkan preset, jadi `menus.order` hanya mengatur URUTAN, bukan keanggotaan.
Capability yang aktif tidak pernah hilang dari sidebar.

Dibuktikan empiris pada `bengkel-arka` (preset `bengkel`), bukan hanya dibaca:

```
VISIBLE(bengkel-arka): dashboard, contacts, inventory, hrd, accounting,
                       settings, projects, bookings, pos
```

POS memang ada. Yang rusak adalah **urutannya**: menu harian mendarat di
belakang "Pengaturan".

### Cacat yang nyata

1. **Pengaturan bukan item terakhir.** Modul yang di-append jatuh sesudahnya,
   sehingga Kasir bengkel menjadi item paling bawah sidebar. Diperbaiki:
   `modules()` sekarang memaksa `settings` ke posisi akhir.
2. **Sembilan preset memakai kunci menu yang tidak ada di registry**, dan
   `modules()` membuangnya diam-diam tanpa error — urutan yang ditulis
   penyusun preset tidak pernah terjadi:

   | Kunci salah | Preset | Koreksi |
   |---|---|---|
   | `orders` | bengkel, bakery_preorder, katering, cuci_mobil, fnb, kedai_kopi, pharmacy | grupnya bernama `pos` |
   | `pharmacy` | praktek_dokter | nama industri sebagai kunci menu; resep hidup di bawah `pos` |
   | `deals` | travel_umroh | itu submenu `contacts`, bukan grup |

   `bengkel` juga belum pernah mengurutkan `pos`, `bookings`, `projects`.

3. `laundry` ternyata **positif palsu** di audit saya: ia menulis `hrd` (kunci
   registry asli) sementara script audit hanya menerima alias `employees`.

### Test

`tests/Feature/PresetMenuContractTest.php` baru, berlaku untuk **seluruh 40
preset**: kunci menu harus resolve, grup capability harus diurutkan,
`settings` harus entri terakhir, dan urutan runtime harus berakhir di
`settings` dengan `pos` mendahuluinya.

`PresetCompositionBatch3Test::test_every_active_capability_has_a_navigable_home`
saya **hapus**: namanya dan komentarnya ("fitur mati") menjanjikan jaminan
reachability yang tidak pernah ia buktikan. Digantikan test repo-wide di atas
dengan deskripsi yang jujur. `DynamicMenuRegistryTest` diperbarui untuk
mengunci kontrak urutan baru.

## Bagian 2 — Pola layar `board`

### Masalah

Empat item menu merujuk pola layar yang kelas komponennya tidak ada
(`contract`, `board`, `report`), sehingga `ModuleController::screenComponent()`
mengembalikan null dan `module-shell.blade.php` merender **kartu kontrak
developer** — memperlihatkan nama pola dan nama entity mentah plus kalimat
"Implementasi interaksi layar dilanjutkan pada task pola layar berikutnya."

| Menu | Pola | Terjangkau di |
|---|---|---|
| Keuangan → Tagihan | `contract` | 29 dari 40 preset |
| Booking → Check-in & Deposit | `board` | 5 preset |
| Proyek → Termin & Opname | `contract` | 3 preset |
| Kasir → Meja & Pesanan | `board` | 2 preset (fnb, kedai_kopi) |

### `BoardScreen` dibangun

`app/Livewire/Screens/BoardScreen.php` + `resources/views/livewire/screens/board.blade.php`.

Papan okupansi per sumber daya. Kegenerikannya dijaga struktural, bukan oleh
konvensi penulisan:

- Kolom = baris entitas `resources` milik company.
- Penempatan baris ditemukan dari `references` di schema entitas (field yang
  menunjuk `resources`), **tidak** dihardcode. Karena itu satu kelas melayani
  `orders` (Meja) dan `bookings` (Check-in).
- Field nilai dan field deposit diturunkan dari `properties` schema; kolom
  deposit hanya muncul bila schema entitas memang mendeklarasikannya.
- Tahap terminal dibaca dari alur kerja preset, jadi baris yang sudah tamat
  dihitung sebagai "selesai", bukan okupansi berjalan. Item menu berpola papan
  tidak mewajibkan alur kerja (`eo` punya `bookings.deposit` tanpa alur
  `bookings`), jadi ketiadaan alur bukan error.
- Baris tanpa sumber daya mendapat kolomnya sendiri, tidak hilang diam-diam.

**Baca-saja, disengaja.** Perpindahan tahap sudah punya rumah di pola
`pipeline`; menduplikasi logika transisi di sini hanya menambah jalur yang
harus diamankan ulang.

9 test di `tests/Feature/BoardScreenTest.php`, termasuk fail-closed saat
company berubah setelah mount, fail-closed saat capability dicabut, dan
pemeriksaan D-31 bahwa kelas maupun view tidak menyebut istilah industri.

## Bagian 3 — Rencana yang DIBATALKAN: `ContractScreen`

Rencana awal adalah membangun `ContractScreen` untuk menutup 29 preset di menu
"Tagihan". **Dibatalkan sebelum satu baris ditulis.**

Alasannya fakta model data: entity `invoices` **bukan** tagihan pelanggan
tenant. Ia tabel billing platform sendiri — `type` enum `topup|subscription`,
`company_membership_id`, `payment_url`, `token_amount_granted`
(`database/schemas/invoices.schema.json`, `docs/DATA_MODEL.md` §2.3, D-23).

Kedua item berpola `contract` menunjuk entity itu. Membangun layar di atasnya
akan memperlihatkan **tagihan langganan Agentic BOS milik tenant sendiri** di
menu Keuangan → Tagihan, seolah itu piutang pelanggannya. Lebih buruk dari
placeholder: placeholder tidak berbohong.

Tidak ada entitas tagihan-pelanggan di repo ini — tidak ada schema, tidak ada
migration. Menutup 29 preset itu butuh entitas baru = perubahan arsitektur,
yang gate-nya milik Bos.

**Yang dilakukan sebagai gantinya:** kedua item diberi `navigation: false`,
mengikuti pola yang sudah dipakai `projects.quotations` dan `hrd.employees`.
Menu berhenti menjanjikan yang tidak ada, sementara route dipertahankan supaya
kontrak path kanonik di `DynamicMenuRegistryTest` tidak berubah.

Pola `report` (`accounting/reports`) sengaja tidak disentuh: `finance.accounting`
aktif di **0 dari 40 preset**, jadi tidak ada tenant yang bisa mencapainya.

## Verifikasi

```
DATA_SOURCE=json php artisan test
→ 929 passed, 4590 assertions

DATA_SOURCE=json php artisan test --filter="BoardScreenTest|PresetMenuContractTest|DynamicMenuRegistryTest|ModuleSidebarTest|PipelineScreenTest|CashierScreenTest|RenderAllPresetsTest|SidebarTest"
→ 68 passed, 402 assertions

vendor/bin/pint (file task ini) → PASS 3 files
npm run build → PASS
```

## Catatan proses

- Writer lain aktif selama pekerjaan ini (LandingPage publik, `routes/web.php`,
  `DashboardComposer`, beberapa blade, `phpunit.xml`, tiga file test). Satu
  putaran full-suite saya sempat menunjukkan 4 kegagalan
  (`LobbyNavigationTest` 302, `WorkflowEngineTest` ukuran koleksi) yang
  **tidak reproducible**: `WorkflowEngineTest` lulus 3/3 saat diisolasi, dan
  putaran berikutnya hijau penuh. Itu polusi fixture dari aktivitas paralel,
  bukan defect.
- `vendor/bin/pint --test` melaporkan satu pelanggaran di
  `tests/Feature/LobbyNavigationTest.php` (`class_attributes_separation`).
  File itu milik writer lain dan sedang dimodifikasi; **dicatat, tidak
  diperbaiki** sesuai kebijakan Pint di `EXECUTION_PLAN.md` §0.2.
- `docs/EXECUTION_PLAN.md` dan `docs/AUTOPILOT_STATUS.md` tidak disentuh.

## Sisa risiko

- Papan `board` belum pernah dilihat dari HP pada tenant produksi; buktinya
  masih level test + render Livewire.
- 29 preset masih tidak punya layar tagihan pelanggan sama sekali. Menu-nya
  kini jujur (tersembunyi), tapi kebutuhannya belum terjawab. Keputusan
  entitas tagihan pelanggan menunggu Bos.
