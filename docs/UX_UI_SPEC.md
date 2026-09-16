# UX / UI SPECIFICATION
## Antarmuka Agentic Business Operating System (BOS)

**Versi:** 1.0.0-PROD  
**Stack Wajib (terverifikasi dari `composer.json` / `package.json`, 2026-09-16):** Laravel 13.32 + Livewire v4.4 + Blade + Tailwind CSS v4.3 + Vite 8 (Dilarang React/Vue/Inertia/Filament).  
**Alpine.js:** TIDAK dipasang sebagai paket npm terpisah. Alpine sudah dibundel oleh Livewire v4 dan tersedia otomatis lewat `@livewireScripts`. Jangan menambahkan `alpinejs` ke `package.json` karena dua instans Alpine akan merusak reaktivitas Livewire.  
**Design Tokens:** Target 36 token CSS `--erp-*` yang lolos WCAG 2.1 AA. **Status: BELUM ADA.** `resources/css/app.css` saat ini hanya mendefinisikan `--font-sans`. Karena proyek memakai Tailwind v4, token harus dideklarasikan dalam blok `@theme` CSS-first (bukan `tailwind.config.js`, yang tidak dipakai Tailwind v4).

---

## 1. Filosofi Desain (Friendly, Zero-Bloat, Actionable)

1. **User Friendly & Tidak Pusing:**
   - Muncul hanya apa yang dibutuhkan bisnis klien.
   - Satu halaman pengaturan dengan **tab-tab dinamis** (bukan menu dropdown bercabang dalam yang membingungkan).
   - Label bahasa Indonesia lugas (bukan istilah teknis asing yang bikin pusing).
2. **Zero-Bloat Sidebar:**
   - Fitur/modul yang dimatikan **sama sekali tidak dirender ke HTML** (bukan disabled, bukan greyed out, tanpa badge "Upgrade" yang mengotori layar).
3. **Subtle & Integrated AI:**
   - Topup token dan kuota diletakkan secara elegan di dalam konteks "Penggunaan & Paket", **bukan banner/tombol CTA jualan** yang agresif.
   - Klien merasa sedang mengelola "asisten kerja", bukan membeli token API.

---

## 2. Onboarding Flow (Pasca-Wawancara / Form — Bukan Wizard Paksa)

```
[ Klien Daftar / Onboarding ]
             │
             ▼
   [ Wawancara AI / Human / Form ]
   (Paham jenis bisnis, jumlah cabang, kebutuhan modul)
             │
             ▼
   [ Admin / Sistem Set Preset Bisnis ]
   (Agency / F&B / Apotek / EO / Kontraktor / Persewaan)
             │
             ▼
   [ Klien Login Pertama Kali ]
   Langsung masuk ke Dashboard Bersih yang relevan dengan industrinya
   (Tanpa pop-up wizard paksaan 5 langkah)
```

- **Alur:** Klien tidak dipaksa mengisi 20 pertanyaan saat login pertama. Tim kita / AI onboarding sudah menyetel preset yang tepat di awal. Klien login langsung siap pakai.
- **Koreksi:** Jika klien ingin mengubah preset atau menambah fitur, mereka bisa masuk ke `Pengaturan > Fitur Bisnis` kapan saja.

---

## 3. Sidebar Kiri Dinamis (Zero-Bloat)

Sidebar adalah komponen Livewire `app/Livewire/Sidebar.php` + `resources/views/livewire/sidebar.blade.php`, dimount oleh layout `resources/views/components/layouts/module.blade.php`, dan membaca `app/Services/DynamicMenuRegistry.php`.

### 3.1 Aturan Rendering
- Item menu level-1 hanya muncul jika `visible($company)` bernilai `true` **dan** minimal satu item level-2 juga `visible` (lihat `INDUSTRY_PRESETS.md` §2.2).
- Item menu level-2 hanya muncul jika `company->feature($key) === true`.
- Menu yang gagal evaluasi: `null` (tidak menghasilkan DOM).

### 3.2 Visual State & Token CSS
- Active menu: `bg-[var(--erp-bg-secondary)] text-[var(--erp-text-primary)] border-l-4 border-[var(--erp-accent)] font-semibold`
- Inactive menu: `text-[var(--erp-text-secondary)] hover:text-[var(--erp-text-primary)] hover:bg-[var(--erp-bg-secondary)]`
- Section divider: `border-t border-[var(--erp-border)]`

---

## 4. Halaman Pengaturan Friendly (Tab-Based)

Semua pengaturan disatukan dalam satu halaman terpusat: **`/app/settings`** (D-24) dengan navigasi **Tab Horizontal**. Tab aktif dipilih dari query `?tab=profile|theme|features|ai-agent|usage|team` sehingga dapat di-deep-link dari sidebar:

```
┌─────────────────────────────────────────────────────────────────────────────────────────────┐
│ PENGATURAN BISNIS & SISTEM                                                                  │
│                                                                                             │
│ [ Profil Bisnis & Pajak ] [ Tampilan & Tema ] [ Fitur Bisnis ] [ Karyawan AI ] [ Penggunaan & Paket ] [ Tim & Akses ] │
│ ─────────────────────────────────────────────────────────────────────────────────────────── │
│ (Konten tab aktif dirender via Alpine.js x-show atau server-side tab query)                 │
└─────────────────────────────────────────────────────────────────────────────────────────────┘
```

