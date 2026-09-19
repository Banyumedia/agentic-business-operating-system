# INDUSTRY PRESETS SPECIFICATION
## Katalog Kapabilitas & Preset-sebagai-Data untuk Puluhan Jenis Bisnis

Dokumen ini mendefinisikan **cara Agentic BOS melayani banyak jenis bisnis tanpa
kode baru per industri** (D-31). Enam preset awal adalah contoh pertama, bukan
batas produk.

Prinsip inti: **industri = data, kapabilitas = kode.**

---

## 0. Model Mental

```
┌──────────────────────────────────────────────────────────────┐
│  KAPABILITAS (kode, dibangun sekali, ~20 modul)              │
│  contacts · deals · projects · bookings · inventory · pos …  │
└──────────────────────────────────────────────────────────────┘
                              ▲ dikomposisi oleh
┌──────────────────────────────────────────────────────────────┐
│  PRESET INDUSTRI (data, 1 baris JSON per industri)           │
│  { capabilities, terminology, workflows, dashboard, menus }  │
└──────────────────────────────────────────────────────────────┘
                              ▲ di-override oleh
┌──────────────────────────────────────────────────────────────┐
│  COMPANY (module_settings)                                   │
│  toggle kapabilitas · ubah istilah · ubah stage · pilih tema │
└──────────────────────────────────────────────────────────────┘
```

Menambah **industri ke-N**: tulis satu baris `definition` JSON, jalankan seeder.
Menambah **kapabilitas** baru: keputusan arsitektur (D-32), butuh kode.

---

## 1. Katalog Kapabilitas v1 (D-32)

Setiap kapabilitas adalah kunci flag yang dibaca `Company::feature($key)`.
Kapabilitas **tidak boleh** menyebut nama industri.

| Key | Nama | Apa yang dibuka | Dipakai oleh (contoh) |
|---|---|---|---|
| `contacts` | Kontak & Pelanggan | Buku alamat, riwayat interaksi, tag | Semua |
| `deals` | Pipeline Peluang | Kanban stage untuk peluang/proyek/kunjungan | Agency, EO, Kontraktor, klinik, sekolah |
| `projects` | Pekerjaan Berbasis Proyek | Proyek dengan tahapan, PIC, tanggal, biaya | Agency, Kontraktor, EO, bengkel, percetakan |
| `projects.progress_billing` | Penagihan Berdasarkan Progres | Termin/opname % + retensi | Kontraktor, arsitek, developer software |
| `scheduling` | Jadwal & Kalender | Agenda per menit/jam, PIC, rundown | EO, klinik, salon, kursus, bengkel |
| `bookings` | Booking Sumber Daya | Reservasi unit/kamar/kursi/meja per waktu, anti double-booking | Rental, hotel, coworking, studio, lapangan futsal |
| `bookings.deposit` | Deposit & Denda | Jaminan, denda keterlambatan, check-in/out | Rental, hotel, persewaan alat |
| `inventory` | Stok Barang | Item, lokasi, mutasi masuk/keluar | F&B, apotek, ritel, bengkel |
| `inventory.batch_expiry` | Batch & Kedaluwarsa (FEFO) | Lot, tanggal kedaluwarsa, saran FEFO | Apotek, sembako, kosmetik, F&B bahan baku |
| `inventory.bom` | Resep / Bill of Material | Komposisi bahan → produk jadi | F&B, roti, manufaktur ringan |
| `pos` | Kasir | Transaksi walk-in, shift, kas | F&B, ritel, apotek, laundry |
| `pos.tables` | Meja & Open Bill | Buka meja, kirim ke dapur, gabung bill | Resto, kafe, billiard |
| `quotations` | Penawaran / SPH | Dokumen penawaran formal → konversi ke order | Agency, Kontraktor, EO, B2B |
| `milestone_billing` | Termin Penagihan | Invoice bertahap (DP/pelunasan) | Agency, EO, Kontraktor, wedding |
| `approval_flow` | Persetujuan 2-Langkah | Tiket approval untuk aksi berisiko (D-11/D-27) | Semua (wajib bila `system.ai_agent`) |
| `timesheet` | Timesheet | Jam kerja per staf per proyek | Agency, konsultan, law firm |
| `finance.cashbook` | Buku Kas Sederhana | UI +Pemasukan/−Pengeluaran; backend tetap jurnal | Semua (default ON) |
| `finance.accounting` | Akuntansi Pro | CoA, jurnal manual, buku besar, neraca, L/R | Kontraktor, korporat, yang butuh audit |
| `hr.employees` | Data Karyawan & Presensi | Karyawan, cuti, presensi | Semua kecuali solo |
| `hr.payroll` | Payroll | Slip gaji, potongan | Agency, Kontraktor, resto besar |
| `system.ai_agent` | Karyawan AI & Token | Asisten WA, kuota token, grup role | Semua (default ON) |

