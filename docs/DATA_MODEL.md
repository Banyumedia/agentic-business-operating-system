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
    business_preset VARCHAR(32) NOT NULL DEFAULT 'custom', -- FK logis ke business_presets.key; VARCHAR (bukan ENUM) agar preset ke-N tidak butuh migration (D-31)
    theme VARCHAR(8) NOT NULL DEFAULT 'a',  -- D-43: tema per-USAHA (a|b|c|d|e), di-set owner, berlaku untuk semua staf company ini. Bukan preferensi per user.
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    CONSTRAINT fk_companies_owner FOREIGN KEY (owner_user_id) REFERENCES users(id),
    CONSTRAINT fk_companies_preset FOREIGN KEY (business_preset) REFERENCES business_presets(`key`)
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
Satu baris per `(company_id, module_name)`. Override company di atas preset (D-31).
```sql
CREATE TABLE module_settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    module_name VARCHAR(64) NOT NULL,      -- 'features' | 'terminology' | 'workflows' | 'dashboard' | 'ai_agent' | ...
    settings_json JSON NOT NULL,           -- features: {"bookings": true}; terminology: {"contact":"Jemaah"}; workflows: {"deal": {...}}
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY uq_module_settings_company_module (company_id, module_name),
    CONSTRAINT fk_module_settings_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);
```
Resolusi `Company::feature($key)`: `module_settings[features].settings_json[$key]`
→ jika tidak ada, `business_presets.definition.capabilities[$key]` → default `false`.
Resolusi `term($key)`, workflow, dan dashboard mengikuti urutan yang sama
(company → preset → default global).

### 1.4 `users` (Otorisasi WhatsApp + konteks tenant aktif)
```sql
ALTER TABLE users
  ADD COLUMN wa_number VARCHAR(32) NULL,
  ADD COLUMN wa_is_verified BOOLEAN NOT NULL DEFAULT FALSE,
  ADD COLUMN current_company_id BIGINT UNSIGNED NULL; -- D-41: konteks tenant aktif
```
Ini satu-satunya `ALTER` nyata di dokumen ini karena `users` memang sudah ada.

### 1.5 `workflow_definitions` (D-31c — alur bisnis sebagai data)
Definisi stage/transisi per entity per company. Baris ini adalah **materialisasi**
dari `preset.definition.workflows` + override `module_settings[workflows]`,
dibuat saat company memilih preset agar query runtime cepat dan dapat diaudit.
```sql
CREATE TABLE workflow_definitions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    entity VARCHAR(64) NOT NULL,           -- 'deal' | 'project' | 'booking' | 'order' | 'prescription' | ...
    version INT UNSIGNED NOT NULL DEFAULT 1,
    definition JSON NOT NULL,              -- {stages:[{code,label}], transitions:[{from,to,roles,effects,requires_approval}]}
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY uq_workflow_company_entity_version (company_id, entity, version),
    CONSTRAINT fk_workflow_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);
```
`WorkflowEngine::transition($model, $toStage, $actor)` memvalidasi transisi
terhadap definisi aktif, memeriksa role, membuat tiket approval bila
`requires_approval`, lalu menjalankan `effects` (katalog di `INDUSTRY_PRESETS.md` §5)
dalam satu `DB::transaction`. Entity yang memakai workflow menyimpan `stage`
sebagai `VARCHAR(32)` kode netral, **bukan** `ENUM`, agar preset bebas
menentukan stage.

### 1.6 `workflow_transitions_log` (audit alur)
```sql
CREATE TABLE workflow_transitions_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    entity VARCHAR(64) NOT NULL,
    entity_id BIGINT UNSIGNED NOT NULL,
    from_stage VARCHAR(32) NULL,
    to_stage VARCHAR(32) NOT NULL,
    actor_user_id BIGINT UNSIGNED NULL,   -- NULL = system/AI
    approval_ticket_id BIGINT UNSIGNED NULL,
    effects_run JSON NULL,
    created_at TIMESTAMP NULL,
    INDEX idx_wf_log_company_entity (company_id, entity, entity_id),
    CONSTRAINT fk_wf_log_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);
```