### 4.0 Kontrak Aksesibilitas & Navigasi Tab (WAI-ARIA Pattern)
- **Container tablist:** `role="tablist"` `aria-orientation="horizontal"`.
- **Tab button:**
  - Wajib memiliki atribut `role="tab"`, `id="tab-{id}"`, `aria-controls="panel-{id}"`.
  - Tab aktif: `aria-selected="true"` `tabindex="0"` `border-b-2 border-[var(--erp-focus)] font-semibold text-[var(--erp-text-primary)]`.
  - Tab non-aktif: `aria-selected="false"` `tabindex="-1"` `text-[var(--erp-text-muted)] hover:text-[var(--erp-text-primary)]`.
- **Navigasi Keyboard Tablist:**
  - `ArrowRight` / `ArrowLeft`: Pindah fokus ke tab berikutnya/sebelumnya (otomatis wrap-around).
  - `Home` / `End`: Lompat langsung ke tab pertama / tab terakhir.
  - `Space` / `Enter`: Memilih/mengaktifkan tab yang sedang difokuskan.
  - `Tab`: Pindah fokus langsung ke dalam isi container `role="tabpanel"`.
- **Kontainer Panel:** `role="tabpanel"` `id="panel-{id}"` `aria-labelledby="tab-{id}"` `tabindex="0"`.
- **Mobile Viewport (<640px):**
  - Tablist memakai pembungkus `flex overflow-x-auto no-scrollbar scroll-smooth snap-x` dengan target sentuh minimal 44x44px per item tab.

### 4.1 Tab 1: Profil Bisnis & Pajak
- Nama bisnis, alamat, no telepon, logo upload.
- **Pengaturan Pajak (Universal):**
  - Radio button: `Non-PKP (Bebas PPN)` vs `PKP (Wajib PPN)`.
  - Jika PKP aktif: Toggle `Harga Jual Sudah Termasuk PPN (Inklusif)` vs `PPN Ditambahkan di Luar Harga (Eksklusif)`.
  - Teks edukasi & peringatan: *"Pilih Inklusif jika nota/menu kasir menampilkan harga final yang sudah mencakup pajak. Peringatan: Mengubah mode pajak akan memengaruhi kalkulasi nota transaksi baru."*

### 4.2 Tab 2: Tampilan & Skema Warna Usaha (D-43 — pengaturan per-USAHA, bukan per-user)
- **Kontrol Skema Warna Tenant (Branding Perusahaan — Tanpa Hardcode):**
  - Grid pilihan **5 tema tetap** (nilai lengkap di §7.3a; semua lolos WCAG 2.1 AA):
    1. `A — Slate + Emerald` (default, netral untuk semua bisnis)
    2. `B — Zinc + Amber` (hangat, tegas — bengkel/F&B/toko)
    3. `C — Navy + Sky` (korporat, dingin — klinik/jasa profesional)
    4. `D — Stone + Terracotta` (organik, ramah — salon/kuliner/kreatif)
    5. `E — Terang` (mode terang dari palet A)
  - Custom Color Accent Picker (opsional bila diaktifkan): owner bisa memilih warna primer brand sendiri, sistem otomatis memvalidasi rasio kontras AA sebelum disimpan.
  - **Klik salah satu kartu tema langsung mengganti tema untuk SELURUH usaha** — tersimpan server-side, berlaku untuk semua staf yang login ke company ini. **Bukan** preferensi per akun/browser.
  - Tidak ada toggle gelap/terang terpisah per pengguna. Owner yang menentukan satu tema untuk seluruh usaha (konsisten dengan branding yang dilihat pelanggan/staf).

### 4.3 Tab 3: Fitur Bisnis (1B Preset + Override)
- Dropdown preset utama diisi **dinamis** dari `business_presets` yang `is_active` (6 awal + yang ditambahkan kemudian), bukan hardcode.
- Di bawah dropdown: daftar kartu toggle **per kapabilitas** dari katalog `INDUSTRY_PRESETS.md` §1, dikelompokkan (Kontak & Peluang, Proyek, Booking & Jadwal, Stok, Kasir, Keuangan, HRD). Kapabilitas Tier B hanya tampil bila dependensinya aktif.
- Sub-tab **Istilah**: owner dapat mengubah label (`contact` → "Jemaah") — tersimpan di `module_settings[terminology]`, UI berubah seketika.
- Sub-tab **Alur**: owner dapat menambah/mengubah stage & label untuk `deal`/`project`/`booking`/`order` — tersimpan di `module_settings[workflows]`; transisi dengan `effects` keuangan tetap `requires_approval`.
- Tiap toggle memiliki switch on/off ramah mata (`peer-checked:bg-emerald-600`) dengan touch target min 44x44px.
- **Transisi Bebas Disorientasi (Tanpa Flash Reload):**
  - Saat switch toggle diubah → kirim AJAX/Fetch ke `module_settings` → tampilkan feedback inline tersimpan (`erp-success-text`) → perbarui status state navigasi Alpine secara mulus tanpa memicu full page reload mendadak.