**Tier B — modul domain khusus** (D-33). Hanya bila aturan tidak bisa jadi data:

| Key | Nama | Mengapa tidak bisa jadi data | Industri serumpun |
|---|---|---|---|
| `pharmacy.prescription` | Resep & Obat Keras | Regulasi: golongan obat, verifikasi apoteker, SIP dokter | Apotek, klinik, RS kecil, toko obat hewan |
| `construction.retention` | Retensi & Opname Fisik | Perhitungan retensi tertahan, rilis 3–6 bulan setelah FHO, BA opname | Kontraktor sipil, MEP, interior, developer |
| `manufacturing.production_order` | Order Produksi & WIP | BOM multi-level, alokasi Work in Progress, realisasi bahan | Pabrik, garmen, perakitan, kitchen besar |

Kapabilitas Tier B **bergantung** pada Tier A: `pharmacy.prescription` butuh
`inventory.batch_expiry` + `pos` + `contacts`; `construction.retention` butuh
`projects.progress_billing` + `milestone_billing`; `manufacturing.production_order`
butuh `inventory.bom` + `inventory.batch_expiry` + `finance.accounting`.

---

## 2. Skema `definition` Preset

Setiap preset adalah **satu dokumen JSON** di `business_presets.definition`.
Skema dikunci; seeder dan endpoint admin memvalidasinya.

```jsonc
{
  "key": "rental",
  "name": "Persewaan",
  "tier": "A",                       // A = komposisi murni | B = butuh modul khusus
  "capabilities": {                  // map flag → bool; yang tidak disebut = false
    "contacts": true,
    "bookings": true,
    "bookings.deposit": true,
    "inventory": true,
    "finance.cashbook": true,
    "system.ai_agent": true,
    "approval_flow": true
  },
  "terminology": {                   // istilah UI; fallback ke default global
    "contact": "Penyewa",
    "contacts": "Penyewa",
    "resource": "Unit",
    "resources": "Armada / Unit",
    "booking": "Sewa",
    "deal": null                     // null = pakai default
  },
  "workflows": {                     // per entity; stage codes netral, label ID
    "bookings": {
      "stages": [
        {"code": "draft",     "label": "Draft"},
        {"code": "confirmed", "label": "Dikonfirmasi"},
        {"code": "out",       "label": "Sedang Disewa"},
        {"code": "overdue",   "label": "Terlambat"},
        {"code": "returned",  "label": "Dikembalikan"},
        {"code": "cancelled", "label": "Batal"}
      ],
      "transitions": [
        {"from": "draft",     "to": "confirmed", "roles": ["owner","staff"]},
        {"from": "confirmed", "to": "out",       "roles": ["owner","staff"]},
        {"from": "out",       "to": "returned",  "roles": ["owner","staff"], "effects": ["bookings.late_fee.compute"]},
        {"from": "out",       "to": "overdue",   "roles": ["system"]},
        {"from": "overdue",   "to": "returned",  "roles": ["owner","staff"], "effects": ["bookings.late_fee.compute"]},
        {"from": "*",         "to": "cancelled", "roles": ["owner"], "requires_approval": true}
      ],
      "terminal": ["returned", "cancelled"]
    }
  },
  "dashboard": {                     // susunan widget dari katalog §4
    "industry_zone": [
      {"widget": "resources_status", "props": {"group_by": "status"}},
      {"widget": "bookings_due_today"},
      {"widget": "overdue_returns"}
    ]
  },
  "menus": {                         // override urutan/label modul; default dari registry
    "order": ["bookings", "contacts", "inventory", "accounting", "settings"]
  }
}
```

