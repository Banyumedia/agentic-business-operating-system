# DATA MODEL SPECIFICATION
## Skema Database Multi-Business Multi-Tenant (Clean / Canonical)

**Codebase:** Laravel, `D:\PROJECTS\agentic-bos`.

## 0. Kontrak Implementasi Migration (WAJIB)

Seluruh DDL di dokumen ini adalah **spesifikasi bentuk data**, bukan SQL yang
boleh disalin mentah ke migration.

**Keputusan B-01 (2026-09-16):** semua migration WAJIB ditulis memakai Laravel
Schema Builder yang portabel, bukan `DB::statement` dengan DDL MySQL mentah.

Alasan terverifikasi: `.env` dan `phpunit.xml` memakai SQLite, sedangkan
produksi memakai MySQL/MariaDB. SQLite tidak mendukung `ENUM` mentah dan
memiliki keterbatasan `ALTER TABLE`, sehingga DDL MySQL mentah akan gagal.

Aturan penerjemahan:

| Spesifikasi di dokumen | Implementasi migration |
|---|---|
| `BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY` | `$table->id()` |
| `BIGINT UNSIGNED` + FK | `$table->foreignId('x')->constrained()` |
| `ENUM('a','b')` | `$table->enum('kolom', ['a','b'])` |
| `DECIMAL(18,2)` | `$table->decimal('kolom', 18, 2)` |
| `JSON` | `$table->json('kolom')` |
| `TIMESTAMP NULL` | `$table->timestamps()` atau `$table->timestamp('x')->nullable()` |
| `ALTER TABLE ... ADD COLUMN` | migration `Schema::table()` dengan pengecekan `Schema::hasColumn()` |

Ketentuan tambahan:

- Setiap tabel bisnis wajib punya `company_id` dengan foreign key dan index.
- Dev dan test memakai SQLite agar cepat dan tanpa setup tambahan.
- Sebelum release, jalankan verifikasi paritas pada MySQL/MariaDB karena
  SQLite tidak menangkap seluruh perilaku MySQL.

---


## 1. Tabel Fondasi Tenant (WAJIB DIBUAT — belum ada di repo)

> **Koreksi 2026-09-16:** versi awal dokumen ini menyebut tabel-tabel di bawah
> "sudah ada" karena ditulis untuk codebase ERP Prime warisan. Repo canonical
> `D:\PROJECTS\agentic-bos` hanya memiliki `users`, `cache`, `jobs` bawaan Laravel.
> Semua tabel di bagian ini harus **dibuat dari nol** (task T-00a/T-00b di
> `EXECUTION_PLAN.md`). `ALTER TABLE` di sini adalah notasi spesifikasi; migration
> aktual membuat kolom tersebut langsung di `CREATE`.

### 1.1 `companies`
```sql
CREATE TABLE companies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(191) NOT NULL,
    slug VARCHAR(64) NOT NULL UNIQUE,      -- dipakai untuk tenant routing & Hermes profile
    owner_user_id BIGINT UNSIGNED NOT NULL,
    business_preset ENUM('agency','fnb','pharmacy','eo','contractor','rental','custom')
        NOT NULL DEFAULT 'custom',
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    CONSTRAINT fk_companies_owner FOREIGN KEY (owner_user_id) REFERENCES users(id)
);
```

### 1.2 `business_identities` (Base Tax Mode)
Konfigurasi pajak dasar berada pada `BusinessIdentity`, bukan pada `Company` atau shadow company. Semua transaksi fase dasar mengikuti mode identity yang dipilih. Override per dokumen/item dan multi-tax rate ditunda untuk add-on Enterprise (D-17).
```sql
CREATE TABLE business_identities (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    legal_name VARCHAR(191) NOT NULL,      -- nama di kop/NPWP
    npwp VARCHAR(32) NULL,
    address TEXT NULL,
    price_includes_tax BOOLEAN NOT NULL DEFAULT FALSE,
    tax_mode ENUM('taxable','non_taxable') NOT NULL DEFAULT 'non_taxable',
    tax_rate DECIMAL(5,2) NULL DEFAULT 0.00, -- Contoh: 11.00 untuk PPN
    is_default BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_business_identities_company (company_id),
    CONSTRAINT fk_business_identities_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);
```