### 4.4 Tab 4: Karyawan AI (Hermes Control Center)
- **Sub-Tab 1: Identitas & SOP (Anti-Jailbreak Form):**
  - Field: Nama Panggilan Asisten (misal: "Sari - Asisten Kasir").
  - Field: Gaya Bicara (Santun, Ringkas, Semi-Formal).
  - Field: Kebijakan Diskon Maksimal Kasir:
    - Input tipe `number`, `min="0"`, `max="100"`, `inputmode="numeric"`.
    - Teks bantu: *"Masukkan persentase diskon 0 sampai 100"*. Validasi inline menolak angka >100 atau <0.
  - Field: Jam Operasional Bisnis (Pilihan dropdown jam buka & jam tutup untuk mencegah format korup).
  - Field: Catatan Khusus untuk AI:
    - Textarea max 300 karakter.
    - Penghitung karakter live-region (`aria-live="polite"`): *"{remaining} karakter tersisa"*.
  - *Dilarang ada textarea raw system prompt / markdown.*
- **Sub-Tab 2: WhatsApp Grup & Role:**
  - Status koneksi nomor kantor (QR code scanner Baileys / Centang Biru Meta API).
  - Tabel grup aktif:
    `[ Nama Grup WA ] [ Role: Kasir / Gudang / Keuangan ] [ Status Kuota ] [ Tombol Putus ]`
  - Warning ramah jika mendekati kuota: *"2 dari 3 grup terpakai pada paket Anda."*
  - **Protokol Tindakan Destruktif (Putus Grup WA):**
    - Klik tombol "Putus" dilarang langsung menghapus data.
    - Buka dialog konfirmasi modal (`role="alertdialog"`, `aria-modal="true"`, focus trap aktif).
    - Tombol "Batal" menerima fokus awal secara default (`autofocus`).
    - Pesan lugas: *"Asisten AI akan berhenti merespons transaksi di grup ini seketika. Kuota 1 grup Anda akan dilepas kembali."*
    - Pilihan aksi: Tombol netral "Batal" vs Tombol merah primer "Ya, Putus Sambungan".

### 4.5 Tab 5: Penggunaan & Paket (Subtle Topup — Non-CTA)
- **Ringkasan Paket:** Nama paket aktif (misal: "Paket Pro - Bulanan"), status aktif, tanggal perpanjangan.
- **Meter Penggunaan Token AI Semantik:**
  - Progress bar dengan atribut semantik lengkap: `role="progressbar"`, `aria-valuenow="{pct}"`, `aria-valuemin="0"`, `aria-valuemax="100"`, `aria-label="Penggunaan Kuota Token AI"`.
  - Warna visual proporsional **via token** (§7.4 melarang kelas warna Tailwind langsung): `--erp-success` (<80%), `--erp-warning` (80–95%), `--erp-danger` (>95%).
  - Teks informatif: *"Terpakai 1.250.000 dari 3.000.000 token bulan ini (Reset 1 Oktober 2026)"*.
- **Opsi Tambah Kuota (Subtle / Elegan):**
  - Section kecil di bawah progress bar: *"Perlu kuota tambahan sebelum tanggal reset?"*
  - Pilihan paket topup nominal bersih (misal: +1 Juta Token @ Rp 100.000, +5 Juta Token @ Rp 450.000).
  - Tombol bersih: `Beli Tambahan Kuota` → buka modal QRIS terstandar.
- **Siklus State Lengkap Modal Topup QRIS:**
  1. `State Memuat (Loading)`: Skeleton placeholder QR code + teks `aria-live="polite"` *"Membuat tagihan QRIS..."*.
  2. `State Siap Bayar (Ready)`:
     - Tampilan QRIS kontras tinggi responsif (256x256px) dengan `alt="Kode QRIS Pembayaran Topup Token"`.
     - Nomor referensi tagihan yang dapat disalin 1-klik sebagai alternatif.
     - Countdown timer visual 15 menit: *"Selesaikan pembayaran sebelum {menit}:{detik}"*.
  3. `State Kedaluwarsa (Expired)`: Overlay QR berubah redup saat timer 00:00. Pesan ramah: *"Masa berlaku QRIS telah habis."* + tombol sentuh *"Buat QRIS Baru"*.
  4. `State Berhasil (Success)`: Webhook mendeteksi transfer lunas → transisi mulus ke centang hijau dengan teks: *"Pembayaran Berhasil! Kuota {jumlah} token telah aktif."* + tombol *"Tutup"* yang otomatis memperbarui progress bar tanpa refresh halaman.
  5. `A11y Dialog Contract`: `role="dialog"`, `aria-modal="true"`, focus trap aktif, tombol `Escape` menutup dialog dan mengembalikan fokus ke tombol pembuka.

### 4.6 Tab 6: Tim & Akses (RBAC)
- Manajemen staf pengguna per-company (`users` link ke `current_company_id`).
- Daftar pengguna aktif, email, role (Owner, Finance, Sales, Cashier, Warehouse).
- Form undang/tambah anggota tim baru + assign cabang (`branch_id`).
- Hak akses granular: Staf hanya melihat modul yang diizinkan oleh role mereka. **Implementasi RBAC belum dipilih** (Spatie Permission tidak terpasang); untuk fase awal cukup kolom `role` di pivot `company_user` + Laravel Gate/Policy. Menambah paket RBAC adalah dependency change yang butuh task eksplisit.

---

## 5. Dashboard Adaptif Berbasis Widget Kapabilitas (D-31)