### 1.7 Konvensi `attributes JSON` (D-31e)
Entitas generik (`contacts`, `resources`, `projects`, `items`) membawa kolom
`type VARCHAR(32)` + `attributes JSON` untuk data spesifik industri yang tidak
punya aturan bisnis sendiri. Contoh: `contacts.attributes = {"allergies": [...],
"chronic_conditions": [...]}` untuk pasien; `resources.attributes = {"plate_no":
"B 1234 XY", "seats": 7}` untuk mobil sewa. Kolom nyata **hanya** dibuat bila
di-query/di-index/di-hitung oleh aturan bisnis.

---

## 2. Tabel Baru — Kernel

### 2.1 `business_presets` (D-31b — preset adalah data)
```sql
CREATE TABLE business_presets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(32) NOT NULL UNIQUE,   -- agency | fnb | pharmacy | eo | contractor | rental | custom | klinik | salon | ...
    name VARCHAR(64) NOT NULL,
    description TEXT NULL,
    tier CHAR(1) NOT NULL DEFAULT 'A',   -- A = komposisi murni | B = memakai modul Tier B (D-33)
    definition JSON NOT NULL,            -- {capabilities, terminology, workflows, dashboard, menus} — skema di INDUSTRY_PRESETS.md §2
    definition_version INT UNSIGNED NOT NULL DEFAULT 1,
    is_system BOOLEAN NOT NULL DEFAULT TRUE,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```
Seeder membaca `database/seeders/presets/*.json` (satu file per preset) dan
**memvalidasi** setiap `definition` terhadap katalog kapabilitas, kamus istilah,
katalog efek, dan katalog widget sebelum menyimpan. Menambah industri baru =
menambah satu file JSON. Seeder awal: 7 preset (6 industri + `custom`).

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

## 3. Kapabilitas `contacts` & `deals` (generik lintas industri — D-31)

Menggantikan `crm_*`. Tabel ini melayani Klien (agency), Pasien (klinik), Penyewa
(rental), Tamu (resto), Siswa (kursus) — hanya `type`, `attributes`, dan `term()`
yang berbeda.

### 3.1 `contacts`
```sql
CREATE TABLE contacts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    business_identity_id BIGINT UNSIGNED NULL,
    type VARCHAR(32) NOT NULL DEFAULT 'customer', -- customer | patient | tenant | student | vendor | lead ...
    name VARCHAR(191) NOT NULL,
    wa_number VARCHAR(32) NULL,
    email VARCHAR(191) NULL,
    source VARCHAR(32) NULL,             -- wa | referral | event | manual | nalarpesan
    tags JSON NULL,
    attributes JSON NULL,                -- industri-spesifik tanpa aturan: allergies, plate_no, id_card_no, dll (§1.7)
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_contacts_company_type (company_id, type),
    INDEX idx_contacts_company_wa (company_id, wa_number),
    CONSTRAINT fk_contacts_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);
```

### 3.2 `deals` (kapabilitas `deals`)
Peluang/pendaftaran/kunjungan yang melewati stage. `stage` adalah kode netral dari
`workflow_definitions[entity='deal']`, **bukan** ENUM (D-38).
```sql
CREATE TABLE deals (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    contact_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(191) NOT NULL,
    stage VARCHAR(32) NOT NULL DEFAULT 'new',
    value DECIMAL(18,2) NULL,
    expected_close_date DATE NULL,
    owner_user_id BIGINT UNSIGNED NULL,
    attributes JSON NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_deals_company_stage (company_id, stage),
    CONSTRAINT fk_deals_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_deals_contact FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE
);
```