Aturan skema:
- `capabilities` hanya boleh memakai key dari §1 (validasi seeder menolak key asing).
- `terminology` hanya boleh mengisi kunci yang ada di **kamus istilah global** (§3).
- `workflows.<entity>.stages[*].code` harus `^[a-z_]+$`; label bebas Indonesia.
  Elemen `stages[0]` adalah stage awal workflow.
- `workflows.<entity>.terminal` wajib berupa array non-kosong yang hanya berisi
  kode stage terdaftar. Stage terminal boleh tidak punya transisi keluar;
  setiap stage non-terminal wajib punya sedikitnya satu transisi keluar.
- Urutan elemen `stages[]` adalah dasar arah transisi. Transisi ke stage dengan
  indeks lebih kecil adalah **transisi mundur** dan wajib memiliki
  `requires_note: true`. Nilai `from: "*"` hanya mencakup stage non-terminal.
- Semua stage harus terjangkau dari stage pertama melalui `transitions[]`.
  Preset dengan stage tidak terjangkau, dead end non-terminal, atau tanpa
  deklarasi `terminal` ditolak sesuai D-46.
- `transitions[*].effects` hanya dari **katalog efek** yang diimplementasikan `WorkflowEngine` (§5).
- `dashboard.industry_zone[*].widget` hanya dari **katalog widget** (§4).
- Preset `tier: "B"` boleh menyebut kapabilitas Tier B; validator memastikan dependensinya juga `true`.

---

## 3. Kamus Terminologi Global

Semua Blade memakai `{{ term('contact') }}`, **bukan** literal. Kunci yang
tersedia (default Indonesia netral):

| Kunci | Default | Contoh override |
|---|---|---|
| `contact` / `contacts` | Kontak / Kontak | Pasien, Penyewa, Klien, Tamu, Siswa, Jemaah |
| `deal` / `deals` | Peluang / Peluang | Proyek, Kunjungan, Pendaftaran, Pesanan |
| `project` / `projects` | Proyek / Proyek | Pekerjaan, Event, Kasus, Job |
| `resource` / `resources` | Sumber Daya / Sumber Daya | Unit, Kamar, Meja, Kursi, Lapangan, Alat |
| `booking` / `bookings` | Booking / Booking | Sewa, Reservasi, Janji Temu, Jadwal |
| `item` / `items` | Barang / Barang | Obat, Menu, Bahan, Produk, Sparepart |
| `order` / `orders` | Pesanan / Pesanan | Bill, Tagihan Meja, Nota, Work Order |
| `staff` / `staffs` | Staf / Staf | Karyawan, Terapis, Mekanik, Guru, Crew |
| `invoice` / `invoices` | Tagihan / Tagihan | Invoice, Kuitansi |
| `vendor` / `vendors` | Vendor / Vendor | Supplier, Subkon, Pemasok |

Helper: `term(string $key, ?Company $company = null): string` — urutan resolusi:
override company → preset → default global. Di-cache per request.

---

## 4. Katalog Widget Dashboard

Widget adalah komponen Livewire generik yang **di-bind ke kapabilitas**, bukan
ke industri. Preset hanya memilih susunannya.

| Widget | Butuh kapabilitas | Menampilkan |
|---|---|---|
| `kpi_revenue` | `finance.cashbook` | Omzet periode + sparkline |
| `kpi_cashflow` | `finance.cashbook` | Kas masuk/keluar |
| `kpi_receivables_due` | `milestone_billing` \| `pos` | Piutang jatuh tempo |
| `ai_report_card` | `system.ai_agent` | Ringkasan harian dari Karyawan AI |
| `deals_pipeline` | `deals` | Peluang per stage (kanban mini) |
| `projects_progress` | `projects` | Bar progres proyek aktif vs target |
| `retention_held` | `construction.retention` | Dana retensi tertahan & jadwal rilis |
| `upcoming_schedule` | `scheduling` | Agenda hari/minggu ini |
| `resources_status` | `bookings` | Unit tersedia vs terpakai (denah/grid) |
| `bookings_due_today` | `bookings` | Booking mulai/selesai hari ini |
| `overdue_returns` | `bookings.deposit` | Pengembalian terlambat + denda berjalan |
| `open_bills` | `pos.tables` | Meja terisi & bill belum bayar |
| `expiring_batches` | `inventory.batch_expiry` | Item kedaluwarsa < N hari (FEFO) |
| `low_stock` | `inventory` | Item di bawah minimum |
| `prescription_queue` | `pharmacy.prescription` | Resep menunggu verifikasi apoteker |
| `timesheet_summary` | `timesheet` | Jam kerja tim minggu ini |
| `vendor_settlement` | `projects` + `vendors` | Vendor menunggu pelunasan |
| `pending_approvals` | `approval_flow` | Tiket approval menunggu Bos |