Dashboard utama (**`/app/dashboard`**, D-24; tujuan default setelah login) terdiri dari 2 zona:
1. **Zona Universal (Atas):** widget yang hampir semua bisnis punya — `kpi_revenue`, `kpi_cashflow`, `kpi_receivables_due`, `pending_approvals`, `ai_report_card`.
2. **Zona Industri (Tengah):** widget yang **disusun oleh preset** (`definition.dashboard.industry_zone`) dan **di-bind ke kapabilitas**, bukan ke nama industri. Widget yang kapabilitasnya `false` tidak dirender.

Tidak ada komponen bernama `AgencyDealsCard` atau `RentalFleetStatusCard`. Yang
ada adalah widget generik dari katalog `INDUSTRY_PRESETS.md` §4; preset hanya
memilih susunannya. Mengganti preset company → dashboard berubah **tanpa kode**.

### 5.1 Contoh Susunan Widget per Preset Awal

| Preset | `industry_zone` (dari katalog §4) | Yang dilihat Bos |
|---|---|---|
| Agency | `deals_pipeline`, `timesheet_summary`, `kpi_receivables_due` | Deal per stage, jam kerja tim minggu ini, termin jatuh tempo |
| F&B | `open_bills`, `resources_status{type:table}`, `low_stock` | Meja terisi & bill terbuka, denah meja, bahan menipis |
| Apotek | `prescription_queue`, `expiring_batches{days:30}`, `low_stock` | Resep menunggu apoteker, obat kedaluwarsa <30 hari (FEFO) |
| EO | `upcoming_schedule`, `projects_progress`, `vendor_settlement` | Event minggu ini + countdown, progres, vendor belum lunas |
| Kontraktor | `projects_progress`, `retention_held`, `kpi_receivables_due` | Progres fisik vs target, retensi tertahan siap klaim, termin |
| Persewaan | `resources_status{group_by:status}`, `bookings_due_today`, `overdue_returns` | Unit tersedia vs tersewa, jatuh tempo hari ini, terlambat + denda |
| **Klinik (ke-7, tanpa kode)** | `upcoming_schedule`, `expiring_batches`, `low_stock` | Janji temu hari ini, obat kedaluwarsa, stok menipis |

Setiap widget menerima `props` dari preset (mis. `{days: 30}`) dan membaca
istilah via `term()` — widget `resources_status` menampilkan "Meja" di resto,
"Unit" di rental, "Kamar" di kos, dari komponen Livewire **yang sama**.

### 5.2 Standar Layar Kosong (Empty State) Friendly
Semua kartu dan tabel wajib menyertakan fallback state ramah jika data bernilai 0:
- **Tabel Grup WA Kosong:** Icon ilustrasi pesan + teks *"Belum ada grup WhatsApp yang terhubung. Hubungkan nomor kantor Anda di atas untuk mulai menambahkan grup."* + tombol aksi primer *"Pindai Kode QR"*.
- **Tim & Akses Kosong:** Teks *"Belum ada staf tambahan. Sistem saat ini hanya dikelola oleh akun Anda."* + tombol *"Undang Anggota Tim"*.
- **Widget `prescription_queue` Kosong:** Centang hijau tenang + teks *"Semua resep dokter hari ini telah selesai diproses."*
- **Widget `overdue_returns` / `bookings_due_today` Kosong:** Teks tenang *"Tidak ada {{ term('booking') }} yang jatuh tempo hari ini."* — istilah mengikuti preset (Sewa / Reservasi / Janji Temu).
- **Aturan umum:** teks empty-state **tidak boleh** menyebut industri; gunakan `term()`.

---

## 6. Standar Aksesibilitas (A11Y) & Safe CSS

1. **Semua Elemen Interaktif:**
   - Wajib memiliki `aria-label` atau `aria-labelledby`.
   - Focus outline memakai token `focus:ring-2 focus:ring-[var(--erp-focus)]`.
   - Skip-link di paling atas `app.blade.php` menuju `#main-content` (raw CSS).
2. **Kepatuhan Kontras Warna:**
   - Setiap teks baru wajib mematuhi 11 contrast pairs (§7.2) yang diuji oleh `App\Services\ThemeRegistry::passesAa()` — **kelas ini belum ada, dibuat pada T-07** beserta `tests/Unit/ThemeContrastTest.php`.
   - Teks muted wajib menggunakan `#5a6779` (rasio ≥ 5.0:1) di light mode.
3. **Target Sentuh Minimum (Touch Targets) Kasir & Mobile:**
   - Semua elemen sentuh (tombol aksi, toggle, tab navigasi, item list meja/resep) wajib berukuran minimal 44x44px (`min-h-[44px] min-w-[44px] sm:min-h-[38px]` di desktop).
   - Jarak antar elemen sentuh (spacing buffer) minimal 8px untuk mencegah insiden fat-finger kasir.
4. **Mobile Slide-Over Navigation Contract:**
   - Saat drawer slide-over terbuka:
     - Tambahkan atribut `inert` pada pembungkus `<main id="main-content">` agar fokus keyboard tidak bocor ke latar belakang.
     - Sediakan tombol tutup eksplisit: `<button type="button" class="min-h-[44px] min-w-[44px] p-2" aria-label="Tutup menu navigasi">` di kanan atas drawer.
     - Backdrop drawer: `@click="openSidebar = false"` dan listener keyboard `@keydown.escape.window="openSidebar = false"`.
5. **Responsive Breakpoints:**
   - Mobile (<640px): Sidebar beralih menjadi drawer slide-over dengan penahan scroll latar (`body { overflow: hidden }`).
   - Desktop (≥1024px): Sidebar tetap statis di sisi kiri (lebar 64 atau 72).

