# INDUSTRY PRESETS SPECIFICATION
## Konfigurasi Fitur & Menu Dinamis 6 Industri

Dokumen ini mendefinisikan matriks konfigurasi default untuk 6 industri awal. Setiap preset mengaktifkan atau menonaktifkan fitur modular secara otomatis.

---

## 1. Matriks Konfigurasi Fitur (Feature Flags)

| Fitur / Sub-Modul | Key Flag | 1. Agency | 2. F&B | 3. Apotek | 4. Event Org | 5. Kontraktor | 6. Persewaan |
|---|---|:---:|:---:|:---:|:---:|:---:|:---:|
| **CRM: Leads Pipeline** | `crm.leads` | ✅ ON | ❌ OFF | ❌ OFF | ✅ ON | ✅ ON | ❌ OFF |
| **CRM: PIC & Contact Person** | `crm.pic` | ✅ ON | ❌ OFF | ❌ OFF | ✅ ON | ✅ ON | ❌ OFF |
| **CRM: Simple Loyalty & WA Phone** | `crm.simple_phone` | ❌ OFF | ✅ ON | ✅ ON | ❌ OFF | ❌ OFF | ✅ ON |
| **CRM: Pasien / Rekam Obat** | `crm.patient_history` | ❌ OFF | ❌ OFF | ✅ ON | ❌ OFF | ❌ OFF | ❌ OFF |
| **CRM: Jaminan / KTP Deposit** | `crm.deposit_identity` | ❌ OFF | ❌ OFF | ❌ OFF | ❌ OFF | ❌ OFF | ✅ ON |
| **POS: Kasir Cepat (Walk-in)** | `pos.quick_counter` | ❌ OFF | ✅ ON | ✅ ON | ❌ OFF | ❌ OFF | ❌ OFF |
| **POS: Meja & Open Bill** | `pos.open_bill_tables` | ❌ OFF | ✅ ON | ❌ OFF | ❌ OFF | ❌ OFF | ❌ OFF |
| **POS: e-Resep & Resep Dokter** | `pos.prescription_flow` | ❌ OFF | ❌ OFF | ✅ ON | ❌ OFF | ❌ OFF | ❌ OFF |
| **Operasional: Project Timesheet** | `ops.timesheet` | ✅ ON | ❌ OFF | ❌ OFF | ❌ OFF | ❌ OFF | ❌ OFF |
| **Operasional: Rundown & Vendor** | `ops.event_rundown` | ❌ OFF | ❌ OFF | ❌ OFF | ✅ ON | ❌ OFF | ❌ OFF |
| **Operasional: SPK & Opname Fisik** | `ops.contractor_spk` | ❌ OFF | ❌ OFF | ❌ OFF | ❌ OFF | ✅ ON | ❌ OFF |
| **Operasional: Kalender Unit & Jadwal** | `ops.rental_calendar` | ❌ OFF | ❌ OFF | ❌ OFF | ❌ OFF | ❌ OFF | ✅ ON |
| **Operasional: Check-in / Check-out** | `ops.rental_checkin` | ❌ OFF | ❌ OFF | ❌ OFF | ❌ OFF | ❌ OFF | ✅ ON |
| **Inventory: Stock Tracking** | `inventory.tracking` | ❌ OFF | ✅ ON | ✅ ON | ✅ ON (Asset) | ✅ ON | ✅ ON (Unit) |
| **Inventory: Batch & Expiry (FEFO)** | `inventory.fefo_expiry` | ❌ OFF | ❌ OFF | ✅ ON | ❌ OFF | ❌ OFF | ❌ OFF |
| **Inventory: Resep / BOM Bahan Baku** | `inventory.bom_recipe` | ❌ OFF | ✅ ON | ❌ OFF | ❌ OFF | ❌ OFF | ❌ OFF |
| **Sales: Termin & Milestone Billing** | `sales.milestone_billing` | ✅ ON | ❌ OFF | ❌ OFF | ✅ ON | ✅ ON | ❌ OFF |
| **Sales: SPH / Quotation Formal** | `sales.sph_quotation` | ✅ ON | ❌ OFF | ❌ OFF | ✅ ON | ✅ ON | ❌ OFF |
| **Sales: Retensi Pembayaran Proyek** | `sales.project_retention` | ❌ OFF | ❌ OFF | ❌ OFF | ❌ OFF | ✅ ON | ❌ OFF |
| **Sales: Rental Deposit & Denda Telat** | `sales.rental_deposit` | ❌ OFF | ❌ OFF | ❌ OFF | ❌ OFF | ❌ OFF | ✅ ON |
| **Keuangan: Buku Kas Sederhana** | `finance.cashbook` | ✅ ON | ✅ ON | ✅ ON | ✅ ON | ✅ ON | ✅ ON |
| **Keuangan: Akuntansi Pro (Jurnal, Neraca, L/R)** | `finance.accounting` | ❌ OFF | ❌ OFF | ❌ OFF | ❌ OFF | ✅ ON | ❌ OFF |
| **HRD: Data Karyawan & Presensi** | `hr.employees` | ✅ ON | ✅ ON | ✅ ON | ✅ ON | ✅ ON | ❌ OFF |
| **HRD: Payroll** | `hr.payroll` | ✅ ON | ❌ OFF | ❌ OFF | ❌ OFF | ✅ ON | ❌ OFF |
| **Sistem: Manajemen Asisten AI & Token** | `system.ai_agent` | ✅ ON | ✅ ON | ✅ ON | ✅ ON | ✅ ON | ✅ ON |

