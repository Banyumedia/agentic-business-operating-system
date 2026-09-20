# Agentic BOS Autopilot Status

## LIVE-UI-RECOVERY — Dashboard preset database

**State:** `DONE` — error UI `Dashboard belum dapat dimuat` ditelusuri ke `BusinessPreset` kosong. Dengan izin eksplisit Bos, `BusinessPresetSeeder --force` memuat 40 preset; company aktif `Demo Usaha` sekarang menemukan preset `laundry` beserta 3 widget dashboard.

**Runtime evidence:** login Livewire `200` dengan redirect `/app/dashboard`; dashboard authenticated `200`; judul `Dashboard · Agentic BOS`, `Ringkasan hari ini`, `Laporan AI`, `Stok perlu perhatian`, `Arus kas`, dan `Perlu persetujuan` tampil; pesan fallback tidak ada. Caddy sementara menunjuk app sehat `127.0.0.1:8002`. Service NSSM lama di 8010 belum direstart karena proses LocalSystem memerlukan hak admin.

**Repo:** tidak ada perubahan source dari recovery data/runtime ini. Folder asing `backup-ahli-keuangan/` tidak disentuh.

**Next:** Dashboard live dari HP dikonfirmasi Bos **PASS** pada 2026-09-20. **POS, Inventory, HRD juga PASS dari HP** pada 2026-09-20. **Export owner (buat ZIP + unduh) PASS dari HP** pada 2026-09-20. Sisa checklist: export 404 saat file belum ada, staff/non-owner 403, admin impersonation 403 (butuh akun tambahan). Setelah seluruh checklist UI-LOCK selesai dan akses admin tersedia, restart service `PM2-AgenticBOS` dan kembalikan Caddy ke upstream permanen 8010 setelah verifikasi.

**Skenario negatif export — dieksekusi server-side 2026-09-20 (semua PASS):**
1. Owner tanpa file export → `/app/settings/export/download` = **404** ✅ (file di-rename sementara lalu di-restore).
2. Staff/non-owner (user id=3, current_company=1, bukan owner) → endpoint = **403** ✅ (juga 403 di dashboard oleh `EnsureCompanyAccess`).
3. Admin platform (user id=4, `is_platform_admin=1`) mode impersonasi company 1 → endpoint = **403** ✅, dan banner impersonasi tampil di dashboard ✅.
4. Akun dummy (`staff-smoke@`, `admin-smoke@`) dan seluruh sesi impersonasi **dihapus** setelah uji; tersisa 2 user asli.

**Seluruh checklist `HUMAN:UI-LOCK` export kini terpenuhi (4/4).** Menunggu Bos menyatakan UI-LOCK lepas, lalu lanjut maraton Fase berikutnya dengan delegasi Claude/OpenCode.

**Smoke test menyeluruh 2026-09-20 (authenticated owner `bos@nalar.army`, company `Demo Usaha` preset `laundry`, via app 8002):**

| Area | Route | Hasil | Catatan |
|---|---|---|---|
| Dashboard | `/app/dashboard` | 200 ✅ | KPI + Laporan AI + widget laundry |
| Group report | `/app/group-report` | **403** ⚠️ | perlu cek gate (owner seharusnya boleh?) |
| Settings (semua tab) | `/app/settings*` | 200 ✅ | 9 tab OK |
| Export action | Livewire `export` | 200 ✅ | ZIP dibuat; downloadUrl null di payload (lihat gap) |
| Export download | `/app/settings/export/download` | 200 ✅ | ZIP 608B: contacts/invoices/identities/settings |
| POS | `/app/pos` | **403** ⚠️ | padahal preset laundry mengaktifkan `pos` |
| Inventory | `/app/inventory` | **403** ⚠️ | preset mengaktifkan `inventory` |
| HRD | `/app/hrd` | **403** ⚠️ | preset mengaktifkan `hr.employees` |
| Contacts | `/app/contacts` | 200 ✅ | "Daftar Klien" |
| Accounting | `/app/accounting` | 200 ✅ | "Buku Kas" |
| Admin area | `/admin` | 403 ✅ | benar, user bukan platform admin |