---

## 7. Design Token `--erp-*` (Kontrak Lengkap — ditambahkan 2026-09-16)

Ini adalah daftar **36 token** yang selama ini hanya dirujuk namanya. Tanpa
daftar ini T-07 tidak dapat dieksekusi. Semua token dideklarasikan dalam blok
`@theme` Tailwind v4 di `resources/css/app.css`, lalu di-override per tema
melalui `[data-theme="..."]` pada `<html>`.

### 7.1 Daftar Token (36)

| # | Token | Kelompok | Fungsi |
|---|---|---|---|
| 1 | `--erp-bg-base` | Latar | Latar halaman utama |
| 2 | `--erp-bg-secondary` | Latar | Sidebar, panel, kartu sekunder |
| 3 | `--erp-bg-elevated` | Latar | Kartu/modal yang "terangkat" |
| 4 | `--erp-bg-inset` | Latar | Input, area cekung |
| 5 | `--erp-bg-hover` | Latar | Hover pada item interaktif |
| 6 | `--erp-bg-active` | Latar | Item aktif/terpilih |
| 7 | `--erp-text-primary` | Teks | Teks utama |
| 8 | `--erp-text-secondary` | Teks | Teks pendukung |
| 9 | `--erp-text-muted` | Teks | Teks redup (≥ 5.0:1 di light) |
| 10 | `--erp-text-inverse` | Teks | Teks di atas warna aksen |
| 11 | `--erp-text-link` | Teks | Tautan |
| 12 | `--erp-border` | Garis | Border default |
| 13 | `--erp-border-strong` | Garis | Border penekanan/divider |
| 14 | `--erp-border-focus` | Garis | Border saat fokus |
| 15 | `--erp-accent` | Aksen | Warna brand tenant (dapat di-override owner) |
| 16 | `--erp-accent-hover` | Aksen | Aksen saat hover |
| 17 | `--erp-accent-soft` | Aksen | Latar lembut berbasis aksen (badge, highlight) |
| 18 | `--erp-focus` | Aksen | Ring fokus keyboard |
| 19 | `--erp-success` | Status | Sukses |
| 20 | `--erp-success-soft` | Status | Latar sukses lembut |
| 21 | `--erp-warning` | Status | Peringatan |
| 22 | `--erp-warning-soft` | Status | Latar peringatan lembut |
| 23 | `--erp-danger` | Status | Bahaya/destruktif |
| 24 | `--erp-danger-soft` | Status | Latar bahaya lembut |
| 25 | `--erp-info` | Status | Informasi |
| 26 | `--erp-info-soft` | Status | Latar info lembut |
| 27 | `--erp-sidebar-bg` | Komponen | Latar sidebar (boleh = bg-secondary) |
| 28 | `--erp-sidebar-text` | Komponen | Teks sidebar |
| 29 | `--erp-sidebar-active` | Komponen | Item sidebar aktif |
| 30 | `--erp-topbar-bg` | Komponen | Latar topbar/telemetri |
| 31 | `--erp-card-shadow` | Komponen | Bayangan kartu (nilai `box-shadow`) |
| 32 | `--erp-radius-sm` | Bentuk | Radius kecil (input, badge) |
| 33 | `--erp-radius-md` | Bentuk | Radius sedang (kartu) |
| 34 | `--erp-radius-lg` | Bentuk | Radius besar (modal) |
| 35 | `--erp-font-sans` | Tipografi | Font UI |
| 36 | `--erp-font-mono` | Tipografi | Font angka/telemetri (Operator Grid, D-20) |

### 7.2 Sebelas Pasangan Kontras yang Wajib Lolos WCAG AA (≥ 4.5:1)

Diuji oleh `tests/Unit/ThemeContrastTest.php` untuk **setiap** tema di §4.2:

| # | Foreground | Background |
|---|---|---|
| 1 | `--erp-text-primary` | `--erp-bg-base` |
| 2 | `--erp-text-primary` | `--erp-bg-secondary` |
| 3 | `--erp-text-primary` | `--erp-bg-elevated` |
| 4 | `--erp-text-secondary` | `--erp-bg-base` |
| 5 | `--erp-text-muted` | `--erp-bg-base` |
| 6 | `--erp-text-inverse` | `--erp-accent` |
| 7 | `--erp-text-link` | `--erp-bg-base` |
| 8 | `--erp-sidebar-text` | `--erp-sidebar-bg` |
| 9 | `--erp-text-inverse` | `--erp-success` |
| 10 | `--erp-text-inverse` | `--erp-warning` |
| 11 | `--erp-text-inverse` | `--erp-danger` |

Ring fokus (`--erp-focus`) wajib ≥ 3:1 terhadap `--erp-bg-base` (non-teks, WCAG 1.4.11).

### 7.3 Nilai Default (**Tema A — Slate + Emerald**, dark-first; D-43)

Agent **boleh** menurunkan nilai hex dari palet Tailwind v4 (slate/emerald/sky/
amber/rose) selama §7.2 terbukti lolos. Nilai di bawah adalah titik awal yang
sudah dipilih agar lolos AA; agent boleh menyesuaikan asal test tetap hijau.