### 1.3 `module_settings` (bentuk mengikuti D-19 / D-25)
Satu baris per `(company_id, module_name)`. Feature flag disimpan di modul bernama `features`.
```sql
CREATE TABLE module_settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    module_name VARCHAR(64) NOT NULL,      -- 'features' | 'crm' | 'pos' | 'ai_agent' | ...
    settings_json JSON NOT NULL,           -- untuk module_name='features': {"crm.leads": true, "pos.quick_counter": false}
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY uq_module_settings_company_module (company_id, module_name),
    CONSTRAINT fk_module_settings_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);
```
Resolusi `Company::feature($key)`: baca `module_settings` baris `features` → jika key ada di `settings_json`, kembalikan nilainya; jika tidak, ambil default dari `business_presets.default_features` sesuai `companies.business_preset`.

### 1.4 `users` (Otorisasi WhatsApp + konteks tenant aktif)
```sql
ALTER TABLE users
  ADD COLUMN wa_number VARCHAR(32) NULL,
  ADD COLUMN wa_is_verified BOOLEAN NOT NULL DEFAULT FALSE,
  ADD COLUMN current_company_id BIGINT UNSIGNED NULL; -- Q-08: konteks tenant aktif
```
Ini satu-satunya `ALTER` nyata di dokumen ini karena `users` memang sudah ada.

---

## 2. Tabel Baru — Kernel

### 2.1 `business_presets`
```sql
CREATE TABLE business_presets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    key VARCHAR(32) NOT NULL UNIQUE,     -- agency | fnb | pharmacy | eo | contractor | rental | custom
    name VARCHAR(64) NOT NULL,
    description TEXT NULL,
    default_features JSON NOT NULL,      -- map {"crm.leads": true, ...} — bukan array
    is_system BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```
Seeder mengisi **7 baris**: 6 industri (flag dari matriks `INDUSTRY_PRESETS.md` §1) + `custom` (semua flag `false` kecuali `system.ai_agent = true`).

### 2.2 `support_tickets` (Master Bot CS)
Tabel untuk mencatat keluhan Bos yang diterima oleh Agen Pusat (BOS Care). Data ini diakses di level platform (bukan per-tenant).
```sql
CREATE TABLE support_tickets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    reported_by_user_id BIGINT UNSIGNED NOT NULL,
    issue_category VARCHAR(32) NULL,     -- technical | billing | feature_request
    issue_description TEXT NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'open', -- open | in_progress | resolved
    resolution_note TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id),
    FOREIGN KEY (reported_by_user_id) REFERENCES users(id)
);
```

### 2.3 `invoices` (Top-up Token DAN Subscription — D-23)
Satu tabel untuk dua jenis tagihan. `token_amount_granted` hanya terisi untuk `type = 'topup'`. Diperbarui otomatis oleh webhook payment gateway (T-19).
```sql
CREATE TABLE invoices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    company_membership_id BIGINT UNSIGNED NULL, -- wajib untuk type='subscription'
    type ENUM('topup','subscription') NOT NULL,
    order_id VARCHAR(64) NOT NULL UNIQUE, -- ID referensi ke payment gateway
    amount DECIMAL(18,2) NOT NULL,
    token_amount_granted BIGINT NULL,     -- hanya untuk topup
    period_start DATE NULL,               -- hanya untuk subscription
    period_end DATE NULL,                 -- hanya untuk subscription
    payment_status ENUM('pending', 'paid', 'expired', 'failed') NOT NULL DEFAULT 'pending',
    payment_url VARCHAR(255) NULL,       -- Link Snap / QRIS
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_invoices_company (company_id),
    CONSTRAINT fk_invoices_company FOREIGN KEY (company_id) REFERENCES companies(id),
    CONSTRAINT fk_invoices_membership FOREIGN KEY (company_membership_id) REFERENCES company_memberships(id)
);
```
Transisi status yang sah: `pending → paid`, `pending → expired`, `pending → failed`. Tidak ada transisi keluar dari `paid`. Webhook yang datang untuk invoice `paid` harus dianggap replay dan diabaikan tanpa efek samping.