Widget yang kapabilitasnya `false` **tidak dirender** (zero-bloat, D-12).

---

## 5. Katalog Efek Workflow

Efek adalah aksi yang dijalankan `WorkflowEngine` saat transisi. Implementasi
di kode, dirujuk preset sebagai string.

| Efek | Aksi |
|---|---|
| `bookings.late_fee.compute` | Hitung denda dari aturan booking |
| `journal.post` | Posting jurnal otomatis dari template (hanya via dokumen sah, D-04) |
| `approval.request` | Buat tiket approval (D-27) — dipicu otomatis oleh `requires_approval: true` |

`stock.reserve`, `stock.deduct`, `invoice.create_dp`, `invoice.create_final`,
`bookings.deposit.collect`, `bookings.deposit.settle`, dan `notify.owner_wa` belum menjadi vocabulary
preset yang sah sampai handler transaksional, idempoten, dan kontrak inputnya
tersedia. Workflow preset tidak boleh mendeklarasikan key tersebut lebih awal.
Satu transisi dibatasi ke satu effect sampai outbox/kompensasi lintas-effect
tersedia. `approval.request` hanya dipicu melalui `requires_approval: true`;
transisi approval tidak boleh sekaligus mendeklarasikan `effects`.

---

## 6. Enam Preset Awal (contoh `definition`)

Ringkasan kapabilitas; `definition` lengkap memakai satu sumber kanonik di
`database/presets/{slug}.json`. Seeder Fase 3 membaca file yang sama dan tidak
memiliki salinan di folder seeder.

### 6.1 Kontrak workflow minimum preset demo Fase 2

Kontrak berikut wajib hadir pada file kanonik T-F4 agar layar T-F6/T-F10
memiliki alur nyata. Nama preset tetap data; `WorkflowEngine` hanya membaca
struktur generik ini.

| Preset | Entity | Urutan `stages[]` minimum | Transisi wajib | `terminal` |
|---|---|---|---|---|
| Bengkel | `orders` | `masuk`, `pemeriksaan`, `pengerjaan`, `qc`, `siap_diambil`, `selesai`, `dibatalkan` | alur maju; lompatan `masuk -> pengerjaan`; rework `qc -> pengerjaan` dengan `requires_note: true`; `qc -> siap_diambil` | `selesai`, `dibatalkan` |
| Klinik | `bookings` | `dijadwalkan`, `check_in`, `diperiksa`, `selesai`, `dibatalkan` | alur maju menuju `selesai`; pembatalan dari stage non-terminal | `selesai`, `dibatalkan` |
| Salon | `bookings` | `dijadwalkan`, `check_in`, `dilayani`, `selesai`, `dibatalkan` | alur maju menuju `selesai`; pembatalan dari stage non-terminal | `selesai`, `dibatalkan` |

Setiap transisi tambahan tetap tunduk pada aturan reachability, dead end, dan
transisi mundur di §2; tabel ini adalah minimum, bukan cabang industri di kode.

