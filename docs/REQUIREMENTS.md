# REQUIREMENTS SPECIFICATION
## Spesifikasi Fungsional & Aturan Bisnis per Modul

---

## 1. Modul Pajak: PPN Inklusif, Eksklusif & Rekap DPP (Universal)

### 1.1 Kebutuhan Bisnis
- **Aturan Pajak Indonesia:** Banyak bisnis retail (Resto, Kafe, Apotek) mencantumkan harga jual yang sudah mencakup PPN 11% (*tax-inclusive*). Sebaliknya, transaksi B2B (Agency, Kontraktor, EO) umumnya menambahkan PPN di luar harga (*tax-exclusive*).
- Sistem wajib mendukung kedua metode ini secara dinamis pada `BusinessIdentity` sebagai konfigurasi dasar untuk seluruh transaksi identity tersebut.
- Override metode per dokumen/item dan multi-tax rate bukan bagian fase dasar. Fitur itu hanya boleh dibuat sebagai add-on Enterprise setelah kontrak skema, pricing, UI, dan migrasinya disahkan.

### 1.2 Formula Perhitungan Matematis
Misalkan:
- `Subtotal` = Jumlah harga item
- `TaxRate` = 0.11 (11%)

#### Skenario A: `price_includes_tax = false` (Tax-Exclusive)
- `DPP` = `Subtotal`
- `PPN` = `round(DPP * TaxRate, 2)`
- `GrandTotal` = `DPP + PPN`

#### Skenario B: `price_includes_tax = true` (Tax-Inclusive)
- `GrandTotal` = `Subtotal` (Nilai yang dibayar pelanggan)
- `DPP` = `round(GrandTotal / (1 + TaxRate), 2)`
- `PPN` = `GrandTotal - DPP` (Menghindari selisih pembulatan)

### 1.3 Kontrak Implementasi di `TaxRateService.php`
```php
public function calculateTax(float $grossAmount, float $rate, bool $priceIncludesTax): TaxCalculationResult
{
    // Normalisasi $rate: cegah salah input pecahan vs persen (misal 11 atau 11.0 dikonversi ke 0.11)
    $normalizedRate = $rate > 1.0 ? $rate / 100.0 : $rate;

    if (! $priceIncludesTax) {
        $dpp = $grossAmount;
        $tax = round($dpp * $normalizedRate, 2);
        return new TaxCalculationResult(dpp: $dpp, tax: $tax, grandTotal: round($dpp + $tax, 2));
    }

    $dpp = round($grossAmount / (1.0 + $normalizedRate), 2);
    $tax = round($grossAmount - $dpp, 2);
    return new TaxCalculationResult(dpp: $dpp, tax: $tax, grandTotal: $grossAmount);
}
```

---

## 2. Composable Capability, Terminologi, Workflow & Menu Registry (D-31)

### 2.0 Prinsip
`Company` tidak "berjenis" agency/apotek di kode. Ia memuat **preset** (data) yang
mengkomposisi **kapabilitas** (kode). Empat resolver membaca urutan
`module_settings` (override company) → `business_presets.definition` → default:

| Resolver | Membaca | Dipakai oleh |
|---|---|---|
| `FeatureResolver` → `Company::feature()` | `capabilities` | registry menu, middleware, widget |
| `TerminologyResolver` → `term()` | `terminology` | semua label Blade |
| `WorkflowEngine` | `workflows` (dimaterialisasi ke `workflow_definitions`) | transisi `stage` entitas |
| `DashboardComposer` | `dashboard` | susunan widget |

Kunci kapabilitas hanya dari katalog `INDUSTRY_PRESETS.md` §1 (D-32). Kunci
seperti `ops.contractor_spk` atau `pos.prescription_flow` **tidak ada lagi**;
padanannya `projects.progress_billing` dan Tier B `pharmacy.prescription`.

