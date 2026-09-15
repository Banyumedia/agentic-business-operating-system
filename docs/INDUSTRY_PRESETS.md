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
| **Sistem: Manajemen Asisten AI & Token** | `system.ai_agent` | ✅ ON | ✅ ON | ✅ ON | ✅ ON | ✅ ON | ✅ ON |

---

## 2. Struktur Menu & Dampak Visibilitas Sidebar

Registry menu terpusat di `app/Services/DynamicMenuRegistry.php`. Visibilitas dikendalikan secara deklaratif:

```php
return [
    [
        'id' => 'crm',
        'title' => 'CRM & Pelanggan',
        'icon' => 'users',
        'visible' => fn($company) => $company->hasAnyFeature(['crm.leads', 'crm.simple_phone', 'crm.patient_history']),
        'children' => [
            [
                'title' => 'Pipeline Leads',
                'route' => 'crm.leads.index',
                'visible' => fn($company) => $company->feature('crm.leads'),
            ],
            [
                'title' => 'Database Pelanggan',
                'route' => 'master-data.customers.index',
                'visible' => fn($company) => true, // Selalu ada
            ],
            [
                'title' => 'Riwayat Pasien & Resep',
                'route' => 'pharmacy.patients.index',
                'visible' => fn($company) => $company->feature('crm.patient_history'),
            ],
        ]
    ],
    [
        'id' => 'pos',
        'title' => 'Kasir & POS',
        'icon' => 'shopping-cart',
        'visible' => fn($company) => $company->hasAnyFeature(['pos.quick_counter', 'pos.open_bill_tables', 'pos.prescription_flow']),
        'children' => [
            [
                'title' => 'Layar Kasir',
                'route' => 'pos.index',
                'visible' => fn($company) => $company->feature('pos.quick_counter'),
            ],
            [
                'title' => 'Manajemen Meja / Bill',
                'route' => 'pos.tables.index',
                'visible' => fn($company) => $company->feature('pos.open_bill_tables'),
            ],
            [
                'title' => 'Antrean e-Resep',
                'route' => 'pharmacy.prescriptions.index',
                'visible' => fn($company) => $company->feature('pos.prescription_flow'),
            ],
        ]
    ],
    [
        'id' => 'operations',
        'title' => 'Operasional',
        'icon' => 'briefcase',
        'visible' => fn($company) => $company->hasAnyFeature(['ops.timesheet', 'ops.event_rundown', 'ops.contractor_spk', 'ops.rental_calendar']),
        'children' => [
            [
                'title' => 'Timesheet & Project Task',
                'route' => 'agency.timesheet.index',
                'visible' => fn($company) => $company->feature('ops.timesheet'),
            ],
            [
                'title' => 'Rundown & Event Matrix',
                'route' => 'eo.rundown.index',
                'visible' => fn($company) => $company->feature('ops.event_rundown'),
            ],
            [
                'title' => 'SPK & Opname Progres',
                'route' => 'contractor.spk.index',
                'visible' => fn($company) => $company->feature('ops.contractor_spk'),
            ],
            [
                'title' => 'Kalender Sewa & Booking',
                'route' => 'rental.calendar.index',
                'visible' => fn($company) => $company->feature('ops.rental_calendar'),
            ],
            [
                'title' => 'Check-in / Check-out Unit',
                'route' => 'rental.checkin.index',
                'visible' => fn($company) => $company->feature('ops.rental_checkin'),
            ],
        ]
    ],
    [
        'id' => 'settings',
        'title' => 'Pengaturan',
        'icon' => 'settings',
        'visible' => fn($company) => true,
        'children' => [
            [
                'title' => 'Scan Agen & Saldo Token',
                'route' => 'settings.ai-agent.index',
                'visible' => fn($company) => $company->feature('system.ai_agent'),
            ],
            [
                'title' => 'Otorisasi Staf WA',
                'route' => 'settings.staff-wa.index',
                'visible' => fn($company) => $company->feature('system.ai_agent'),
            ],
        ]
    ],
];
```

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