| Kapabilitas | Agency | F&B | Apotek | EO | Kontraktor | Persewaan | Custom |
|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| `contacts` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| `deals` | ✅ | ❌ | ❌ | ✅ | ✅ | ❌ | ❌ |
| `projects` | ✅ | ❌ | ❌ | ✅ | ✅ | ❌ | ❌ |
| `projects.progress_billing` | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ |
| `scheduling` | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ |
| `bookings` | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ |
| `bookings.deposit` | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ |
| `inventory` | ❌ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| `inventory.batch_expiry` | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ |
| `inventory.bom` | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| `pos` | ❌ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| `pos.tables` | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| `quotations` | ✅ | ❌ | ❌ | ✅ | ✅ | ❌ | ❌ |
| `milestone_billing` | ✅ | ❌ | ❌ | ✅ | ✅ | ❌ | ❌ |
| `approval_flow` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `timesheet` | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| `finance.cashbook` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| `finance.accounting` | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ |
| `hr.employees` | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| `hr.payroll` | ✅ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ |
| `system.ai_agent` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Tier B** `pharmacy.prescription` | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Tier B** `construction.retention` | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ |
| **Tier B** `manufacturing.production_order` | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Tier** | A | A | A+B | A | A+B | A | A |

Terminologi per preset (cuplikan): Agency `contact→Klien, deal→Proyek`; F&B
`contact→Pelanggan, order→Bill, resource→Meja`; Apotek `contact→Pasien,
item→Obat`; EO `project→Event, vendor→Vendor`; Kontraktor `project→Proyek,
contact→Pemberi Kerja, vendor→Subkon`; Persewaan `contact→Penyewa,
resource→Unit, booking→Sewa`.

**Preset `custom`:** hanya `finance.cashbook`, `approval_flow`, `system.ai_agent`.
AI onboarding menyalakan sisanya (§8).

---

## 7. Peta Pasar: 63 Bisnis Potensial Pengguna Agentic BOS

Bukti bahwa arsitektur ini menutup bisnis di luar 6 awal:

| Industri | Kapabilitas | Terminologi kunci |
|---|---|---|
| Klinik / Praktek Dokter | `contacts, scheduling, bookings, inventory, inventory.batch_expiry, pos, finance.cashbook` | contact→Pasien, booking→Janji Temu, staff→Dokter |
| Salon / Barbershop | `contacts, scheduling, bookings, pos, inventory, hr.employees` | contact→Pelanggan, staff→Kapster, resource→Kursi |
| Laundry | `contacts, pos, inventory, approval_flow` + workflow order `received→washing→ready→picked_up` | order→Nota, item→Cucian |
| Bengkel | `contacts, projects, quotations, inventory, pos, timesheet` | project→Work Order, item→Sparepart, staff→Mekanik |
| Kursus / Bimbel | `contacts, scheduling, bookings, milestone_billing, hr.employees` | contact→Siswa, booking→Kelas, staff→Pengajar |
| Kos / Coworking | `contacts, bookings, bookings.deposit, milestone_billing, finance.cashbook` | resource→Kamar/Meja, booking→Sewa, contact→Penghuni |
| Warnet / Gaming | `contacts, scheduling, bookings, inventory, pos, finance.cashbook, hr.employees, approval_flow, system.ai_agent` | contact→Pelanggan, resource→Unit, booking→Sesi |
| Cuci Sepatu | `contacts, inventory, pos, finance.cashbook, hr.employees, approval_flow, system.ai_agent` | contact→Pelanggan, item→Layanan, order→Order |
| Percetakan | `contacts, quotations, inventory, pos, finance.cashbook, hr.employees, approval_flow, system.ai_agent` | contact→Klien, item→Produk Cetak, order→Pesanan |
| Service AC | `contacts, scheduling, bookings, inventory, pos, finance.cashbook, hr.employees, approval_flow, system.ai_agent` | contact→Pelanggan, staff→Teknisi, booking→Kunjungan |
| Toko Bangunan | `contacts, inventory, pos, quotations, finance.cashbook, hr.employees, approval_flow, system.ai_agent` | contact→Pelanggan, item→Material, order→Pesanan |
| Cleaning Service | `contacts, scheduling, bookings, projects, timesheet, quotations, finance.cashbook, hr.employees, approval_flow, system.ai_agent` | contact→Klien, project→Kontrak, booking→Jadwal |

Menambah salah satu = **satu file JSON** + `php artisan db:seed --class=BusinessPresetSeeder`.

---

## 8. Override Dinamis & Preset `custom` via AI