### 2.1 Aturan Hard Runtime Gate
1. `Company` memiliki method helper (didelegasikan ke `FeatureResolver`):
   ```php
   public function feature(string $key): bool;
   public function hasAnyFeature(array $keys): bool;
   ```
2. Resolusi status fitur:
   - Baca baris `module_settings` dengan `company_id` aktif dan `module_name = 'features'` (bentuk D-19/D-25: satu baris per modul, flag disimpan di `settings_json`).
   - Jika `settings_json` memuat key `$key`, kembalikan nilai boolean-nya (override).
   - Jika tidak ada override, ambil dari `business_presets.definition.capabilities[$key]` sesuai `company->business_preset`; jika tidak ada juga → `false`.
3. **App Switcher Architecture (Odoo/Zoho Style):**
   - Aplikasi web tidak menggunakan tradisi navigasi *sidebar* konvensional.
   - Halaman pertama setelah login adalah **Lobby Dashboard (App Switcher)**. Layar ini menampilkan:
     - **Widget Wealth Management:** Merangkum total kekayaan/Laba-Rugi Bos dari *seluruh* cabang perusahaannya.
     - **App Grid:** Daftar ikon modul (label via `term()`, mis. "Kasir", "Penyewa", "Pasien") untuk perusahaan aktif, hanya modul yang `visible` di `DynamicMenuRegistry`.
   - Modul yang *Feature Flags*-nya mati tidak akan di-*render* sama sekali ke HTML (Zero-Clutter).
   - Saat bos mengeklik "Kasir", layar akan berubah 100% menjadi *Single Page Application* khusus POS.
   - Untuk berpindah aplikasi dengan cepat, ada ikon **Global App Launcher (9-Dots Grid)** di pojok kiri atas aplikasi.
4. **UI Masking untuk Modul Keuangan (Simple vs Pro):**
   - *Backend* secara kaku diwajibkan menggunakan struktur *Double-Entry Accounting* (`accounting_journals` & `accounting_journal_lines`) untuk *semua* perusahaan, kecil maupun besar. Hal ini menjamin integritas data seumur hidup.
   - **Simple Mode (`finance.cashbook = true`, `finance.accounting = false`):** UI Web menyembunyikan kata "Jurnal", "Debit", "Kredit", dan "Buku Besar". Layar hanya menampilkan antarmuka "Buku Kas" dengan tombol **[+ Pemasukan]** dan **[- Pengeluaran]**. Saat formulir disubmit, *Backend* yang bertugas menerjemahkannya menjadi Jurnal (misal: Debit Beban Listrik, Kredit Kas).
   - **Pro Mode (`finance.accounting = true`):** UI Web menampilkan menu Akuntansi Lengkap: *Chart of Accounts*, Jurnal Manual, Buku Besar, Neraca, dan Laba-Rugi. Jika sebuah Warung (*Simple Mode*) berkembang dan menyalakan *Pro Mode*, seluruh data lamanya otomatis tersaji dalam format neraca tanpa perlu migrasi data sama sekali.
5. **Route Protection:**
   - Middleware `EnsureFeatureEnabled:{capability}` memblokir akses direct URL dengan HTTP 403 jika kapabilitas terkait mati untuk company tersebut. Modul yang tidak dikenal registry → 404.

### 2.2 Alur Bisnis sebagai Data (`WorkflowEngine`)
- Setiap entitas ber-`stage` (`deals`, `projects`, `bookings`, `orders`, `prescriptions`) **tidak** memakai `ENUM`; nilai `stage` adalah kode netral dari `workflow_definitions` company.
- `WorkflowEngine::transition($model, $toStage, $actor)`:
  1. Menolak transisi yang tidak terdefinisi (`InvalidTransition`).
  2. Menolak actor yang role-nya tidak diizinkan (403).
  3. Bila `requires_approval: true` → membuat tiket approval (`YA <kode>`, D-27) dan **menahan** transisi sampai disetujui.
  4. Menjalankan `effects` (katalog `INDUSTRY_PRESETS.md` §5) dalam satu `DB::transaction`; efek gagal → seluruh transisi rollback.
  5. Menulis `workflow_transitions_log`.