```css
@theme {
  --erp-bg-base:        #0f172a; /* slate-900 */
  --erp-bg-secondary:   #1e293b; /* slate-800 */
  --erp-bg-elevated:    #334155; /* slate-700 */
  --erp-bg-inset:       #020617; /* slate-950 */
  --erp-bg-hover:       #334155;
  --erp-bg-active:      #475569; /* slate-600 */
  --erp-text-primary:   #f8fafc; /* slate-50 */
  --erp-text-secondary: #cbd5e1; /* slate-300 */
  --erp-text-muted:     #94a3b8; /* slate-400 */
  --erp-text-inverse:   #0f172a;
  --erp-text-link:      #7dd3fc; /* sky-300 */
  --erp-border:         #334155;
  --erp-border-strong:  #475569;
  --erp-border-focus:   #a78bfa; /* violet-400 */
  --erp-accent:         #a78bfa;
  --erp-accent-hover:   #c4b5fd; /* violet-300 */
  --erp-accent-soft:    #2e1065; /* violet-950 */
  --erp-focus:          #a78bfa;
  --erp-success:        #34d399; /* emerald-400 */
  --erp-success-soft:   #022c22;
  --erp-warning:        #fbbf24; /* amber-400 */
  --erp-warning-soft:   #451a03;
  --erp-danger:         #fb7185; /* rose-400 */
  --erp-danger-soft:    #4c0519;
  --erp-info:           #38bdf8; /* sky-400 */
  --erp-info-soft:      #082f49;
  --erp-sidebar-bg:     #020617;
  --erp-sidebar-text:   #cbd5e1;
  --erp-sidebar-active: #1e293b;
  --erp-topbar-bg:      #0f172a;
  --erp-card-shadow:    0 1px 2px 0 rgb(0 0 0 / 0.4);
  --erp-radius-sm:      0.375rem;
  --erp-radius-md:      0.75rem;
  --erp-radius-lg:      1rem;
  --erp-font-sans:      'Instrument Sans', ui-sans-serif, system-ui, sans-serif;
  --erp-font-mono:      ui-monospace, 'JetBrains Mono', 'Cascadia Code', monospace;
}
```

Tema lain (**B/C/D/E**, nilai lengkap di §7.3a; nama lama `Clean Ledger`/`Ocean Blue`/`Brass Amber`/`Rose` **tidak dipakai lagi** — D-43) dan mode
terang didefinisikan sebagai blok `[data-theme="clean-ledger"] { ... }` dst.
pada T-07, masing-masing wajib lolos §7.2. Custom accent picker (§4.2) hanya
mengubah `--erp-accent*` dan `--erp-focus`, lalu menjalankan `passesAa()` di
server sebelum disimpan.

### 7.3a Lima Tema Final (D-43 — sudah dipilih Bos: A default)

Struktur token tidak berubah; yang dipilih hanya **nilai** untuk `bg-*`,
`accent*`, `focus`, `text-link`. Semua kandidat dihitung lolos §7.2 (rasio
tertulis untuk pasangan terlemah). Mode terang tiap kandidat memakai
netral yang sama dengan kandidat tersebut pada skala 50-200.

| | A. Slate + Emerald | B. Zinc + Amber | C. Navy + Sky | D. Stone + Terracotta |
|---|---|---|---|---|
| Karakter | Tenang, "dashboard finansial", aman untuk semua industri | Hangat, tegas, cocok bengkel/F&B/toko | Korporat, dingin, cocok klinik/jasa | Organik, ramah, cocok salon/kuliner/kreatif |
| `--erp-bg-base` | `#0f172a` slate-900 | `#18181b` zinc-900 | `#0b1220` (navy custom) | `#1c1917` stone-900 |
| `--erp-bg-secondary` | `#1e293b` slate-800 | `#27272a` zinc-800 | `#111a2e` | `#292524` stone-800 |
| `--erp-bg-elevated` | `#334155` slate-700 | `#3f3f46` zinc-700 | `#1b2740` | `#44403c` stone-700 |
| `--erp-bg-inset` | `#020617` slate-950 | `#09090b` zinc-950 | `#060b16` | `#0c0a09` stone-950 |
| `--erp-text-primary` | `#f8fafc` | `#fafafa` | `#f1f5f9` | `#fafaf9` |
| `--erp-text-secondary` | `#cbd5e1` | `#d4d4d8` | `#cbd5e1` | `#d6d3d1` |
| `--erp-text-muted` | `#94a3b8` (7.0:1) | `#a1a1aa` (7.6:1) | `#94a3b8` (7.5:1) | `#a8a29e` (7.4:1) |
| `--erp-accent` | `#34d399` emerald-400 | `#fbbf24` amber-400 | `#38bdf8` sky-400 | `#fb923c` orange-400 |
| `--erp-accent-hover` | `#6ee7b7` emerald-300 | `#fcd34d` amber-300 | `#7dd3fc` sky-300 | `#fdba74` orange-300 |
| `--erp-accent-soft` | `#022c22` emerald-950 | `#451a03` amber-950 | `#082f49` sky-950 | `#431407` orange-950 |
| `--erp-text-inverse` on accent | `#052e16` (9.8:1) | `#1c1917` (11.9:1) | `#082f49` (8.6:1) | `#1c1917` (8.0:1) |
| `--erp-text-link` | `#7dd3fc` sky-300 | `#fcd34d` amber-300 | `#7dd3fc` sky-300 | `#fdba74` orange-300 |
| `--erp-focus` | `#34d399` | `#fbbf24` | `#38bdf8` | `#fb923c` |
| Success / Warning / Danger / Info | emerald-400 / amber-400 / rose-400 / sky-400 (sama untuk semua) | | | |
| Risiko | Aksen hijau bentrok dengan `success` -> success dipetakan ke emerald-**300** `#6ee7b7` agar berbeda tone | Aksen kuning bentrok dengan `warning` -> warning dipetakan ke amber-**200** `#fde68a` | Aksen biru bentrok dengan `info` -> info dipetakan ke cyan-400 `#22d3ee` | Aksen oranye dekat `warning` -> warning tetap amber-400, cukup berbeda hue |

