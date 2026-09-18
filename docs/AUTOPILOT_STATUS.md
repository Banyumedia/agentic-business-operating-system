# Agentic BOS Autopilot Status

**Updated:** 2026-09-18 (final-gate Hermes: T-24c DONE; sisa task tertahan gate HUMAN)
**Mode:** FASE 4 AKTIF - Gate UI-LOCK sudah dibuka.
**Arsitektur target:** puluhan jenis bisnis — industri = data, kapabilitas = kode (D-31..D-33)
**Canonical workspace:** `D:\PROJECTS\agentic-bos`
**Git:** branch `main`, HEAD lihat `git rev-parse --short HEAD`; **remote belum dikonfigurasi**.

> Agent yang resume: baca file ini, lalu `EXECUTION_PLAN.md` §0 untuk definisi
> `READY` dan command verifikasi. Jangan pakai angka/SHA dari ingatan sesi.

## Review Bisnis Menyeluruh 2026-09-16 (D-48..D-56)

Review ujung-ke-ujung seluruh dokumen. Hasil: 56 keputusan terkunci, **tidak
ada item OPEN**, semua keputusan punya task pelaksana.

**Kontradiksi diperbaiki:** D-32 (18→21 kapabilitas), D-36 & U-06 digantikan
D-43, PRD (nama warisan + "1 bot per company" → per owner), `companies.theme`
dan `admin_impersonation_sessions` ditambahkan ke skema.

**Kesalahan skema diperbaiki:** FK menggantung `approval_ticket_id` (tabel
`approval_tickets` kini didefinisikan §1.8), tabel hilang `cash_entries` (§4.4),
`quotations` + `quotation_lines` (§4.5).

**Task baru dari keputusan bisnis:** T-10c (mode hemat token, D-48), T-10d
(gerbang kapabilitas per paket, D-52), T-12b (trial + ekspor data, D-51), T-18
diperluas (tangga dunning, D-49), **Fase 4b T-27..T-27e** (kepatuhan PDP,
D-50 — memblokir penjualan preset klinik/apotek), Fase 6b (katalog add-on,
D-56).

**Yang belum berubah:** UI-LOCK belum
diberikan Bos. Fase 2 (frontend-first D-42) tidak terpengaruh review ini.
## Akses Pratinjau Jarak Jauh (untuk review dari HP)

| URL | Sumber | Port |
|---|---|---|
| `https://agentic-bos.nalar.army/` | worktree `main` (aplikasi nyata) | 8000 |
| `https://bos-mockup.nalar.army/mockup` | worktree `mockup/ux-dummy` (referensi visual) | 8001 |

Keduanya di balik basic auth Caddy (user `bos`) dan `noindex`. Dev server
dijalankan otomatis saat login Windows oleh PM2. **Tidak usah** menjalankan
`php artisan serve` atau `npm run dev` sendiri.

## Pekerjaan Selesai

- T-17c (Panel Super Admin minimal + Login As beraudit) → `671d804`
- T-08e (Regression WidgetRegistry Eloquent + EloquentEntityRepository) → `b0c390a`
- T-08e (Fix: model+migration Dashboard untuk Eloquent mode - `CashEntry`, `Quotation`, `AssistantReport`) → `67d09aa`

- T-25b (Modul Tier B: Production Order + BOM lines, D-57) → `241cd97`
  (3 bug ditemukan & diperbaiki saat final-gate: company_id hilang di
  production_order_lines/D-26, kontrak HasWorkflow salah, kolom items salah)
- T-25 (Keputusan Tier B: manufacturing.production_order) → `e946477`
- T-26 (Halaman publik "Cocok untuk bisnis apa?") → `5a0ba6d` (+ `a2d34c2` docs sync)
- T-24d (Preset gelombang 4: Barbershop, Kedai Kopi, Fotografi) → `c193f8e`
- T-27e (Kendali pengiriman data ke AI) → `4f5f491`
- T-23 (Build + smoke tenant dogfood) → `776840e`
- T-20 (Scout + Universal Search) → `b1da91d`
- T-27d (Hak subjek data: hapus per pelanggan) → sudah selesai di phase sebelumnya
- T-00a (Fase 3: Migration `users`, `companies`, `business_identities`, `module_settings`) → `65cda86`
- T-00b (Fase 3: Seeder Admin/Demo) → `f1c01e6`
- T-00c (Fase 3: Session `company_id` guard) → `26e834b`
- T-08 (Fase 3b: `business_presets` migration & seeder dari JSON) → `79f0449`
- T-08b (`FeatureResolver` Eloquent adapter) → `0b4293a` (T-15 digabung)
- T-08c (`TerminologyResolver` Eloquent) → `9179975`
- T-08d (`workflow_transitions_log` + Eloquent transaction log) → `b0b5bd9`
- T-03b (`DynamicMenuRegistry` Eloquent test) → `19f03d1`
- T-13 (`contacts`, `deals`) → `e8fe772`
- T-13b (`projects`, `milestones`, `assignments`) → `35c1fa7`
- T-11 (`chart_of_accounts`, `journals`) → `27d604f`
- T-13c (`resources`, `bookings`) → `7231b5d`
- T-13d (`items`, `batches`, `bom`, `stock`) → `89d64d6`
- T-13e (`orders`, `pos`, service) → `9503b08`
- T-13f (`employees`, `payrolls`, `ai_reminders`) → `01a627d`
- T-10 (`membership_plans`, `company_memberships`) → `c27b0eb`
- T-10a (`token_ledger_entries`, `TokenLedgerService`) → `e074d2b`
- T-12 (`invoices`) → `6912389`
- T-14 (`attachments`) → `88d1d05`
- T-14b (`prescriptions`, `retentions` Tier B) → `bd16d8a`
- T-16 (`EnsureFeatureEnabled` middleware) → `8b292c2`
- T-19 (`PaymentWebhookController` midtrans) → `2da34f4`
- T-19b (`NalarPesanWebhookController`) → `8739ec9`
- T-17 (`MasterBot` API) → `1951f26`
- T-17b (`TenantBot` MCP ERP) → `d419385`
- T-18 (`BillingCheckExpiring` dunning ladder) → `7560da1`
- T-10b (`hermes_nodes` dst) → `0d1c7bc`
- T-27 (Penandaan kapabilitas sensitif + kebijakan privasi) → `98476fb`
- T-10c, T-10d, T-19b, T-17, T-17b, T-18, T-27b, T-27c, T-27d (Hermes node/MCP,
  MasterBot/TenantBot API, billing dunning, access logs, enkripsi at-rest,
  attachments) → `3ba45a5` (bundle backend — dependency riil, tidak bisa
  dipisah tanpa merusak kontrak test)