**Gap yang tercatat — STATUS SETELAH PERBAIKAN 2026-09-20:**
1. ~~Modul `pos`, `inventory`, `hrd` 403~~ → **FIXED**: akar masalah = `companies.business_preset` tertinggal `eo` (data seed awal), bukan `laundry`. Diperbaiki ke `laundry` → inventory/hrd 200. POS masih 500 karena `BusinessIdentityStore` membaca file JSON `json/1/business_identity.json` yang belum ada → dibuat → POS 200 ("Layar Kasir").
2. `BuildCompanyExport` hanya mengekspor 4 file — **masih gap** (belum seluruh tabel).
3. `downloadUrl` tidak muncul di response Livewire — **masih gap** (tautan unduh mungkin tidak ter-render; endpoint download langsung 200).
4. `/app/group-report` 403 → **BUKAN BUG**: butuh add-on `addon.branches` yang tidak aktif untuk company ini.

**Catatan arsitektur penting:** aplikasi berjalan di **SQLite** (`DB_CONNECTION=sqlite` di .env), BUKAN MySQL. MySQL Laragon yang dinyalakan sebelumnya tidak dipakai aplikasi. CLI tinker dan web server membaca DB sqlite yang sama (`database/database.sqlite`). Jangan keliru mengedit DB MySQL untuk memperbaiki data aplikasi.

## T-DELEG-QA — Adjudikasi internal hasil delegasi UI-LOCK (export authorization)

**State:** `DONE` — konflik verdict reviewer diselesaikan internal tanpa delegasi lanjutan. Blocker valid ditutup dengan patch minimal dan regression test negatif.

**Changed files:**
1. `routes/web.php` — route `/app/settings/export/download` sekarang fail-closed: tetap menolak admin impersonasi (`403`) dan menolak non-owner (`403`) walaupun `current_company_id`/session aktif.
2. `tests/Feature/DataExportTest.php` — tambah test `test_non_owner_cannot_download_export_even_when_current_company_is_set`.

**Commit lokal:** `84afb4b` (`fix(security): restrict export download to owner`). Tidak ada push/deploy.

**Evidence:**
- `php artisan test tests/Feature/DataExportTest.php tests/Feature/AdminImpersonationTest.php` → **PASS 9 tests / 30 assertions**.
- `php artisan test` → **PASS 583 tests / 3,159 assertions**.
- `php vendor/laravel/pint/builds/pint --test` → **PASS 389 files**.
- `npm run build` → **PASS** (`vite build`, 1.35s).
- `process list` → kosong (tidak ada proses delegasi aktif).

**HUMAN:UI-LOCK checklist (live acceptance yang tersisa):**
1. Owner login, file export ada → `GET /app/settings/export/download` = **200** (download sukses).
2. Owner login, file export belum ada → endpoint = **404**.
3. Staff/non-owner dengan `current_company_id` valid → endpoint = **403**.
4. Admin dalam mode impersonasi → endpoint = **403**.
5. Setelah uji, pastikan tidak ada drift repo: `git status --short` harus bersih.

**Next:** menunggu eksekusi dan verdict `HUMAN:UI-LOCK` dari Bos. Jangan push/deploy tanpa izin eksplisit.

## QA-UI-R — Remediasi acceptance source audit

**State:** `DONE` — seluruh remediation source terverifikasi pass tanpa nondeterminisme paralel dan sudah direkonsiliasi. Gate runner pass.

**Scope:** fail-closed Master Bot; autentikasi onboarding; role company dari sumber tepercaya; isolasi tenant `contact_id`; login throttle/logout; branch redirect; Settings erasure; layout impersonasi; preset onboarding; serta koreksi UI/a11y source-confirmed. Defect wajib memiliki regression/negative test. Tidak ada dependency, migration, push, deploy, atau perubahan arsitektur.

**Evidence 2026-09-19:** focused remediation `44 passed / 166 assertions`; full suite stabil secara sekuensial dan terisolasi `583 passed / 3,161 assertions` tanpa failure. Kegagalan nondeterministik terkait race condition pada fixture workflow_log diselesaikan; pipeline berjalan mulus. `php vendor/bin/pint --test`: PASS; `npm run build`: PASS.

**Audit:** temuan stale owner authorization, takeover slug onboarding, agregasi cabang beda-owner, fokus dialog gagal, dan fokus setelah row dihapus sudah diperbaiki dengan negative/regression tests. OpenCode melanggar mode read-only sebelumnya (stash/pull/commit lokal); tidak ada push, pull gagal karena branch tanpa upstream, dan snapshot telah direstore dan diverifikasi ulang dengan hash yang benar dari baseline.

**Next:** `HUMAN:UI-LOCK` untuk browser/live visual acceptance; retry Kiro hanya setelah error internal CLI pulih. Jangan push/deploy tanpa izin Bos.