- **Override company** disimpan di `module_settings`:
  - `module_name='features'` → `settings_json = {"bookings": true, ...}` (toggle kapabilitas)
  - `module_name='terminology'` → `{"contact": "Jemaah"}`
  - `module_name='workflows'` → `{ "deal": { "stages": [...], "transitions": [...] } }` (menimpa preset)
  - `module_name='dashboard'` → `{ "industry_zone": [...] }`
- Contoh hibrida: Kafe (`fnb`) yang juga menyewakan venue → owner menyalakan
  `bookings` + `bookings.deposit` di Pengaturan > Fitur Bisnis; widget
  `resources_status` dan menu Booking muncul seketika. Nol kode.
- **Preset `custom`:** saat onboarding, AI bertanya kebutuhan lalu memanggil
  `mcp_configure_modules({capabilities:{...}, terminology:{...}})` → tersimpan
  sebagai override company di atas preset `custom`. Hasilnya adalah preset
  Tier A yang dirakit untuk satu tenant, tanpa menyentuh katalog.

---

## 9. Registry Menu (flag-aware, mengikuti D-24)

Registry membaca **kapabilitas**, bukan industri. Bentuk target (T-03b):

```php
return [
    'contacts' => [
        'title'   => fn () => term('contacts'),
        'icon'    => 'book-user',
        'accent'  => 'bg-emerald-600',
        'visible' => fn (Company $c) => $c->feature('contacts'),
        'items'   => [
            ['label' => fn () => 'Daftar '.term('contacts'), 'path' => '/app/contacts',       'visible' => fn ($c) => true],
            ['label' => fn () => 'Pipeline '.term('deals'),  'path' => '/app/contacts/deals', 'visible' => fn ($c) => $c->feature('deals')],
        ],
    ],
    'projects' => [
        'title'   => fn () => term('projects'),
        'icon'    => 'briefcase',
        'accent'  => 'bg-sky-600',
        'visible' => fn (Company $c) => $c->feature('projects'),
        'items'   => [
            ['label' => fn () => 'Daftar '.term('projects'), 'path' => '/app/projects',           'visible' => fn ($c) => true],
            ['label' => 'Penawaran / SPH',                    'path' => '/app/projects/quotations','visible' => fn ($c) => $c->feature('quotations')],
            ['label' => 'Termin & Opname',                    'path' => '/app/projects/billing',   'visible' => fn ($c) => $c->feature('projects.progress_billing')],
            ['label' => 'Retensi',                            'path' => '/app/projects/retention', 'visible' => fn ($c) => $c->feature('construction.retention')],
            ['label' => 'Timesheet',                          'path' => '/app/projects/timesheet', 'visible' => fn ($c) => $c->feature('timesheet')],
        ],
    ],
    'bookings' => [
        'title'   => fn () => term('bookings'),
        'icon'    => 'calendar-check',
        'accent'  => 'bg-violet-600',
        'visible' => fn (Company $c) => $c->hasAnyFeature(['bookings', 'scheduling']),
        'items'   => [
            ['label' => 'Kalender',                              'path' => '/app/bookings',           'visible' => fn ($c) => true],
            ['label' => fn () => 'Daftar '.term('resources'),    'path' => '/app/bookings/resources', 'visible' => fn ($c) => $c->feature('bookings')],
            ['label' => 'Check-in / Check-out',                  'path' => '/app/bookings/checkin',   'visible' => fn ($c) => $c->feature('bookings.deposit')],
            ['label' => 'Rundown',                               'path' => '/app/bookings/rundown',   'visible' => fn ($c) => $c->feature('scheduling')],
        ],
    ],
    'inventory' => [
        'title'   => fn () => term('items'),
        'icon'    => 'package',
        'accent'  => 'bg-indigo-600',
        'visible' => fn (Company $c) => $c->feature('inventory'),
        'items'   => [
            ['label' => fn () => 'Daftar '.term('items'), 'path' => '/app/inventory',           'visible' => fn ($c) => true],
            ['label' => 'Mutasi Stok',                     'path' => '/app/inventory/movements', 'visible' => fn ($c) => true],
            ['label' => 'Batch & Kedaluwarsa',             'path' => '/app/inventory/batches',   'visible' => fn ($c) => $c->feature('inventory.batch_expiry')],
            ['label' => 'Resep / BOM',                     'path' => '/app/inventory/bom',       'visible' => fn ($c) => $c->feature('inventory.bom')],
        ],
    ],
    'pos' => [
        'title'   => 'Kasir',
        'icon'    => 'shopping-cart',
        'accent'  => 'bg-purple-600',
        'visible' => fn (Company $c) => $c->feature('pos'),
        'items'   => [
            ['label' => 'Layar Kasir',                        'path' => '/app/pos',               'visible' => fn ($c) => true],
            ['label' => fn () => term('resources').' & Bill', 'path' => '/app/pos/tables',        'visible' => fn ($c) => $c->feature('pos.tables')],
            ['label' => 'Antrean Resep',                      'path' => '/app/pos/prescriptions', 'visible' => fn ($c) => $c->feature('pharmacy.prescription')],
            ['label' => 'Riwayat Transaksi',                  'path' => '/app/pos/history',       'visible' => fn ($c) => true],
        ],
    ],
    'accounting' => [
        'title'   => 'Keuangan',
        'icon'    => 'landmark',
        'accent'  => 'bg-amber-600',
        'visible' => fn (Company $c) => $c->hasAnyFeature(['finance.cashbook', 'finance.accounting']),
        'items'   => [
            ['label' => 'Buku Kas',            'path' => '/app/accounting',          'visible' => fn ($c) => $c->feature('finance.cashbook')],
            ['label' => fn () => term('invoices'), 'path' => '/app/accounting/invoices','visible' => fn ($c) => $c->hasAnyFeature(['milestone_billing','pos'])],
            ['label' => 'Bagan Akun',          'path' => '/app/accounting/coa',      'visible' => fn ($c) => $c->feature('finance.accounting')],
            ['label' => 'Jurnal Umum',         'path' => '/app/accounting/journals', 'visible' => fn ($c) => $c->feature('finance.accounting')],
            ['label' => 'Neraca & Laba-Rugi',  'path' => '/app/accounting/reports',  'visible' => fn ($c) => $c->feature('finance.accounting')],
        ],
    ],
    'hrd' => [
        'title'   => 'HRD',
        'icon'    => 'users-round',
        'accent'  => 'bg-blue-600',
        'visible' => fn (Company $c) => $c->hasAnyFeature(['hr.employees', 'hr.payroll']),
        'items'   => [
            ['label' => fn () => 'Data '.term('staffs'), 'path' => '/app/hrd/employees',  'visible' => fn ($c) => $c->feature('hr.employees')],
            ['label' => 'Presensi & Cuti',               'path' => '/app/hrd/attendance', 'visible' => fn ($c) => $c->feature('hr.employees')],
            ['label' => 'Payroll',                       'path' => '/app/hrd/payroll',    'visible' => fn ($c) => $c->feature('hr.payroll')],
        ],
    ],
    'settings' => [ /* tetap seperti U-02: satu halaman /app/settings dengan tab */ ],
];
```