### 3.3 `activity_logs` (polimorfik — histori interaksi)
```sql
CREATE TABLE activity_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    subject_type VARCHAR(191) NOT NULL,  -- App\Models\Contact | Deal | Project | Booking ...
    subject_id BIGINT UNSIGNED NOT NULL,
    type VARCHAR(32) NOT NULL,           -- call | wa | meeting | email | note | stage_change | system
    content TEXT NULL,
    user_id BIGINT UNSIGNED NULL,        -- NULL = AI/system
    created_at TIMESTAMP NULL,
    INDEX idx_activity_company_subject (company_id, subject_type, subject_id),
    CONSTRAINT fk_activity_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
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
    project_id BIGINT UNSIGNED NULL, -- Laba/Rugi per proyek (project costing) — generik, bukan hanya deal
    debit DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    credit DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    description TEXT NULL,
    created_at TIMESTAMP NULL,
    INDEX idx_journal_line_company (company_id),
    INDEX idx_journal_line (journal_id),
    INDEX idx_journal_line_project (project_id),
    CONSTRAINT fk_journal_line_company FOREIGN KEY (company_id) REFERENCES companies(id),
    CONSTRAINT fk_journal_line_journal FOREIGN KEY (journal_id) REFERENCES accounting_journals(id) ON DELETE CASCADE,
    CONSTRAINT fk_journal_line_account FOREIGN KEY (account_id) REFERENCES chart_of_accounts(id),
    CONSTRAINT fk_journal_line_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL
);
```
Invarian wajib (ditegakkan di service layer + test): untuk setiap `journal_id`, `SUM(debit) = SUM(credit)`. Karena FK ke `projects`, migration tabel ini berjalan **setelah** tabel `projects` (T-13b).

---

## 5. Kapabilitas `projects` (generik: Proyek / Event / Work Order / Kasus)

Menggantikan `eo_events`, `project_center`, dan sebagian `contractor_*`.

### 5.1 `projects`
```sql
CREATE TABLE projects (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    contact_id BIGINT UNSIGNED NULL,     -- pemberi kerja / klien
    deal_id BIGINT UNSIGNED NULL,        -- asal peluang, bila ada
    type VARCHAR(32) NOT NULL DEFAULT 'project', -- project | event | work_order | case
    name VARCHAR(191) NOT NULL,
    stage VARCHAR(32) NOT NULL DEFAULT 'planned', -- dari workflow_definitions[entity='project']
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    venue VARCHAR(191) NULL,
    budget DECIMAL(18,2) NULL,
    progress_pct DECIMAL(5,2) NOT NULL DEFAULT 0, -- dipakai projects.progress_billing
    owner_user_id BIGINT UNSIGNED NULL,
    attributes JSON NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_projects_company_stage (company_id, stage),
    CONSTRAINT fk_projects_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_projects_contact FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE SET NULL,
    CONSTRAINT fk_projects_deal FOREIGN KEY (deal_id) REFERENCES deals(id) ON DELETE SET NULL
);
```

### 5.2 `project_milestones` (kapabilitas `milestone_billing` / `projects.progress_billing`)
Termin DP/pelunasan (agency, EO) **dan** opname progres (kontraktor) — struktur sama.
```sql
CREATE TABLE project_milestones (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    project_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(191) NOT NULL,          -- "DP 50%", "Opname 35%", "Serah Terima"
    sequence INT UNSIGNED NOT NULL DEFAULT 1,
    trigger_type VARCHAR(32) NOT NULL,   -- fixed_pct | progress_pct | date | manual
    trigger_value DECIMAL(8,2) NULL,     -- 50.00 (persen) atau NULL
    amount DECIMAL(18,2) NOT NULL,
    retention_pct DECIMAL(5,2) NOT NULL DEFAULT 0, -- >0 hanya bila construction.retention
    invoice_id BIGINT UNSIGNED NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'pending', -- pending | invoiced | paid
    achieved_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_milestones_company_project (company_id, project_id),
    CONSTRAINT fk_milestones_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_milestones_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);
```

### 5.3 `project_assignments` (crew / staf / PIC per proyek)
Menggantikan `eo_crew_assignments`; dipakai juga `timesheet`.
```sql
CREATE TABLE project_assignments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    project_id BIGINT UNSIGNED NOT NULL,
    employee_id BIGINT UNSIGNED NULL,    -- FK ke employees bila hr.employees
    assignee_name VARCHAR(191) NULL,     -- freelancer tanpa record employee
    role VARCHAR(64) NULL,
    scheduled_at DATETIME NULL,
    hourly_cost DECIMAL(18,2) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_assign_company_project (company_id, project_id),
    CONSTRAINT fk_assign_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_assign_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);
```