- Owner dapat mengubah stage/label/transisi via Pengaturan > Fitur Bisnis (tersimpan di `module_settings[workflows]`) **tanpa** kode.

### 2.3 Terminologi (`term()`)
- Blade **dilarang** menulis literal istilah bisnis (`Klien`, `Pasien`, `Karyawan`, `Meja`). Wajib `{{ term('contact') }}` / `@term('contacts')`.
- Kunci hanya dari kamus `INDUSTRY_PRESETS.md` §3. Key tak dikenal → exception di `APP_ENV=local|testing`, fallback ke key di produksi.
- T-22 menjalankan grep literal istilah = 0 sebagai gate white-label sekaligus gate D-31.

---

## 3. Validasi Server-Side (API Anti-Jailbreak Bot)

Sistem harus memvalidasi setiap payload API yang datang dari Asisten AI secara ketat di sisi server untuk mencegah insiden akibat *prompt injection* atau eksploitasi oleh staf/karyawan.

### 3.1 Chat-Driven Config Sync (Bot to ERP)
- **Endpoint API:** `PUT /api/bot/settings`
- **Fungsi:** Menyimpan konfigurasi bisnis lisan yang diinstruksikan oleh Bos di WA (misal: "ubah batas diskon jadi 5%") ke tabel `module_settings`.
- **Validasi Hak Akses:** Endpoint ini **mutlak** hanya menerima mutasi jika parameter *caller* (pengirim pesan WA awal) adalah `wa_number` yang *role*-nya Owner/Bos. Apabila bot memanggil API ini atas hasutan Staf Kasir, API mengembalikan `403 Forbidden`.
### 3.2 Universal Business Customization (AI Onboarding)
- **Endpoint API:** `PUT /api/bot/features`
- **Fungsi:** Mengizinkan bot (selama proses orientasi/wawancara awal) untuk menyalakan/mematikan fitur di `module_settings` (contoh: `{"capabilities": {"pos": true, "inventory": true}, "terminology": {"contact": "Pelanggan"}}` — kunci hanya dari katalog `INDUSTRY_PRESETS.md` §1/§3) sehingga membentuk *Custom Preset* secara dinamis.
- **Validasi Hak Akses:** Sama seperti pengaturan konfigurasi, endpoint ini mutlak hanya dapat dipanggil atas inisiasi dari *wa_number* yang berstatus Bos.

### 3.3 Aturan Hard-Limit Eksekusi (Mengekang Bawahan)
- **Batas Toleransi Diskon:** Endpoint `POST /api/bot/pos/discount` (contoh) wajib mengecek nilai `max_discount_percentage` milik perusahaan terkait (yang disetel oleh Bos di 3.1). Jika nilai diskon melebihi batas, tolak dengan `422 Unprocessable Entity`.
- **Waktu Operasional:** Jika jam buka/tutup diatur oleh Bos, semua request *write* (POST/PUT/DELETE) dari bot atas instruksi staf di luar jam tersebut ditolak.

### 3.4 Otorisasi Human-in-the-Loop (2-Step Approval)
- **Tindakan Destruktif (Void/Pengeluaran):** Endpoint tindakan berisiko tidak boleh langsung mengeksekusi data.
  1. API mengembalikan status `202 Accepted` beserta ID tiket persetujuan.
  2. Sistem mengirimkan pesan "Kartu Persetujuan" ke nomor WhatsApp Bos/Owner.
  3. Kartu Persetujuan memuat **kode tiket** 4–6 digit. Transaksi tereksekusi **hanya** jika Bos membalas `YA <kode-tiket>` yang cocok dan belum dipakai (D-11/D-27). Balasan `YA` polos ditolak untuk mencegah replay.

---