**Keputusan final (D-43, Bos 2026-09-16):** **A** adalah default produk.
**5 tema tetap** — A, B, C, D, E (mode terang dari A) — dipilih **per USAHA**
oleh owner di §4.2, berlaku untuk seluruh staf company itu. Bukan preferensi
per akun/browser; tidak ada toggle personal terpisah. Accent picker per
tenant (§4.2) tetap tersedia di atas tema manapun bila diaktifkan.

```css
/* Tema A - default */
[data-theme="a"], :root {
  --erp-bg-base: #0f172a; --erp-bg-secondary: #1e293b; --erp-bg-elevated: #334155;
  --erp-bg-inset: #020617; --erp-text-primary: #f8fafc; --erp-text-secondary: #cbd5e1;
  --erp-text-muted: #94a3b8; --erp-accent: #34d399; --erp-accent-hover: #6ee7b7;
  --erp-accent-soft: #022c22; --erp-text-inverse: #052e16; --erp-text-link: #7dd3fc;
  --erp-focus: #34d399; --erp-success: #6ee7b7; --erp-warning: #fbbf24; --erp-danger: #fb7185; --erp-info: #38bdf8;
}
/* Tema B - Zinc + Amber */
[data-theme="b"] {
  --erp-bg-base: #18181b; --erp-bg-secondary: #27272a; --erp-bg-elevated: #3f3f46;
  --erp-bg-inset: #09090b; --erp-text-primary: #fafafa; --erp-text-secondary: #d4d4d8;
  --erp-text-muted: #a1a1aa; --erp-accent: #fbbf24; --erp-accent-hover: #fcd34d;
  --erp-accent-soft: #451a03; --erp-text-inverse: #1c1917; --erp-text-link: #fcd34d;
  --erp-focus: #fbbf24; --erp-success: #34d399; --erp-warning: #fde68a; --erp-danger: #fb7185; --erp-info: #38bdf8;
}
/* Tema C - Navy + Sky */
[data-theme="c"] {
  --erp-bg-base: #0b1220; --erp-bg-secondary: #111a2e; --erp-bg-elevated: #1b2740;
  --erp-bg-inset: #060b16; --erp-text-primary: #f1f5f9; --erp-text-secondary: #cbd5e1;
  --erp-text-muted: #94a3b8; --erp-accent: #38bdf8; --erp-accent-hover: #7dd3fc;
  --erp-accent-soft: #082f49; --erp-text-inverse: #082f49; --erp-text-link: #7dd3fc;
  --erp-focus: #38bdf8; --erp-success: #34d399; --erp-warning: #fbbf24; --erp-danger: #fb7185; --erp-info: #22d3ee;
}
/* Tema D - Stone + Terracotta */
[data-theme="d"] {
  --erp-bg-base: #1c1917; --erp-bg-secondary: #292524; --erp-bg-elevated: #44403c;
  --erp-bg-inset: #0c0a09; --erp-text-primary: #fafaf9; --erp-text-secondary: #d6d3d1;
  --erp-text-muted: #a8a29e; --erp-accent: #fb923c; --erp-accent-hover: #fdba74;
  --erp-accent-soft: #431407; --erp-text-inverse: #1c1917; --erp-text-link: #fdba74;
  --erp-focus: #fb923c; --erp-success: #34d399; --erp-warning: #fbbf24; --erp-danger: #fb7185; --erp-info: #38bdf8;
}
/* Tema E - mode terang dari A */
[data-theme="e"] {
  --erp-bg-base: #f8fafc; --erp-bg-secondary: #ffffff; --erp-bg-elevated: #f1f5f9;
  --erp-bg-inset: #e2e8f0; --erp-text-primary: #0f172a; --erp-text-secondary: #334155;
  --erp-text-muted: #5a6779; --erp-accent: #059669; --erp-accent-hover: #047857;
  --erp-accent-soft: #d1fae5; --erp-text-inverse: #ffffff; --erp-text-link: #0369a1;
  --erp-focus: #059669; --erp-success: #059669; --erp-warning: #b45309; --erp-danger: #be123c; --erp-info: #0369a1;
}
```

Nilai di atas mengikuti pola token yang sama dengan §7.3 (36 token penuh
per tema didefinisikan sama, hanya 17 yang berbeda antar tema ditampilkan di
sini untuk ringkas; sisanya mengikuti radius/font/shadow bersama). T-07
wajib menulis 36 token lengkap × 5 tema dan membuktikan 11 pasangan kontras
lolos AA untuk **kelima** tema (`ThemeContrastTest`, 55 kasus).


### 6.9 Aturan dari Temuan UX Mockup (2026-09-16)