> **Aturan tampil modul (D-12/U-04):** modul level-1 dirender di App Switcher & sidebar
> **hanya jika** minimal satu flag anggotanya `true`. `finance.cashbook` sengaja ON di semua
> preset karena setiap bisnis butuh pencatatan kas; `finance.accounting` (Pro Mode) hanya
> untuk Kontraktor secara default. Backend **selalu** double-entry (REQUIREMENTS §2.1.4);
> flag ini hanya mengatur UI yang ditampilkan.

> **Preset `custom` (ke-7):** semua flag `false` kecuali `system.ai_agent = true` dan
> `finance.cashbook = true`. AI Onboarding menyalakan flag lain via `mcp_configure_modules`
> (lihat §4).

---

## 2. Struktur Menu & Dampak Visibilitas Sidebar

Registry menu terpusat di `app/Services/DynamicMenuRegistry.php`. Skema URL mengikuti
**D-24**: `/app/{module}/{path?}` dengan route bernama `app.module`. Registry menyimpan
**URL path**, bukan nama route Laravel per fitur.

> **Status implementasi (2026-09-16):** registry saat ini berbentuk katalog statis
> (`slug => [accent, items[]]`) tanpa `visible` dan tanpa `Company`. Bentuk target di bawah
> ini diwujudkan pada **T-15** (`Company::feature()`) dan **T-03b** (refactor registry ke
> bentuk flag-aware). Sampai saat itu, semua item dianggap `visible = true`.

### 2.1 Bentuk Target Registry (flag-aware)