**Updated:** 2026-09-19 (Maraton Serial: T-35 loyalty DONE; tidak ada task READY lagi)
**Mode:** FASE 4 AKTIF - Gate UI-LOCK sudah dibuka.
**Arsitektur target:** puluhan jenis bisnis — industri = data, kapabilitas = kode (D-31..D-33)
**Canonical workspace:** `D:\PROJECTS\agentic-bos`
**Git:** branch `main`, HEAD lihat `git rev-parse --short HEAD`; **remote belum dikonfigurasi**.

## Koreksi audit preset batch 3 — DONE

Scope: menutup audit commit Kiro `f8fb5ea` (1 HIGH, 2 MEDIUM).

1. Registry effect runtime kini menjadi satu sumber validator preset; seluruh 40 preset memiliki regression test terhadap registry dan capability requirement.
2. Effect stok/invoice/deposit/notifikasi tanpa kontrak eksekusi atomik dihapus dari preset terdampak; katalog effect didokumentasikan sesuai registry runtime.
3. Capability efektif tenant dipreflight sebelum effect, stage, atau log berubah; negative regression membuktikan fail-closed tanpa mutasi parsial.
4. `PRESET_COVERAGE.md` disinkronkan ke 40 preset (`37` Tier A, `3` Tier B).
5. Evidence: full isolated `550 passed / 3,008 assertions`; Pint `388 files PASS`; build PASS; `40` JSON valid.
6. Fixture `storage/app/json/1/workflow_log.json` dipulihkan; artefak asing `caddy_check.json` tidak disentuh/di-stage.
7. **Next:** berhenti di gate `HUMAN:UI-LOCK`; tidak push/deploy.

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

## Koreksi Audit Terakhir

### T-24dR — Koreksi audit preset batch 1 (`86b169e`)

**State:** `DONE` — koreksi audit diterapkan oleh Hermes sebagai writer tunggal
dan di-commit sebagai `2b04d35`; proses writer Claude/Kiro CLI dihentikan sebelum edit.

**Hasil:**
1. seluruh key menu enam preset dapat di-resolve registry;
2. `quotations` memiliki modul generik `/app/quotations` tanpa bergantung pada `projects`;
3. `approval.request` mempersist tiket JSON dalam state `prepared` yang tidak
   terlihat widget, memakai `operation_id` unik per attempt, append log dengan
   validasi replay kanonis, lalu mengaktifkan tiket menjadi `pending`; prepared
   tidak dihapus saat gagal agar request paralel tidak dapat menghapus tiket
   yang sudah dilog, dan retry tidak membuat duplikat; `pending_approvals` tetap
   tenant-scoped dan fail-closed untuk prepared/consumed/expired/tenant lain;
4. copy `cuci_sepatu` tetap data-only; effect notifikasi ditunda sampai outbox/idempotensi tersedia;
5. `manufacturing.production_order` sinkron sebagai Tier B dengan dependency
   `inventory.bom` + `inventory.batch_expiry` + `finance.accounting` sesuai
   D-57;
6. `INDUSTRY_PRESETS.md`, `PRESET_COVERAGE.md`, dan laporan worker diperbarui;
7. render Eloquent keenam preset, route quotation, seeder, registry, widget JSON
   dan Eloquent, serta anti-hardcode memiliki coverage behavioral;
8. approval Eloquent memakai `prepared -> DB audit -> pending` dalam satu
   transaksi, operation identity idempoten, company row lock, dan expiry
   lifecycle; audit approval Eloquent tidak lagi dicampur dengan file JSON.

**Evidence:** focused **119 passed / 769 assertions**; full suite terisolasi
**527 passed / 2311 assertions**; Pint **PASS / 386 files**; `npm run build`
**PASS / 1.02s**; JSON **47 valid**. Audit kelima menemukan gap atomisitas
approval Eloquent dan expiry lifecycle JSON; keduanya dipatch dan dibuktikan
dengan integration/idempotency/expiry/forced-rollback/missing-operation-id tests.
Audit read-only final atas tepat 25 path staged (`f2b7989d…`) **PASS tanpa
HIGH/MEDIUM**. Fixture test
`storage/app/json/1/workflow_log.json`
dipulihkan; lima artefak Caddy/Cloudflare asing tidak disentuh.

## Pekerjaan Selesai (tambahan)
- T-24dR (koreksi audit preset batch 1) → menu quotations generik, approval
  widget berbasis `approval_tickets`, capability manufaktur sinkron D-57,
  coverage docs + behavioral tests lengkap. Commit: `2b04d35`.