### 5.4 `project_vendors` (vendor / subkon per proyek)
Menggantikan `eo_vendors` dan SPK subkon.
```sql
CREATE TABLE project_vendors (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    project_id BIGINT UNSIGNED NOT NULL,
    vendor_contact_id BIGINT UNSIGNED NULL, -- contacts.type='vendor'
    vendor_name VARCHAR(191) NOT NULL,
    service_type VARCHAR(64) NULL,       -- sound | catering | subkon_sipil | ...
    fee DECIMAL(18,2) NULL,
    payment_status VARCHAR(32) NOT NULL DEFAULT 'unpaid', -- unpaid | partial | paid
    attributes JSON NULL,                -- nomor SPK, lingkup, dll
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_pvendors_company_project (company_id, project_id),
    CONSTRAINT fk_pvendors_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_pvendors_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);
```

### 5.5 `timesheet_entries` (kapabilitas `timesheet`)
```sql
CREATE TABLE timesheet_entries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    project_id BIGINT UNSIGNED NULL,
    employee_id BIGINT UNSIGNED NOT NULL,
    work_date DATE NOT NULL,
    hours DECIMAL(5,2) NOT NULL,
    note TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_ts_company_employee_date (company_id, employee_id, work_date),
    CONSTRAINT fk_ts_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);
```

---

## 6. Kapabilitas `bookings` & `scheduling` (generik: Sewa / Reservasi / Janji Temu / Jadwal)

Menggantikan `rental_*` dan rundown EO. Satu mesin anti-double-booking untuk
mobil sewa, kamar kos, meja resto, kursi salon, lapangan futsal, ruang meeting.

### 6.1 `resources` (unit yang bisa di-booking)
```sql
CREATE TABLE resources (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    type VARCHAR(32) NOT NULL,           -- vehicle | room | table | seat | equipment | court | staff_slot
    name VARCHAR(191) NOT NULL,
    category VARCHAR(64) NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'available', -- available | in_use | maintenance | retired
    capacity INT UNSIGNED NULL,
    rate_amount DECIMAL(18,2) NULL,
    rate_unit VARCHAR(16) NULL,          -- hour | day | month | session
    condition_notes TEXT NULL,
    attributes JSON NULL,                -- plate_no, floor, seats, purchase_cost, acquired_date ...
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_resources_company_type (company_id, type),
    CONSTRAINT fk_resources_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);
```

### 6.2 `bookings`
```sql
CREATE TABLE bookings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    resource_id BIGINT UNSIGNED NOT NULL,
    contact_id BIGINT UNSIGNED NULL,
    project_id BIGINT UNSIGNED NULL,     -- rundown event: booking ber-parent ke project
    type VARCHAR(32) NOT NULL DEFAULT 'booking', -- booking | appointment | rundown_item | shift
    stage VARCHAR(32) NOT NULL DEFAULT 'draft', -- dari workflow_definitions[entity='booking']
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,
    actual_ends_at DATETIME NULL,
    rate_amount DECIMAL(18,2) NULL,
    deposit_amount DECIMAL(18,2) NOT NULL DEFAULT 0,      -- bookings.deposit
    late_fee_per_unit DECIMAL(18,2) NOT NULL DEFAULT 0,   -- bookings.deposit
    late_fee_total DECIMAL(18,2) NOT NULL DEFAULT 0,      -- dihitung efek late_fee.compute
    pic_user_id BIGINT UNSIGNED NULL,
    attributes JSON NULL,                -- renter_identity, notes, dll
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_bookings_company_resource_time (company_id, resource_id, starts_at, ends_at),
    INDEX idx_bookings_company_stage (company_id, stage),
    CONSTRAINT fk_bookings_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_bookings_resource FOREIGN KEY (resource_id) REFERENCES resources(id),
    CONSTRAINT fk_bookings_contact FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE SET NULL,
    CONSTRAINT fk_bookings_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);
```
Invarian (service + test): untuk `resource_id` yang sama, tidak boleh ada dua
`bookings` dengan `stage NOT IN ('cancelled','returned')` yang rentang waktunya
tumpang tindih.

### 6.3 `booking_incidents` (kerusakan / kehilangan / denda tambahan)
```sql
CREATE TABLE booking_incidents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    booking_id BIGINT UNSIGNED NOT NULL,
    type VARCHAR(32) NOT NULL,           -- damage | loss | late | other
    description TEXT NULL,
    charge_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL,
    INDEX idx_incidents_company_booking (company_id, booking_id),
    CONSTRAINT fk_incidents_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_incidents_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
);
```

---

## 7. Kapabilitas `inventory` (generik: Barang / Obat / Bahan / Sparepart)