## 4. Spesifikasi 6 Modul Industri Spesifik

### 4.1 Industri 1: Agency (Jasa Kreatif & IT)
- **CRM:** Menggunakan pipeline deals. Kode stage disimpan netral (`new / qualified / proposal / negotiation / won / lost`, D-38) dan ditampilkan dengan label Indonesia: `Lead Baru → Pitch / SPH → Negosiasi → Won / Lost`. Data PIC (`pic_name`, `pic_wa`) disimpan di `contacts` (`name`, `wa_number`) yang direlasikan ke `deals` — **bukan** kolom baru di `deals`. `deals.value` wajib.
- **Operasional:** Timesheet per staf untuk menghitung biaya per jam pengerjaan proyek klien.
- **Invoicing:** Termin bertahap (contoh: DP 50%, Pelunasan 50% setelah serah terima).

### 4.2 Industri 2: F&B (Restoran, Kafe, FnB)
- **POS Meja (Open Bill):**
  - Kasir dapat membuka meja (contoh: Meja 04), mencatat pesanan awal, lalu mengirim pesanan ke dapur tanpa langsung meminta pembayaran.
  - Tambahan pesanan (re-fire) dapat digabungkan ke tagihan meja yang sama tanpa menduplikasi pesanan sebelumnya.
  - Checkout / Pembayaran dapat dilakukan tunai atau QRIS. Saat shift ditutup, sistem mencetak ringkasan kas masuk & selisih (*variance*).
- **Integrasi NalarPesan (D-30):**
  - Tamu scan QR di meja → memesan via browser mobile → data masuk ke ERP via webhook.
  - Webhook controller memetakan pesanan tamu ke tagihan meja yang bersangkutan di ERP.

### 4.3 Industri 3: Apotek & Farmasi
- **Aturan FEFO (First Expired, First Out):**
  - Setiap batch obat memiliki `expired_date`. Sistem selalu menyarankan dan memotong stok dari batch dengan tanggal kedaluwarsa terdekat.
- **Validasi Resep Dokter:**
  - Obat golongan Keras / Psikotropika **dilarang dijual langsung** tanpa resep dokter.
  - Foto resep dokter diunggah ke sistem (atau via WA bot), diverifikasi oleh Apoteker yang bertugas (`pharmacist_verify`), baru kemudian dapat dilayani di kasir POS Apotek.

### 4.4 Industri 4: Event Organizer (EO)
- **Rundown & Timeline:**
  - Manajemen jadwal acara per menit/jam beserta penanggung jawab (PIC/Crew).
- **Vendor Matrix:**
  - Daftar vendor pihak ketiga (Sound System, Lighting, Panggung, Catering, Talent) yang terikat pada event tertentu beserta status pelunasan pembayarannya.

### 4.5 Industri 5: Kontraktor & Konstruksi
- **Surat Perintah Kerja (SPK) Subkon:**
  - Pencatatan pekerjaan yang dilempar ke mandor/subkontraktor.
- **Opname Progres Fisik (%):**
  - Penagihan ke pemberi kerja (Bouwheer) didasarkan pada Berita Acara Progres Fisik (contoh: Progres 35% tercapai).
- **Pemotongan Retensi:**
  - Otomatis memotong retensi pemeliharaan (biasanya 5%) dari setiap invoice progres, yang baru bisa ditagihkan 3-6 bulan setelah proyek serah terima final (FHO).

### 4.6 Industri 6: Persewaan (Rental Alat, Mobil, Venue)
- **Kalender Booking Unit:**
  - Tampilan visual status ketersediaan unit/armada pada tanggal tertentu untuk mencegah *double booking*.
- **Manajemen Deposit Jaminan & Denda:**
  - Pelanggan wajib menitipkan deposit uang/identitas saat pengambilan unit.
  - Jika pengembalian unit melebihi batas waktu (jam/hari), sistem otomatis menghitung denda keterlambatan saat check-in unit.