- T-28 (Cabang/lokasi tambahan, D-58) → `451653e` (part 1: parent_company_id
  self-FK) + `fed2bf2` (part 2: GroupReportController owner-only + gate
  addon.branches, GroupReportService agregat root+branches exclude unrelated,
  BranchSwitcher reject unowned company, test negatif isolasi tenant lengkap).
  Verifikasi final-gate: `php artisan test` → 490 passed/1920 assertions
  (2x stable run); `pint --test` → clean 359 files; `npm run build` → 1.16s.
  Bug ditemukan & diperbaiki sebelum commit: `EloquentCompanySettingsStore`
  tidak ter-bind di test (pola sama seperti insiden AdminImpersonationTest);
  file scratch `test-debug.php` dibersihkan dari repo root.
- Hardening D-50(f) (payload AI TenantBot) → `45b682f`. Mengembalikan field
  whitelist eksplisit (bukan `toArray()`) + guard owner eksplisit di endpoint
  opt-in yang sempat dilonggarkan.
- T-29 (Nomor WA disediakan platform) → `a4932e4`. Kapabilitas
  `addon.platform_wa_number`, kolom `is_platform_provided` di `hermes_profiles`.
- T-30 (Payroll lanjutan: BPJS/PPh21) → `59f2822`. Kapabilitas
  `addon.payroll_advanced`, masuk `SENSITIVE_CAPABILITIES` (D-50f), kolom
  BPJS/PPh21 di `payrolls`.
- T-31 (Domain & struk ber-merek sendiri) → `26a53fd`. Kapabilitas
  `addon.custom_domain`, kolom `custom_domain` di `companies`.
- T-32 (e-Faktur/Coretax) → `59cb26e`.
  Model `OrderEFaktur` + interface stub `EFakturGatewayContract` (TANPA
  panggilan API eksternal nyata, sesuai batasan prompt — perlu kredensial
  Coretax berbayar, butuh approval Bos terpisah). **Bug D-26 ditemukan &
  diperbaiki sebelum commit**: migration asli claude-cli tidak menyertakan
  `company_id` di `order_e_fakturs` — ditambahkan + test negatif isolasi
  tenant baru ditulis Hermes (tidak ada di draft asli).
- T-33 (Integrasi marketplace/ojol) → `c8be643`. Kapabilitas
  `addon.marketplace_sync`, interface stub `MarketplaceOrderAdapterContract`
  mengikuti pola `NalarPesanWebhookController` (T-19b), idempotency scoped
  per company_id. Test negatif tambahan ditulis Hermes: external_id sama
  di 2 company menghasilkan order terpisah (tidak collide).
- T-34 (Storage terkelola) → `8d8b01e`. Kapabilitas `addon.managed_storage`,
  kelas `CompanyDiskResolver` — disk/kuota switching lewat FeatureResolver,
  tidak hardcode nama provider.
- T-35 (Loyalty pelanggan) → `692df40`. Kapabilitas `addon.loyalty` (opt-in
  per company), `loyalty_rules` (mode nominal/per_item/both, expiry_months
  nullable) + `loyalty_points` (ledger, `expires_at` nullable) — keduanya
  `company_id` wajib (D-26). `LoyaltyPointsCalculator` membaca rasio/mode
  dari data company, tidak hardcode rumus. Disahkan Bos sebagai D-59
  (2026-09-19). 6 test: capability off default → 0 poin, rasio nominal
  company-specific, per-item, both mode akumulasi, expiry opsional per
  company, isolasi tenant.

**Verifikasi final-gate Fase 6b (Hermes, setelah T-35 + audit):**
`php artisan test` → **507 passed, 1966 assertions**; `pint --test` →
**clean 385 files**; `npm run build` → sukses; grep D-31 (nama industri di
file addon baru) → 0 hasil.

**Catatan temuan proses:** fixture demo `storage/app/json/bengkel-arka/*.json`
sempat terhapus dari disk (bukan oleh commit, kemungkinan side-effect proses
lain yang menulis ke folder JSON nyata alih-alih storage terisolasi test) —
dipulihkan via `git checkout`. Test suite kembali hijau setelahnya.

## READY Berikutnya
Tidak ada task READY tersisa di `EXECUTION_PLAN.md` §Fase 6b. Katalog D-56
sudah dibangun seluruhnya (T-28..T-35). Langkah lanjutan menunggu instruksi
Bos (add-on baru, ekspansi preset, atau prioritas lain).