### 7.1 `items`
```sql
CREATE TABLE items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    sku VARCHAR(64) NULL,
    name VARCHAR(191) NOT NULL,
    type VARCHAR(32) NOT NULL DEFAULT 'goods', -- goods | raw_material | finished_good | service
    unit VARCHAR(16) NOT NULL DEFAULT 'pcs',
    price DECIMAL(18,2) NULL,
    cost DECIMAL(18,2) NULL,
    min_stock DECIMAL(14,3) NOT NULL DEFAULT 0,
    track_batches BOOLEAN NOT NULL DEFAULT FALSE, -- true bila inventory.batch_expiry
    attributes JSON NULL,                -- golongan obat (Tier B baca ini), merek, dll
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY uq_items_company_sku (company_id, sku),
    CONSTRAINT fk_items_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);
```

### 7.2 `item_batches` (kapabilitas `inventory.batch_expiry`)
```sql
CREATE TABLE item_batches (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    item_id BIGINT UNSIGNED NOT NULL,
    batch_no VARCHAR(64) NOT NULL,
    expires_on DATE NULL,
    qty_on_hand DECIMAL(14,3) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_batches_company_item_exp (company_id, item_id, expires_on),
    CONSTRAINT fk_batches_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_batches_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
);
```
FEFO: efek `stock.deduct` mengambil dari batch dengan `expires_on` terdekat dulu.

### 7.3 `stock_movements`
```sql
CREATE TABLE stock_movements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    item_id BIGINT UNSIGNED NOT NULL,
    batch_id BIGINT UNSIGNED NULL,
    direction VARCHAR(8) NOT NULL,       -- in | out
    qty DECIMAL(14,3) NOT NULL,
    reason VARCHAR(32) NOT NULL,         -- purchase | sale | adjustment | bom_consume | bom_produce | return
    reference_type VARCHAR(191) NULL,
    reference_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    INDEX idx_moves_company_item (company_id, item_id),
    CONSTRAINT fk_moves_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_moves_item FOREIGN KEY (item_id) REFERENCES items(id)
);
```

### 7.4 `bom_lines` (kapabilitas `inventory.bom`)
```sql
CREATE TABLE bom_lines (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    product_item_id BIGINT UNSIGNED NOT NULL,   -- produk jadi / menu
    component_item_id BIGINT UNSIGNED NOT NULL, -- bahan
    qty DECIMAL(14,3) NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY uq_bom (company_id, product_item_id, component_item_id),
    CONSTRAINT fk_bom_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_bom_product FOREIGN KEY (product_item_id) REFERENCES items(id) ON DELETE CASCADE,
    CONSTRAINT fk_bom_component FOREIGN KEY (component_item_id) REFERENCES items(id)
);
```

---

## 8. Kapabilitas `pos` (generik: Kasir / Bill / Nota)

### 8.1 `pos_shifts`
```sql
CREATE TABLE pos_shifts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    outlet_name VARCHAR(191) NULL,
    cashier_user_id BIGINT UNSIGNED NOT NULL,
    opening_float DECIMAL(18,2) NOT NULL DEFAULT 0,
    expected_cash DECIMAL(18,2) NOT NULL DEFAULT 0,
    actual_cash DECIMAL(18,2) NULL,
    variance DECIMAL(18,2) NULL,
    opened_at TIMESTAMP NOT NULL,
    closed_at TIMESTAMP NULL,
    INDEX idx_shifts_company (company_id),
    CONSTRAINT fk_shifts_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);
```