```php
// Kunci = slug modul (dipakai di URL /app/{slug}). Urutan = urutan di App Switcher.
return [
    'crm' => [
        'title'   => 'CRM & Pelanggan',
        'icon'    => 'users',
        'accent'  => 'bg-emerald-600',
        'visible' => fn (Company $c) => $c->hasAnyFeature(['crm.leads', 'crm.simple_phone', 'crm.patient_history']),
        'items'   => [
            ['label' => 'Dashboard CRM',         'icon' => 'home',       'path' => '/app/crm',           'visible' => fn ($c) => true],
            ['label' => 'Pipeline Leads',        'icon' => 'funnel',     'path' => '/app/crm/leads',     'visible' => fn ($c) => $c->feature('crm.leads')],
            ['label' => 'Kontak & Pelanggan',    'icon' => 'book-user',  'path' => '/app/crm/contacts',  'visible' => fn ($c) => true],
            ['label' => 'Riwayat Pasien & Resep','icon' => 'stethoscope','path' => '/app/crm/patients',  'visible' => fn ($c) => $c->feature('crm.patient_history')],
        ],
    ],
    'pos' => [
        'title'   => 'Kasir & POS',
        'icon'    => 'shopping-cart',
        'accent'  => 'bg-purple-600',
        'visible' => fn (Company $c) => $c->hasAnyFeature(['pos.quick_counter', 'pos.open_bill_tables', 'pos.prescription_flow']),
        'items'   => [
            ['label' => 'Layar Kasir',           'icon' => 'cash-register','path' => '/app/pos',              'visible' => fn ($c) => $c->feature('pos.quick_counter')],
            ['label' => 'Manajemen Meja / Bill', 'icon' => 'table',        'path' => '/app/pos/tables',       'visible' => fn ($c) => $c->feature('pos.open_bill_tables')],
            ['label' => 'Antrean e-Resep',       'icon' => 'pill',         'path' => '/app/pos/prescriptions','visible' => fn ($c) => $c->feature('pos.prescription_flow')],
            ['label' => 'Riwayat Transaksi',     'icon' => 'receipt',      'path' => '/app/pos/history',      'visible' => fn ($c) => true],
        ],
    ],
    'operations' => [
        'title'   => 'Operasional',
        'icon'    => 'briefcase',
        'accent'  => 'bg-sky-600',
        // BUG FIX 2026-09-16: versi awal melupakan ops.rental_checkin sehingga tenant
        // rental yang hanya menyalakan check-in tidak melihat modul ini.
        'visible' => fn (Company $c) => $c->hasAnyFeature(['ops.timesheet', 'ops.event_rundown', 'ops.contractor_spk', 'ops.rental_calendar', 'ops.rental_checkin']),
        'items'   => [
            ['label' => 'Timesheet & Project Task', 'icon' => 'clock',      'path' => '/app/operations/timesheet', 'visible' => fn ($c) => $c->feature('ops.timesheet')],
            ['label' => 'Rundown & Event Matrix',   'icon' => 'calendar',   'path' => '/app/operations/rundown',   'visible' => fn ($c) => $c->feature('ops.event_rundown')],
            ['label' => 'SPK & Opname Progres',     'icon' => 'hard-hat',   'path' => '/app/operations/spk',       'visible' => fn ($c) => $c->feature('ops.contractor_spk')],
            ['label' => 'Kalender Sewa & Booking',  'icon' => 'calendar-days','path' => '/app/operations/rental-calendar','visible' => fn ($c) => $c->feature('ops.rental_calendar')],
            ['label' => 'Check-in / Check-out Unit','icon' => 'arrow-left-right','path' => '/app/operations/rental-checkin','visible' => fn ($c) => $c->feature('ops.rental_checkin')],
        ],
    ],
    'inventory' => [
        'title'   => 'Inventory',
        'icon'    => 'package',
        'accent'  => 'bg-indigo-600',
        'visible' => fn (Company $c) => $c->feature('inventory.tracking'),
        'items'   => [
            ['label' => 'Daftar Barang',        'icon' => 'boxes',    'path' => '/app/inventory/items',     'visible' => fn ($c) => true],
            ['label' => 'Stok Masuk & Keluar',  'icon' => 'arrows',   'path' => '/app/inventory/movements', 'visible' => fn ($c) => true],
            ['label' => 'Batch & Expiry (FEFO)','icon' => 'calendar-x','path' => '/app/inventory/batches',  'visible' => fn ($c) => $c->feature('inventory.fefo_expiry')],
            ['label' => 'Resep / BOM',          'icon' => 'list-tree','path' => '/app/inventory/bom',       'visible' => fn ($c) => $c->feature('inventory.bom_recipe')],
        ],
    ],
    'accounting' => [
        'title'   => 'Keuangan',
        'icon'    => 'landmark',
        'accent'  => 'bg-amber-600',
        'visible' => fn (Company $c) => $c->hasAnyFeature(['finance.cashbook', 'finance.accounting']),
        'items'   => [
            // Simple Mode (REQUIREMENTS §2.1.4): hanya Buku Kas.
            ['label' => 'Buku Kas',             'icon' => 'wallet',   'path' => '/app/accounting',            'visible' => fn ($c) => $c->feature('finance.cashbook')],
            // Pro Mode: menu akuntansi lengkap.
            ['label' => 'Bagan Akun',           'icon' => 'list',     'path' => '/app/accounting/coa',        'visible' => fn ($c) => $c->feature('finance.accounting')],
            ['label' => 'Jurnal Umum',          'icon' => 'book',     'path' => '/app/accounting/journals',   'visible' => fn ($c) => $c->feature('finance.accounting')],
            ['label' => 'Buku Besar',           'icon' => 'book-open','path' => '/app/accounting/ledger',     'visible' => fn ($c) => $c->feature('finance.accounting')],
            ['label' => 'Neraca & Laba-Rugi',   'icon' => 'scale',    'path' => '/app/accounting/reports',    'visible' => fn ($c) => $c->feature('finance.accounting')],
        ],
    ],
    'hrd' => [
        'title'   => 'HRD',
        'icon'    => 'users-round',
        'accent'  => 'bg-blue-600',
        'visible' => fn (Company $c) => $c->hasAnyFeature(['hr.employees', 'hr.payroll']),
        'items'   => [
            ['label' => 'Data Karyawan',    'icon' => 'id-card',  'path' => '/app/hrd/employees',  'visible' => fn ($c) => $c->feature('hr.employees')],
            ['label' => 'Presensi & Cuti',  'icon' => 'clock',    'path' => '/app/hrd/attendance', 'visible' => fn ($c) => $c->feature('hr.employees')],
            ['label' => 'Payroll',          'icon' => 'banknote', 'path' => '/app/hrd/payroll',    'visible' => fn ($c) => $c->feature('hr.payroll')],
        ],
    ],
    'settings' => [
        'title'   => 'Pengaturan',
        'icon'    => 'settings',
        'accent'  => 'bg-slate-600',
        'visible' => fn (Company $c) => true,
        // Sesuai U-02: Pengaturan adalah SATU halaman /app/settings dengan tab dinamis,
        // bukan sub-halaman terpisah. Item di bawah adalah deep-link ke tab.
        'items'   => [
            ['label' => 'Profil Bisnis & Pajak', 'icon' => 'building',  'path' => '/app/settings?tab=profile',   'visible' => fn ($c) => true],
            ['label' => 'Tampilan & Tema',       'icon' => 'palette',   'path' => '/app/settings?tab=theme',     'visible' => fn ($c) => true],
            ['label' => 'Fitur Bisnis',          'icon' => 'toggle',    'path' => '/app/settings?tab=features',  'visible' => fn ($c) => true],
            ['label' => 'Karyawan AI',           'icon' => 'bot',       'path' => '/app/settings?tab=ai-agent',  'visible' => fn ($c) => $c->feature('system.ai_agent')],
            ['label' => 'Penggunaan & Paket',    'icon' => 'gauge',     'path' => '/app/settings?tab=usage',     'visible' => fn ($c) => $c->feature('system.ai_agent')],
            ['label' => 'Tim & Akses',           'icon' => 'users',     'path' => '/app/settings?tab=team',      'visible' => fn ($c) => true],
        ],
    ],
];
```

