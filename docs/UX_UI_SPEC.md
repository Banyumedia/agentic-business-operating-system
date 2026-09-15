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

Sidebar dirender di `resources/views/layouts/navigation.blade.php` via `DynamicMenuRegistry`.

### 3.1 Aturan Rendering
- Item menu level-1 hanya muncul jika minimal satu sub-menunya lolos `visible_when`.
- Item menu level-2 hanya muncul jika `company->feature($key) === true`.
- Menu yang gagal evaluasi: `null` (tidak menghasilkan DOM).

### 3.2 Visual State & Token CSS
- Active menu: `bg-[var(--erp-bg-secondary)] text-[var(--erp-text-primary)] border-l-4 border-[var(--erp-accent)] font-semibold`
- Inactive menu: `text-[var(--erp-text-secondary)] hover:text-[var(--erp-text-primary)] hover:bg-[var(--erp-bg-secondary)]`
- Section divider: `border-t border-[var(--erp-border)]`

---

## 4. Halaman Pengaturan Friendly (Tab-Based)

Semua pengaturan disatukan dalam satu halaman terpusat: `/settings` dengan navigasi **Tab Horizontal**:

```
┌─────────────────────────────────────────────────────────────────────────────────────────────┐
│ PENGATURAN BISNIS & SISTEM                                                                  │
│                                                                                             │
│ [ Profil Bisnis ] [ Tampilan & Tema ] [ Fitur & Modul ] [ Karyawan AI ] [ Paket & Kuota ] [ Tim & Akses ] │
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

### 4.2 Tab 2: Tampilan, Skema Warna & Preferensi Gelap/Terang (U-06)
- **Kontrol Skema Warna Tenant (Branding Perusahaan - Tanpa Hardcode):**
  - Dropdown/Grid Pilihan Tema Bawaan (Semua Lolos WCAG 2.1 AA):
    1. `Prime Default / Midnight` (Slate gelap, aksen ungu-emerald)
    2. `Clean Ledger` (Putih bersih, aksen perbankan/fintech modern)
    3. `Ocean Blue` (Navy korporat, aksen biru laut)
    4. `Brass Amber` (Cokelat tembaga hangat, aksen emas)
    5. `Rose` (Marun pekat, aksen mawar)
  - Custom Color Accent Picker: Owner bisa memilih warna primer brand sendiri, sistem otomatis memvalidasi rasio kontras AA sebelum disimpan.
- **Preferensi Mode Gelap/Terang Personal (Per-User):**
  - Opsi: `Ikuti Sistem OS (Auto)` | `Terang (Light)` | `Gelap (Dark)`.
  - Tersimpan di level akun / session pengguna, tidak mengganggu pengguna lain.

### 4.3 Tab 3: Fitur & Modul Bisnis (1B Preset + Override)
- Dropdown preset utama: `Agency | F&B / Resto | Apotek | Event Organizer | Kontraktor | Persewaan | Kustom`.
- Di bawah dropdown: Daftar kartu toggle per kategori (CRM, POS, Operasional, Inventori, Sales).
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
  - Warna visual proporsional: `bg-emerald-600` (<80%), `bg-amber-500` (80-95%), `bg-rose-600` (>95%).
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
- Hak akses granular: Staf hanya melihat modul yang diizinkan oleh role mereka sesuai Spatie Permission.

---

## 5. Dashboard Adaptif & Card Khusus per Industri

Dashboard utama (`/dashboard`) terdiri dari 2 zona:
1. **Zona Universal (Atas):** Action Cards (Perlu Aksi Hari Ini) + Metrik Keuangan Ringkas (Omzet, Arus Kas Masuk, Piutang Jatuh Tempo).
2. **Zona Industri (Tengah):** Card khusus yang dirender kondisional berdasarkan preset bisnis aktif.

### 5.1 Card Khusus Industri

| Industri | Card Khusus yang Dirender | Data & Aksi Cepat |
|---|---|---|
| **1. Agency** | `AgencyDealsCard` + `TimesheetSummaryCard` | 3 Deal tahap negosiasi, Total jam kerja tim minggu ini, 2 Invoice termin jatuh tempo. |
| **2. F&B** | `FnBTablesLiveCard` + `KitchenPendingCard` | Visual denah meja (Meja terisi vs kosong), 4 pesanan belum bayar (Open Bill), shortcut *Buka Kasir Cepat*. |
| **3. Apotek** | `PharmacyPrescriptionQueueCard` + `ExpiringDrugsAlertCard` | 5 e-Resep menunggu verifikasi Apoteker, 3 obat mendekati expired <30 hari (FEFO warning). |
| **4. Event Org** | `UpcomingEventsCard` + `VendorSettlementCard` | 2 Event berjalan minggu ini, countdown rundown, 4 vendor menunggu pelunasan. |
| **5. Kontraktor** | `ProjectProgressCard` + `RetentionReceivableCard` | Bar progres fisik proyek (%) vs target waktu, nominal dana retensi tertahan yang siap diklaim. |
| **6. Persewaan** | `RentalFleetStatusCard` + `RentalDueReturnCard` | Unit tersedia vs tersewa, 3 unit jatuh tempo kembali hari ini, shortcut *Check-in Unit*. |

### 5.2 Standar Layar Kosong (Empty State) Friendly
Semua kartu dan tabel wajib menyertakan fallback state ramah jika data bernilai 0:
- **Tabel Grup WA Kosong:** Icon ilustrasi pesan + teks *"Belum ada grup WhatsApp yang terhubung. Hubungkan nomor kantor Anda di atas untuk mulai menambahkan grup kasir atau gudang."* + tombol aksi primer *"Pindai Kode QR"*.
- **Tim & Akses Kosong:** Teks *"Belum ada staf tambahan. Sistem saat ini hanya dikelola oleh akun Anda."* + tombol *"Undang Anggota Tim"*.
- **Kartu Resep Apotek Kosong:** Centang hijau tenang + teks *"Semua resep dokter hari ini telah selesai diproses."*
- **Kartu Jatuh Tempo Persewaan Kosong:** Teks tenang *"Tidak ada pengembalian unit sewa yang jatuh tempo hari ini."*

---

## 6. Standar Aksesibilitas (A11Y) & Safe CSS

1. **Semua Elemen Interaktif:**
   - Wajib memiliki `aria-label` atau `aria-labelledby`.
   - Focus outline memakai token `focus:ring-2 focus:ring-[var(--erp-focus)]`.
   - Skip-link di paling atas `app.blade.php` menuju `#main-content` (raw CSS).
2. **Kepatuhan Kontras Warna:**
   - Setiap teks baru wajib mematuhi 11 contrast pairs yang diuji oleh `ThemeRegistryService::passesAa()`.
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