### 8.2 `orders` (bill / nota / work order kasir)
```sql
CREATE TABLE orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    business_identity_id BIGINT UNSIGNED NOT NULL, -- menentukan mode pajak (D-03)
    shift_id BIGINT UNSIGNED NULL,
    contact_id BIGINT UNSIGNED NULL,
    resource_id BIGINT UNSIGNED NULL,    -- meja (pos.tables) — FK ke resources.type='table'
    prescription_id BIGINT UNSIGNED NULL, -- Tier B pharmacy
    order_no VARCHAR(64) NOT NULL,
    stage VARCHAR(32) NOT NULL DEFAULT 'open', -- dari workflow_definitions[entity='order']: open | sent_to_kitchen | paid | void
    subtotal DECIMAL(18,2) NOT NULL DEFAULT 0,
    discount_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
    dpp DECIMAL(18,2) NOT NULL DEFAULT 0,
    tax_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
    grand_total DECIMAL(18,2) NOT NULL DEFAULT 0,
    payment_method VARCHAR(32) NULL,     -- cash | qris | transfer
    paid_at TIMESTAMP NULL,
    source VARCHAR(32) NOT NULL DEFAULT 'pos', -- pos | nalarpesan | wa_bot
    external_ref VARCHAR(128) NULL,      -- idempotency untuk webhook NalarPesan (D-04)
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY uq_orders_company_no (company_id, order_no),
    UNIQUE KEY uq_orders_company_ext (company_id, external_ref),
    INDEX idx_orders_company_stage (company_id, stage),
    CONSTRAINT fk_orders_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_orders_identity FOREIGN KEY (business_identity_id) REFERENCES business_identities(id),
    CONSTRAINT fk_orders_shift FOREIGN KEY (shift_id) REFERENCES pos_shifts(id) ON DELETE SET NULL,
    CONSTRAINT fk_orders_resource FOREIGN KEY (resource_id) REFERENCES resources(id) ON DELETE SET NULL
);
```

### 8.3 `order_lines`
```sql
CREATE TABLE order_lines (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    order_id BIGINT UNSIGNED NOT NULL,
    item_id BIGINT UNSIGNED NULL,
    description VARCHAR(191) NOT NULL,
    qty DECIMAL(14,3) NOT NULL,
    unit_price DECIMAL(18,2) NOT NULL,
    discount_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
    line_total DECIMAL(18,2) NOT NULL,
    fired_at TIMESTAMP NULL,             -- pos.tables: waktu kirim ke dapur (re-fire = baris baru)
    created_at TIMESTAMP NULL,
    INDEX idx_olines_company_order (company_id, order_id),
    CONSTRAINT fk_olines_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_olines_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);
```

---

## 9. Kapabilitas `hr.*`

### 9.1 `employees`
```sql
CREATE TABLE employees (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,        -- bila punya akun login
    name VARCHAR(191) NOT NULL,
    position VARCHAR(191) NULL,
    base_salary DECIMAL(18,2) NOT NULL DEFAULT 0,
    hourly_cost DECIMAL(18,2) NULL,      -- untuk timesheet costing
    joined_on DATE NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    attributes JSON NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_employees_company (company_id),
    CONSTRAINT fk_employees_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);
```

### 9.2 `payrolls` (kapabilitas `hr.payroll`)
```sql
CREATE TABLE payrolls (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    employee_id BIGINT UNSIGNED NOT NULL,
    period_month CHAR(7) NOT NULL,       -- YYYY-MM
    gross_salary DECIMAL(18,2) NOT NULL,
    deductions DECIMAL(18,2) NOT NULL DEFAULT 0,
    net_salary DECIMAL(18,2) NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'draft', -- draft | paid
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY uq_payroll_employee_period (company_id, employee_id, period_month),
    CONSTRAINT fk_payrolls_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_payrolls_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);
```

### 9.3 `ai_reminders`
```sql
CREATE TABLE ai_reminders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    task_description TEXT NOT NULL,
    remind_at DATETIME NOT NULL,
    is_completed BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_reminders_company_due (company_id, remind_at, is_completed),
    CONSTRAINT fk_reminders_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);
```

---

## 10. Modul Tier B (D-33 — aturan domain yang tidak bisa jadi data)

Hanya dua untuk 6 preset awal. Tabel ini **melengkapi** tabel generik, tidak
menggantikannya.

### 10.1 `prescriptions` (Tier B `pharmacy.prescription`)
Pasien = `contacts.type='patient'` (alergi/riwayat di `attributes`). Foto resep =
`attachments` (D-29). Obat = `items` dengan `attributes.drug_class` (`bebas |
bebas_terbatas | keras | psikotropika`). Aturan yang butuh kode: obat `keras`/
`psikotropika` **tidak boleh** masuk `order_lines` tanpa `prescription_id` yang
`stage='verified'`.
```sql
CREATE TABLE prescriptions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    patient_contact_id BIGINT UNSIGNED NULL,
    doctor_name VARCHAR(191) NULL,
    doctor_sip VARCHAR(64) NULL,
    extracted_lines JSON NULL,           -- [{item_id?, drug, qty, dosage, signa}]
    stage VARCHAR(32) NOT NULL DEFAULT 'draft', -- draft | pharmacist_verify | verified | served | cancelled
    verified_by_user_id BIGINT UNSIGNED NULL,
    verified_at TIMESTAMP NULL,
    served_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_rx_company_stage (company_id, stage),
    CONSTRAINT fk_rx_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_rx_patient FOREIGN KEY (patient_contact_id) REFERENCES contacts(id) ON DELETE SET NULL
);
```