1. **Pajak (D-44).** `tax_mode=non_taxable` -> tidak ada baris DPP/PPN di
   layar, struk, faktur, dan laporan. Jangan tampilkan `Rp 0`. Label pada
   mode `taxable` memakai bahasa awam: "Harga sudah termasuk pajak" /
   "Pajak ditambahkan".
2. **Konfirmasi berisiko (D-45) - tiga tingkat.** Tingkat 1 (ketik `YA`) hanya untuk aksi tak dapat dibatalkan berdampak legal/fiskal; tingkat 2 (dua tombol, aksi merah) untuk destruktif yang dapat dipulihkan; tingkat 3 tanpa konfirmasi. Tombol aksi **inert 400 ms** sejak modal muncul. Type-to-confirm dengan
   `strtoupper(trim($input)) === 'YA'`; `inputmode="text"`
   `autocapitalize="characters"` `autocorrect="off"` `spellcheck="false"`.
   Modal menyebut akibat konkret + nominal. Tekan-tahan dilarang untuk aksi
   finansial.
3. **Perpindahan tahap (D-46).** Tidak boleh ada jalan buntu: setiap tahap non-terminal punya transisi keluar, tahap QC/pemeriksaan wajib punya cabang mundur (rework). Transisi mundur membuka isian **alasan singkat** yang tercatat di log. Tombol "tahap berikutnya" untuk alur normal, plus
   menu pada badge tahap berisi **hanya transisi yang sah** dari stage saat
   ini menurut preset. Tidak ada daftar stage penuh yang bisa diklik bebas.
### 6.10 Kontrak Overlay & Navigasi (temuan audit mockup 2026-09-16)

Berlaku untuk command palette, drawer mobile, dan semua modal:

- `role="dialog"` + `aria-modal="true"` + `aria-labelledby` menunjuk judulnya.
- Fokus berpindah ke overlay saat dibuka, **terperangkap** di dalamnya
  (focus trap), dan kembali ke elemen pemicu saat ditutup.
- `Esc` selalu menutup. Klik area gelap menutup (kecuali modal konfirmasi
  tingkat 1 D-45).
- Elemen latar diberi `inert`/`aria-hidden` selama overlay terbuka.

Navigasi bawah (mobile) dan sidebar:

- Item aktif wajib `aria-current="page"` - bukan sekadar beda warna.
- Tombol pembuka drawer wajib `aria-expanded` dan `aria-controls`.
- Konten utama diberi padding bawah agar tidak tertutup bilah navigasi.

Ditegakkan sebagai test di T-F14 (`NoLiteralTermsTest` diperluas menjadi
audit a11y: setiap overlay punya `role=dialog`+`aria-modal`; setiap nav punya
tepat satu `aria-current`).
### 6.11 Keadaan Akun: Trial, Menunggak, Hanya-Baca, Beku (D-49/D-51)

Belum pernah dispesifikasi sebelumnya. Wajib ada sebelum T-12b/T-18.

| Status | Yang dilihat pengguna | Aturan UI |
|---|---|---|
| `trial` | Pita halus di topbar: *"Masa coba — sisa N hari"* + tautan "Lihat paket" | Tidak mengganggu kerja. Tidak ada modal paksaan. Hitung mundur **hari**, bukan jam (mengurangi tekanan) |
| `active` | Tidak ada pita sama sekali | Keadaan normal |
| `ai_suspended` (H+0) | Pita kuning: *"Asisten WhatsApp berhenti sementara — tagihan belum dibayar"* + tombol bayar | **Web tetap berfungsi penuh.** Menu asisten AI menampilkan penjelasan, bukan error |
| `read_only` (H+7) | Pita oranye permanen: *"Mode hanya-baca. Data Anda aman dan bisa diunduh."* | Semua tombol simpan/tambah/hapus **dinonaktifkan dengan penjelasan** (bukan hilang — pengguna harus paham kenapa). Tombol **Unduh Data** dan **Bayar** tetap menonjol. Percobaan tulis → 423 + pesan ramah |
| `frozen` (H+30) | Halaman tunggal saat login: ringkasan tagihan + tombol bayar + **tombol unduh data** | Tidak masuk ke aplikasi. **Jangan** tampilkan pesan menakut-nakuti. Sebut tanggal data akan dihapus secara jujur |
| `cancelled` | Sama seperti `frozen` + konfirmasi bahwa langganan dihentikan atas permintaan | Unduh data tetap tersedia sampai masa retensi habis |

**Prinsip yang tidak boleh dilanggar:**
- **Ekspor data selalu tersedia** di setiap status (D-51) — termasuk saat
  menunggak. Jangan pernah menyandera data sebagai alat tekan.
- Nada pesan: **memberi tahu, bukan mengancam.** Sebut angka dan tanggal
  konkret, bukan "akun Anda bermasalah".
- Pita status memakai token warna (`--erp-warning`, `--erp-danger`), bukan
  kelas Tailwind langsung (§7.4).
- Pita tidak boleh menutupi navigasi bawah pada mobile.

### 7.4 Larangan

- Dilarang memakai `bg-gray-*`, `text-gray-*`, `bg-slate-*` dst. **langsung** pada
  komponen baru setelah T-07. Gunakan `bg-[var(--erp-bg-base)]` atau utility
  yang dipetakan ke token.
- Dilarang hardcode hex di Blade.
- Komponen Fase 1 (`lobby`, `sidebar`, `command-palette`) **dimigrasikan ke token
  pada T-07** sebagai bagian dari acceptance, bukan dibiarkan.