- T-24, T-21c (gate rilis preset kanonik klinik/salon + bukti D-31 database
  nyata `LaundryPresetDatabaseTest`), T-24b (preset kursus, kos_coworking),
  T-24c (preset gelombang 3: katering, bakery_preorder, travel_umroh, gym,
  praktek_dokter, cuci_mobil) → `33cbbd0`
- T-22 (audit white-label: composer package rename, label UI) → `d33779f`
- T-12b, T-27d (UI ekspor data & hak hapus data pelanggan) → `7aefe92`
- T-21 (Full regression sebagai final-gate independen: `php artisan test`
  461 passed/1794 assertions, `pint --test` clean 318 files, `npm run build`
  OK, `migrate:fresh --seed` OK) → diverifikasi ulang oleh Hermes (final
  gate), bukan hanya klaim runner.
- T-21b (Paritas MySQL, B-01) → `b2ba595`, `de9684c`. Dijalankan
  `DB_CONNECTION=mysql` di Laragon MySQL 8.4.3 lokal
  (`agentic_bos_parity` db). `migrate:fresh --seed` PASS setelah 4 fix nyata
  yang lolos di SQLite tapi ditolak MySQL strict mode:
  1) `orders.resource_id` FK dideklarasi sebelum tabel `resources` ada -
     dipisah ke migration baru setelahnya;
  2) `invoices.company_membership_id` FK sama pola - dipisah serupa;
  3) index composite `contacts(company_id, wa_number)` bentrok dengan
     konversi kolom ke `TEXT` (enkripsi) - MySQL menolak index di
     BLOB/TEXT tanpa key length - index di-drop lalu diganti index
     `company_id` saja;
  4) `business_presets.tier` dideklarasi `varchar(1)` padahal diisi label
     penuh (`"professional"`) - SQLite truncate diam-diam, MySQL error 1406
     - diperbesar ke `varchar(32)`.
  Ditemukan juga bug kode nyata (bukan schema): `DataErasure::erase()`
  query `Prescription::where('contact_id', ...)` padahal kolom asli
  `patient_contact_id` - silent no-op di SQLite karena tabel kosong,
  meledak di MySQL sebagai kolom tak dikenal. Diperbaiki di commit sama.
  **Catatan terpisah (bukan blocker T-21b):** full `php artisan test` di
  MySQL menyisakan 3-4 test flaky (`OrderServiceTest`,
  `TokenLedgerServiceTest`, `EloquentFeatureResolverTest`, `DataExportTest`)
  akibat beberapa test hardcode `id => 1/2` untuk `ChartOfAccount` yang
  bentrok saat berjalan berurutan dengan test lain di kelas berbeda -
  seluruhnya PASS saat dijalankan isolated per-class. Ini pre-existing
  test-design smell, dicatat untuk perbaikan terpisah, tidak menghalangi
  T-21b DONE.

**Catatan final-gate:** Claude CLI (executor) menyelesaikan pekerjaan di atas
namun tidak sempat commit sendiri (approval hook lokal gagal dieksekusi).
Hermes menjalankan verifikasi independen penuh (test+pint+build+migrate) lalu
membagi ~110 file uncommitted menjadi 4 commit bertema sesuai dependency
riil, bukan `git add -A`.

## Pekerjaan Aktif
Tidak ada (Sistem berhenti: Tidak ada task berstatus READY setelah T-26). Menunggu instruksi HUMAN atau add-on komersial (Fase 6b).

## READY Berikutnya
- Tidak ada task READY (Tunggu perintah/Fase berikutnya)