### 10.2 `retentions` (Tier B `construction.retention`)
Proyek = `projects`, opname = `project_milestones` dengan `retention_pct > 0`.
Aturan yang butuh kode: retensi dipotong otomatis dari setiap invoice milestone,
ditahan, dan hanya bisa ditagih setelah `release_on` (FHO + 3–6 bulan).
```sql
CREATE TABLE retentions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    project_id BIGINT UNSIGNED NOT NULL,
    milestone_id BIGINT UNSIGNED NULL,
    invoice_id BIGINT UNSIGNED NULL,
    amount DECIMAL(18,2) NOT NULL,
    release_on DATE NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'held', -- held | released | invoiced
    released_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_retentions_company_project (company_id, project_id),
    CONSTRAINT fk_retentions_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_retentions_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);
```

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

### 12.2 `hermes_profiles` (D-37: satu bot per OWNER, multi-bisnis)

Bot **tidak** terikat satu company. Profile milik seorang owner dan diberi scope
ke satu atau banyak company lewat pivot `hermes_profile_companies`. Bot pertama
owner bertipe `primary`; bot berikutnya (mis. untuk manajer cabang) bertipe
`addon` dan terikat item billing.

```sql
CREATE TABLE hermes_profiles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_user_id BIGINT UNSIGNED NOT NULL,                 -- pemilik/penanggung jawab bot
    node_id BIGINT UNSIGNED NULL,                           -- node tempat instance berjalan
    type VARCHAR(16) NOT NULL DEFAULT 'primary',           -- primary | addon (divalidasi model)
    label VARCHAR(64) NULL,                                 -- "Asisten Bos", "Bot Manajer Cabang A"
    billing_addon_id BIGINT UNSIGNED NULL,                  -- wajib bila type=addon (item add-on aktif)
    instance_id VARCHAR(128) NOT NULL UNIQUE,
    webhook_secret_reference VARCHAR(191) NOT NULL,         -- referensi secret manager, bukan plaintext
    status VARCHAR(16) NOT NULL DEFAULT 'unpaired',        -- unpaired | connected | disconnected
    last_ping_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    CONSTRAINT fk_hermes_profiles_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_hermes_profiles_node FOREIGN KEY (node_id) REFERENCES hermes_nodes(id) ON DELETE RESTRICT
);
-- Tepat satu primary per owner: unique index parsial disimulasikan di aplikasi
-- (SQLite/MySQL portabel): validasi di model + test.

CREATE TABLE hermes_profile_companies (
    hermes_profile_id BIGINT UNSIGNED NOT NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    role VARCHAR(32) NOT NULL DEFAULT 'owner',              -- owner | manager | viewer: batas kemampuan bot di company itu
    is_default BOOLEAN NOT NULL DEFAULT FALSE,              -- company yang dipakai bila percakapan belum menyebut konteks
    created_at TIMESTAMP NULL,
    PRIMARY KEY (hermes_profile_id, company_id),
    CONSTRAINT fk_hpc_profile FOREIGN KEY (hermes_profile_id) REFERENCES hermes_profiles(id) ON DELETE CASCADE,
    CONSTRAINT fk_hpc_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);

CREATE TABLE hermes_conversation_contexts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    hermes_profile_id BIGINT UNSIGNED NOT NULL,
    channel VARCHAR(16) NOT NULL,                           -- whatsapp | telegram
    chat_id VARCHAR(128) NOT NULL,
    active_company_id BIGINT UNSIGNED NULL,                 -- company yang sedang dibicarakan
    updated_at TIMESTAMP NULL,
    UNIQUE (hermes_profile_id, channel, chat_id),
    CONSTRAINT fk_hcc_profile FOREIGN KEY (hermes_profile_id) REFERENCES hermes_profiles(id) ON DELETE CASCADE,
    CONSTRAINT fk_hcc_company FOREIGN KEY (active_company_id) REFERENCES companies(id) ON DELETE SET NULL
);
```