### 2.4 `ai_model_pricings` (Sistem Base Token Multiplier)
Tabel rahasia internal platform untuk memetakan harga modal model AI (seperti GPT-4o, Claude) terhadap mata uang *BOS Token*.
```sql
CREATE TABLE ai_model_pricings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    model_name VARCHAR(64) NOT NULL UNIQUE, -- cth: gpt-4o, llama-3-8b
    input_multiplier DECIMAL(8,2) NOT NULL DEFAULT 1.00,  -- Pengali untuk token Input
    output_multiplier DECIMAL(8,2) NOT NULL DEFAULT 3.00, -- Pengali untuk token Output
    is_active BOOLEAN NOT NULL DEFAULT true,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

---

## 3. Tabel CRM (Pemisahan Kontak & Transaksi)

### 3.1 `crm_contacts` (Data Orang/Biodata)
Menggantikan rancangan `crm_leads` lama. Berfungsi murni sebagai buku alamat pintar.
```sql
CREATE TABLE crm_contacts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    identity_id BIGINT UNSIGNED NULL,
    name VARCHAR(191) NOT NULL,
    wa_number VARCHAR(32) NULL,
    email VARCHAR(191) NULL,
    source VARCHAR(32) NULL,             -- wa | referral | event | manual
    metadata JSON NULL,                  -- catatan hobi, jabatan, dll
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_crm_contacts_company (company_id)
);
```

### 3.2 `crm_deals` (Peluang Uang/Proyek)
Satu entitas `crm_contacts` bisa memiliki banyak *Deals* (Proyek).
```sql
CREATE TABLE crm_deals (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    contact_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(191) NOT NULL,         -- cth: Proyek Website e-Commerce
    stage VARCHAR(32) NOT NULL DEFAULT 'new', -- new | in_progress | won | lost
    deal_value DECIMAL(18,2) NULL,
    expected_close_date DATE NULL,
    metadata JSON NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_crm_deals_company (company_id),
    CONSTRAINT fk_crm_deals_contact FOREIGN KEY (contact_id) REFERENCES crm_contacts(id) ON DELETE CASCADE
);
```

### 3.3 `crm_activity_logs` (Histori Aktivitas Ganda)
```sql
CREATE TABLE crm_activity_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    contact_id BIGINT UNSIGNED NULL,     -- Bisa nempel di profil orangnya
    deal_id BIGINT UNSIGNED NULL,        -- Bisa nempel di profil proyeknya
    type ENUM('call','wa','meeting','email','note','stage_change') NOT NULL,
    content TEXT NULL,
    user_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    INDEX idx_crm_activity_company (company_id),
    CONSTRAINT fk_crm_activity_contact FOREIGN KEY (contact_id) REFERENCES crm_contacts(id) ON DELETE CASCADE,
    CONSTRAINT fk_crm_activity_deal FOREIGN KEY (deal_id) REFERENCES crm_deals(id) ON DELETE CASCADE
);
```

---

## 4. Tabel Baru — Modul Akuntansi (Keuangan Inti)

Pencatatan keuangan di-backend *selalu* menggunakan *Double-Entry* (Jurnal). UI/UX di frontend menyesuaikan mode perusahaan (Simple Cashbook vs Pro Accounting).

### 4.1 `chart_of_accounts` (Bagan Akun)
```sql
CREATE TABLE chart_of_accounts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    account_code VARCHAR(32) NOT NULL,
    name VARCHAR(191) NOT NULL,
    type ENUM('asset','liability','equity','revenue','expense') NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_coa_company (company_id)
);
```

### 4.2 `accounting_journals` (Buku Jurnal)
```sql
CREATE TABLE accounting_journals (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    journal_number VARCHAR(64) NOT NULL,
    transaction_date DATE NOT NULL,
    reference VARCHAR(191) NULL,         -- Nomor Invoice / Nota
    description TEXT NULL,               -- Keterangan transaksi
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_journal_company (company_id)
);
```

### 4.3 `accounting_journal_lines` (Baris Jurnal Debit/Kredit)
`company_id` didenormalisasi dari parent sesuai D-26 agar isolasi tenant tidak bergantung pada join.
```sql
CREATE TABLE accounting_journal_lines (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    journal_id BIGINT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    deal_id BIGINT UNSIGNED NULL, -- Untuk melacak Laba/Rugi per Proyek (Project Costing)
    debit DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    credit DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    description TEXT NULL,
    created_at TIMESTAMP NULL,
    INDEX idx_journal_line_company (company_id),
    INDEX idx_journal_line (journal_id),
    INDEX idx_journal_line_deal (deal_id),
    CONSTRAINT fk_journal_line_company FOREIGN KEY (company_id) REFERENCES companies(id),
    CONSTRAINT fk_journal_line_journal FOREIGN KEY (journal_id) REFERENCES accounting_journals(id) ON DELETE CASCADE,
    CONSTRAINT fk_journal_line_account FOREIGN KEY (account_id) REFERENCES chart_of_accounts(id),
    CONSTRAINT fk_journal_line_deal FOREIGN KEY (deal_id) REFERENCES crm_deals(id) ON DELETE SET NULL
);
```
Invarian wajib (ditegakkan di service layer + test): untuk setiap `journal_id`, `SUM(debit) = SUM(credit)`. Karena FK ke `crm_deals`, migration tabel ini harus berjalan **setelah** T-13.

---

## 5. Modul HRD (Sumber Daya Manusia)

### 5.1 `hr_employees`
```sql
CREATE TABLE hr_employees (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(191) NOT NULL,
    position VARCHAR(191) NULL,
    base_salary DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_hr_employees_company (company_id)
);
```

### 5.2 `hr_payrolls`
```sql
CREATE TABLE hr_payrolls (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id BIGINT UNSIGNED NOT NULL,
    period_month VARCHAR(7) NOT NULL, -- format: YYYY-MM
    gross_salary DECIMAL(18,2) NOT NULL,
    deductions DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    net_salary DECIMAL(18,2) NOT NULL,
    status ENUM('draft', 'paid') NOT NULL DEFAULT 'draft',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_hr_payrolls_employee (employee_id),
    CONSTRAINT fk_hr_payrolls_employee FOREIGN KEY (employee_id) REFERENCES hr_employees(id) ON DELETE CASCADE
);
```

### 5.3 `ai_reminders`
```sql
CREATE TABLE ai_reminders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL, -- Siapa yang harus diingatkan (Bos / Staf)
    task_description TEXT NOT NULL,
    remind_at DATETIME NOT NULL,
    is_completed BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_ai_reminders_company (company_id)
);
```

---

## 6. Tabel Baru — Apotek

### 6.1 `pharmacy_patients`
```sql
CREATE TABLE pharmacy_patients (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    crm_contact_id BIGINT UNSIGNED NULL,
    name VARCHAR(191) NOT NULL,
    wa_number VARCHAR(32) NULL,
    allergies JSON NULL,
    chronic_conditions JSON NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_pharmacy_patients_company (company_id),
    CONSTRAINT fk_pharmacy_patients_contact FOREIGN KEY (crm_contact_id) REFERENCES crm_contacts(id) ON DELETE SET NULL
);
```

### 6.2 `pharmacy_prescriptions`
Foto resep **tidak** disimpan di kolom lokal (D-29 / BYOS). Gunakan baris `attachments` dengan `attachable_type = App\Models\PharmacyPrescription`.
```sql
CREATE TABLE pharmacy_prescriptions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    patient_id BIGINT UNSIGNED NULL,
    doctor_name VARCHAR(191) NULL,
    doctor_sip VARCHAR(64) NULL,
    extracted_data JSON NULL,            -- [{drug, qty, dosage, signa}]
    status ENUM('draft','pharmacist_verify','verified','served','cancelled') NOT NULL DEFAULT 'draft',
    verified_by BIGINT UNSIGNED NULL,    -- apoteker
    served_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_pharmacy_prescriptions_company (company_id),
    CONSTRAINT fk_pharmacy_prescriptions_patient FOREIGN KEY (patient_id) REFERENCES pharmacy_patients(id) ON DELETE SET NULL
);
```

---

## 7. Tabel Baru — POS

### 7.1 `pos_shifts`
```sql
CREATE TABLE pos_shifts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    outlet_id BIGINT UNSIGNED NULL,
    cashier_id BIGINT UNSIGNED NOT NULL,
    opening_float DECIMAL(18,2) NOT NULL DEFAULT 0,
    actual_cash DECIMAL(18,2) NOT NULL DEFAULT 0,
    variance DECIMAL(14,2) NOT NULL DEFAULT 0,
    opened_at TIMESTAMP NOT NULL,
    closed_at TIMESTAMP NULL,
    INDEX idx_pos_shifts_company (company_id)
);
```

### 7.2 `pos_bills`
```sql
CREATE TABLE pos_bills (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    shift_id BIGINT UNSIGNED NOT NULL,
    table_id BIGINT UNSIGNED NULL,
    status ENUM('open','paid','cancelled') NOT NULL DEFAULT 'open',
    opened_at TIMESTAMP NOT NULL,
    closed_at TIMESTAMP NULL,
    INDEX idx_pos_bills_company (company_id),
    CONSTRAINT fk_pos_bills_shift FOREIGN KEY (shift_id) REFERENCES pos_shifts(id) ON DELETE CASCADE
);
```

---

## 8. Tabel Baru — Rental (Persewaan)

### 8.1 `rental_units`
```sql
CREATE TABLE rental_units (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(191) NOT NULL,
    category VARCHAR(64) NOT NULL,
    status ENUM('available','rented','maintenance','retired') NOT NULL DEFAULT 'available',
    condition_notes TEXT NULL,
    purchase_cost DECIMAL(18,2) NULL,
    acquired_date DATE NULL,
    metadata JSON NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_rental_units_company (company_id)
);
```

### 8.2 `rental_bookings`
```sql
CREATE TABLE rental_bookings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    unit_id BIGINT UNSIGNED NOT NULL,
    crm_contact_id BIGINT UNSIGNED NOT NULL,
    renter_identity JSON NULL,           -- {ktp, address}
    pickup_datetime DATETIME NOT NULL,
    return_datetime DATETIME NOT NULL,
    actual_return DATETIME NULL,
    daily_rate DECIMAL(18,2) NOT NULL,
    deposit_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
    late_fee_per_hour DECIMAL(18,2) NOT NULL DEFAULT 0,
    late_fee_total DECIMAL(18,2) NOT NULL DEFAULT 0,
    status ENUM('draft','confirmed','out','returned','overdue','cancelled') NOT NULL DEFAULT 'draft',
    metadata JSON NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_rental_bookings_company (company_id),
    CONSTRAINT fk_rental_bookings_unit FOREIGN KEY (unit_id) REFERENCES rental_units(id),
    CONSTRAINT fk_rental_bookings_contact FOREIGN KEY (crm_contact_id) REFERENCES crm_contacts(id)
);
```

### 8.3 `rental_incidents`
```sql
CREATE TABLE rental_incidents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    booking_id BIGINT UNSIGNED NOT NULL,
    type ENUM('damage','loss','late','other') NOT NULL,
    description TEXT NULL,
    charge_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL,
    INDEX idx_rental_incidents_company (company_id),
    CONSTRAINT fk_rental_incidents_booking FOREIGN KEY (booking_id) REFERENCES rental_bookings(id)
);
```

---

## 9. Tabel Baru — Event Organizer

### 9.1 `eo_events`
```sql
CREATE TABLE eo_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(191) NOT NULL,
    event_date DATE NOT NULL,
    venue VARCHAR(191) NULL,
    status ENUM('draft','planned','running','done','cancelled') NOT NULL DEFAULT 'draft',
    budget DECIMAL(18,2) NULL,
    metadata JSON NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_eo_events_company (company_id)
);
```

### 9.2 `eo_vendors`
```sql
CREATE TABLE eo_vendors (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    event_id BIGINT UNSIGNED NOT NULL,
    vendor_name VARCHAR(191) NOT NULL,
    service_type VARCHAR(64) NULL,       -- sound | lighting | stage | catering | talent
    fee DECIMAL(18,2) NULL,
    payment_status ENUM('unpaid','partial','paid') NOT NULL DEFAULT 'unpaid',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_eo_vendors_company (company_id),
    CONSTRAINT fk_eo_vendors_event FOREIGN KEY (event_id) REFERENCES eo_events(id) ON DELETE CASCADE
);
```

### 9.3 `eo_crew_assignments`
```sql
CREATE TABLE eo_crew_assignments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    event_id BIGINT UNSIGNED NOT NULL,
    crew_name VARCHAR(191) NOT NULL,
    role VARCHAR(64) NULL,
    assignment_time DATETIME NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_eo_crew_company (company_id),
    CONSTRAINT fk_eo_crew_event FOREIGN KEY (event_id) REFERENCES eo_events(id) ON DELETE CASCADE
);
```

---

## 10. Tabel Baru — Kontraktor (Retensi)

### 10.1 `contractor_retentions`
```sql
CREATE TABLE contractor_retentions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    project_id BIGINT UNSIGNED NOT NULL,
    invoice_id BIGINT UNSIGNED NULL,
    retention_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
    release_date DATE NULL,
    status ENUM('held','released') NOT NULL DEFAULT 'held',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_contractor_retentions_company (company_id)
);
```
> Kontraktor memakai ulang `project_center` + `subcontractor_spk` yang sudah ada di codebase; tabel retensi ini hanya untuk mencatat dana retensi tertahan dan jadwal rilisnya.

---

## 11. Tabel Baru — Komersial SaaS & Membership (Align D-05)

### 11.1 `membership_plans`
```sql
CREATE TABLE membership_plans (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(64) NOT NULL,            -- Starter, Pro, Enterprise
    slug VARCHAR(64) NOT NULL UNIQUE,
    monthly_price DECIMAL(18,2) NOT NULL DEFAULT 0,
    annual_price DECIMAL(18,2) NOT NULL DEFAULT 0,
    max_wa_groups INT NOT NULL DEFAULT 1,
    monthly_token_quota BIGINT NOT NULL DEFAULT 500000,
    allowed_presets JSON NULL,
    features JSON NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

### 11.2 `company_memberships`
```sql
CREATE TABLE company_memberships (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    plan_id BIGINT UNSIGNED NOT NULL,
    status ENUM('trial','active','past_due','cancelled') NOT NULL DEFAULT 'trial',
    starts_at TIMESTAMP NOT NULL,
    expires_at TIMESTAMP NULL,
    max_wa_groups INT NOT NULL DEFAULT 1,
    monthly_token_quota BIGINT NOT NULL DEFAULT 500000,
    current_token_balance BIGINT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_memberships_company (company_id),
    CONSTRAINT fk_memberships_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_memberships_plan FOREIGN KEY (plan_id) REFERENCES membership_plans(id)
);
```

### 11.3 `token_ledger_entries` (Audit Saldo Token)
Saldo pada `company_memberships.current_token_balance` adalah cache operasional;
setiap kredit/debit wajib mempunyai baris ledger yang dapat direkonsiliasi. Webhook
payment dan pemakaian model harus memakai `idempotency_key` unik agar event yang
diulang tidak dapat mengubah saldo dua kali.
```sql
CREATE TABLE token_ledger_entries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    company_membership_id BIGINT UNSIGNED NOT NULL,
    direction ENUM('credit','debit') NOT NULL,
    amount BIGINT NOT NULL,
    balance_after BIGINT NOT NULL,
    source VARCHAR(32) NOT NULL, -- subscription | topup | inference | refund | adjustment
    idempotency_key VARCHAR(128) NOT NULL UNIQUE,
    reference_type VARCHAR(64) NULL,
    reference_id VARCHAR(128) NULL,
    provider VARCHAR(64) NULL,
    model VARCHAR(128) NULL,
    metadata JSON NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_token_ledger_company_created (company_id, created_at),
    CONSTRAINT fk_token_ledger_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_token_ledger_membership FOREIGN KEY (company_membership_id) REFERENCES company_memberships(id) ON DELETE CASCADE
);
```


---

## 12. Tabel Baru — Integrasi Hermes White-label

### 12.1 `hermes_nodes` (Node Registry / Load Balancer)
```sql
CREATE TABLE hermes_nodes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(191) NOT NULL,            -- Misal: "Hermes Node 01 (Singapore)"
    api_url VARCHAR(191) NOT NULL,         -- Endpoint rahasia server Hermes tersebut
    api_secret_reference VARCHAR(191) NOT NULL, -- Referensi secret manager, bukan secret plaintext
    max_capacity INT NOT NULL DEFAULT 100, -- Batas maksimal klien
    active_profiles INT NOT NULL DEFAULT 0,-- Jumlah klien saat ini
    status ENUM('active', 'maintenance', 'down') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

### 12.2 `hermes_profiles`
```sql
CREATE TABLE hermes_profiles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL UNIQUE,
    node_id BIGINT UNSIGNED NULL, -- Referensi ke Node tempat instance ini berjalan
    instance_id VARCHAR(128) NOT NULL UNIQUE,
    webhook_secret_reference VARCHAR(191) NOT NULL, -- Referensi secret manager, bukan secret plaintext
    status ENUM('unpaired','connected','disconnected') NOT NULL DEFAULT 'unpaired',
    last_ping_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    CONSTRAINT fk_hermes_profiles_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_hermes_profiles_node FOREIGN KEY (node_id) REFERENCES hermes_nodes(id) ON DELETE RESTRICT
);
```

## 13. Manajemen Penyimpanan File (Google Drive BYOS)

### 13.1 `attachments` (Tabel Polimorfik Sapu Jagat)
```sql
CREATE TABLE attachments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    attachable_type VARCHAR(191) NOT NULL, -- Nama Model Laravel (misal: App\Models\CrmDeal)
    attachable_id BIGINT UNSIGNED NOT NULL, -- ID dari record tersebut
    file_name VARCHAR(255) NOT NULL,       -- Nama asli file (misal: KTP_Budi.pdf)
    file_type VARCHAR(50) NOT NULL,        -- image/jpeg, application/pdf
    drive_file_id VARCHAR(191) NOT NULL,   -- ID File di Google Drive
    drive_url TEXT NOT NULL,               -- Link untuk melihat/download file
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_attachments_company (company_id),
    INDEX idx_attachments_polymorphic (attachable_type, attachable_id),
    CONSTRAINT fk_attachments_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);
```

---

## 14. Uji Kebenaran Data
Setiap tabel baru WAJIB punya `company_id` (FK ke `companies`) untuk scoping tenant. Test isolasi tenant A vs B wajib ditulis untuk setiap modul.