### 9.1 Aturan Render (D-12 / U-04 — Zero-Bloat)

1. Modul level-1 dirender **hanya jika** `visible($company)` `true` **dan** minimal
   satu item level-2 `visible`.
2. Item level-2 dirender **hanya jika** `visible($company)` `true`.
3. Yang gagal → **tidak ada DOM sama sekali** (sudah ditegakkan
   `ModuleSidebarTest::test_unknown_module_renders_no_menu_items`).
4. Akses langsung ke path yang modulnya tidak `visible` → 403 (`EnsureFeatureEnabled`,
   T-16). Modul tak dikenal → 404.
5. `title`/`label` boleh closure agar `term()` dievaluasi per company.

### 9.2 Ikon

`icon` adalah nama ikon (kompatibel Lucide). Implementasi saat ini masih emoji
placeholder; diganti pada T-03b.

---

## 10. Batas Realistis

Arsitektur ini menargetkan **≥80% jenis bisnis** murni dari komposisi (Tier A).
Sisa ~20% dengan aturan domain unik (farmasi, konstruksi, manufaktur bertingkat,
lembaga keuangan) butuh modul Tier B yang dibangun **sekali** dan dipakai
industri serumpun. Menambah kapabilitas atau modul Tier B adalah keputusan
arsitektur (D-32) — bukan sesuatu yang autopilot putuskan sendiri.