**Aturan bot multi-bisnis (D-37):**
- Setiap perintah tenant MCP (T-17b) wajib membawa `company_id` yang berasal dari
  `hermes_conversation_contexts.active_company_id`; bila NULL dan owner punya >1
  company, bot **bertanya** ("Untuk Kopi Senja atau Rental Arka?") - tidak menebak.
- Owner dengan 1 company: konteks otomatis, tidak pernah ditanya.
- Bot `addon` hanya boleh mengakses company di pivot-nya dengan `role` tersebut;
  `EnsureFeatureEnabled` + policy memakai `role` ini.
- Kuota AI: `primary` dihitung per owner (gabungan semua company); `addon` punya
  kuota sendiri sesuai item billing.
- Pengecualian D-26: `hermes_profiles` tidak punya `company_id` karena memang
  lintas company; isolasi dijamin lewat pivot.

### 12.4 `admin_impersonation_sessions` (D-47: Super Admin "Login As" beraudit)

Super Admin platform boleh membantu setup klien yang tidak sempat/kurang paham
teknologi, **tanpa** mengetahui password klien dan **dengan jejak audit penuh**.

```sql
CREATE TABLE admin_impersonation_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_user_id BIGINT UNSIGNED NOT NULL,   -- user platform berrole platform_admin
    company_id BIGINT UNSIGNED NOT NULL,      -- tenant yang dibantu
    acting_as_user_id BIGINT UNSIGNED NULL,   -- akun owner yang diwakili (untuk konteks)
    reason VARCHAR(191) NOT NULL,             -- wajib diisi admin sebelum sesi mulai
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    started_at TIMESTAMP NOT NULL,
    ended_at TIMESTAMP NULL,                  -- NULL = sesi masih aktif
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_impersonation_company (company_id, started_at),
    INDEX idx_impersonation_admin (admin_user_id, started_at),
    CONSTRAINT fk_impersonation_admin FOREIGN KEY (admin_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_impersonation_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);
```

**Aturan wajib (D-47):**
- Sesi aktif (`ended_at IS NULL`) menampilkan banner kuning permanen
  non-dismissable di seluruh halaman `/app/*`.
- Semua tulis ke `module_settings`, `companies.business_preset`,
  `companies.theme` selama sesi aktif dicatat `changed_by_type='admin_impersonation'`
  beserta `admin_user_id` — **tidak** tercatat seolah owner yang melakukan.
- Aksi finansial/destruktif **tetap** melewati D-45 (tidak ada bypass approval).
- Admin **tidak** boleh membaca/expor secret klien (password hash, token,
  `*_secret_reference`) selama impersonasi — endpoint terkait menjawab 403.
- Pengecualian D-26 kedua: tabel ini milik platform, `company_id` adalah
  **target** bantuan, bukan penanda kepemilikan data tenant.

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

1. Setiap tabel bisnis WAJIB punya `company_id` (FK ke `companies`) untuk scoping
   tenant, termasuk tabel anak (D-26). Test isolasi tenant A vs B wajib untuk
   setiap tabel.
2. Kolom `stage` pada entitas ber-workflow (`deals`, `projects`, `bookings`,
   `orders`, `prescriptions`) adalah `VARCHAR(32)`, **bukan** `ENUM`, dan
   nilainya divalidasi oleh `WorkflowEngine` terhadap `workflow_definitions`
   — bukan oleh database (D-31c).
3. `attributes JSON` tidak boleh menyimpan data yang di-query/di-index/di-hitung
   oleh aturan bisnis; data seperti itu wajib kolom nyata (§1.7).
4. **Uji komposisi preset**: test wajib membuktikan bahwa memilih preset
   `klinik` (Tier A, bukan 6 awal) menghasilkan menu, terminologi, dan widget
   yang benar **tanpa** migration atau kode baru — ini bukti D-31 terpenuhi.
5. `business_presets.definition` divalidasi seeder terhadap katalog
   `INDUSTRY_PRESETS.md` §1, §3, §4, §5; key asing → seeder gagal.
