# Roadmap & Spesifikasi Teknis: UX/UI Mobile-First Agentic BOS

Dokumen ini adalah acuan resmi hasil sesi peninjauan dan wawancara desain (*grill-me*) tanggal 2026-09-21, yang disepakati untuk meningkatkan pengalaman pengguna, efisiensi operasional di perangkat mobile, dan kepatuhan arsitektur D-31.

---

## 1. Prinsip Utama & Keputusan Arsitektur

1. **Mobile-First Thumb-Zone Ergonomics**:
   - Mayoritas operasional UMKM di Indonesia berjalan lewat ponsel cerdas staf dan kasir.
   - Navigasi primer dipindahkan ke area jangkauan jempol bawah (**Bottom Navigation Bar** di `<768px`) dan **Quick Actions Bar** di bagian atas beranda.
2. **Kepatuhan Invarian D-31 (*Industry is Data, Capability is Code*)**:
   - Tidak ada tombol aksi cepat atau label menu yang meng-hardcode nama industri.
   - Seluruh label dihasilkan dari kamus istilah `term()`, dan opsi aksi cepat diturunkan secara deklaratif dari skema preset (`database/presets/*.json`) dengan fallback ke kapabilitas operasional aktif.
3. **Karyawan AI (Hermes) WhatsApp-First Murni**:
   - Antarmuka web fokus sebagai kanvas analitik, audit log terverifikasi, dan manajemen SOP (`/app/settings/assistant`).
   - Interaksi percakapan dinamis dan eksekusi instruksi tetap eksklusif di WhatsApp dengan tautan handoff satu klik *"Lanjutkan Diskusi di WhatsApp"*.
4. **Adaptive POS Kiosk Mode**:
   - Modul Kasir di layar sempit/tablet (`<1024px`) otomatis mengisolasi diri dari navigasi umum ERP untuk mencegah salah klik dan memaksimalkan ruang belanja kasir.
5. **Zero Layout-Shift Feedback**:
   - Sistem notifikasi mengambang (**Toast / SnackBar**) menggantikan kotak banner statis di bawah header yang memicu lonjakan layout halaman.
6. **Compact Mobile Data Controls**:
   - Menggantikan deretan tombol sortir kolom bertumpuk di HP dengan satu baris ringkas: Pencarian + Dropdown Sortir + Toggle Arah.

---

## 2. Rincian Fase Implementasi

### Fase 0: Baseline Quality & Pre-flight
- Pembersihan literal kata terlarang D-31 di view admin dan pengaturan asisten.
- Penambahan `<env name="DATA_SOURCE" value="json"/>` di `phpunit.xml`.
- Memastikan 913/913 pengujian unit & fitur berstatus **HIJAU**.

### Fase 1: Quick Actions Bar di Dashboard
- Deklarasi tombol aksi cepat kontekstual di `DashboardComposer.php`.
- Tampilan strip tombol aksi di `dashboard.blade.php`.
- Pengujian otomatis `DashboardQuickActionsTest.php`.

### Fase 2: Compact Mobile Data-Table
- Refactor kontrol sortir pada `resources/views/components/data-table.blade.php`.
- Memastikan penghematan 60-70% ruang vertikal sebelum daftar kartu data di ponsel.

### Fase 3: Adaptive Kiosk Mode Kasir (POS)
- Isolasi layout kasir mobile di `resources/views/livewire/screens/cashier.blade.php` dan `CashierScreen.php`.
- Tombol proteksi "Tutup Kasir / Kembali".

### Fase 4: Bottom Navigation Bar Mobile
- Penambahan bar navigasi bawah 4 pilar di `resources/views/components/layouts/module.blade.php`: Beranda, Kasir, Buku Kas, Menu Lengkap.
- Penyesuaian safe area padding (`pb-20 md:pb-6`).

### Fase 5: Global Floating Toast Notification System
- Komponen Toast melayang otomatis di `module.blade.php`.
- Auto-dismiss dalam 3.5 detik dengan opsi tutup manual.

---

## 3. Matriks Verifikasi & Kriteria Penerimaan

Setiap fase wajib memverifikasi:
- `php artisan test` (0 failures).
- `vendor/bin/pint --test` (0 violations).
- `npm run build` (0 build errors).
- Pengujian visual responsif viewport 360px, 390px, 768px, dan 1280px.