### 2.2 Aturan Render (D-12 / U-04 — Zero-Bloat)

1. Modul level-1 dirender **hanya jika** `visible($company)` bernilai `true`.
2. Item level-2 dirender **hanya jika** `visible($company)` bernilai `true`.
3. Modul yang `visible` tetapi seluruh `items`-nya tidak `visible` **tidak dirender**.
4. Yang gagal evaluasi menghasilkan **tidak ada DOM sama sekali** — bukan `disabled`, bukan
   greyed-out, bukan placeholder teks. (Sudah ditegakkan oleh
   `tests/Feature/ModuleSidebarTest::test_unknown_module_renders_no_menu_items`.)
5. Akses langsung ke `/app/{module}/...` yang modulnya tidak `visible` → HTTP 403 via
   middleware `EnsureFeatureEnabled` (T-16). Modul yang tidak dikenal sama sekali → 404.

### 2.3 Ikon

Kolom `icon` adalah **nama ikon** (kompatibel Lucide), bukan emoji. Implementasi saat ini
masih memakai emoji sebagai placeholder dan akan diganti pada T-03b.

---
## 3. Override Dinamis & Fallback (Kustomisasi Mandiri)

- Jika tenant adalah bisnis hibrida (contoh: **Kafe F&B yang juga menyewakan ruang acara/venue**):
  1. Tenant memilih preset utama: `fnb`.
  2. Masuk ke menu `Pengaturan > Fitur Bisnis`.
  3. Mengaktifkan toggle `ops.rental_calendar` dan `sales.rental_deposit`.
  4. Sistem menyimpan override di tabel `module_settings` dengan scope `company_id`.
  5. Menu Kalender Sewa langsung muncul di sidebar seketika.

---

## 4. Preset 7: Universal "Custom" (via AI Onboarding)

Sistem juga dirancang untuk bisnis yang **tidak masuk dalam 6 kategori di atas** (misal: Toko Ritel Pakaian, Cuci Mobil, Konsultan Hukum). Alih-alih memberikan UI Web yang membingungkan, pembentukan *Custom Preset* dilakukan sepenuhnya oleh **Asisten AI**:
1. Saat pertama kali akun dibuat (Onboarding), AI menyapa Bos di WA: *"Halo Bos! Bisnis Bapak bergerak di bidang apa? Butuh struk kasir?"*
2. Lewat obrolan natural, AI mengidentifikasi kebutuhan bisnis (misal: hanya butuh Kasir dan Pencatatan Utang).
3. Bot mengeksekusi API internal (`mcp_configure_modules`) untuk menyalakan/mematikan fitur secara spesifik (`pos.quick_counter = ON`, sisanya `OFF`).
4. Saat Bos *login* ke Web, UI sudah bersih dan terpersonalisasi secara *magic* khusus untuk bisnisnya.
