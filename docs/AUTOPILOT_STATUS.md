# Agentic BOS Autopilot Status

## Fase 8 — Tagihan Pelanggan & Profitabilitas Proyek (D-62)

**D-62 dicatat LOCKED** (commit `2ba0ee3`) beserta antrean Fase 8 (T-41..T-47) di `EXECUTION_PLAN.md`.

**T-41 jalur input Buku Kas + pembebanan ke proyek: `DONE`, commit `2cc6c96`.**

- **Cacat yang ditutup:** `LedgerScreen` hanya punya `render()` — tidak ada aksi tulis sama sekali, jadi tenant tidak punya cara mencatat penerimaan atau pengeluaran dari UI. Tanpa ini laba-rugi proyek (T-46) mustahil punya isi.
- **Submenu baru** `accounting/entries` ("Entri Kas", screen `list`, entity `cash_entries`, `requires_all: ['finance.cashbook']`). Tidak ada kelas layar baru — `ListScreen` generik yang dipakai.
- **Temuan saat kerja:** `SchemaPresenter::columns()` membuang **semua** foreign key, jadi `project_id` tidak pernah muncul di form. Itu benar untuk kolom sistem (`journal_id`, `created_by_user_id`) tapi salah untuk pembebanan yang memang dipilih manusia. Perbaikan tidak memakai daftar field per entitas: referensi kini bisa ditandai di schema (`references.{field}.assignable = true`), dan `SchemaPresenter::relations()` mengangkatnya jadi field form bertipe `relation`. Penanda divalidasi `EntitySchema` (harus bool; `term`/`label` harus string). `cash_entries.project_id` dan `contact_id` ditandai; kolom sistem tetap tersembunyi. Entitas baru mendapat perilaku ini tanpa menyentuh presenter (D-31/D-42).
- **Label relasi lewat kamus istilah:** schema menyebut kunci `term`, layar meneruskannya ke `term()`. Pada preset bengkel labelnya jadi "Pekerjaan" dan "Pelanggan", bukan "Project Id".
- **Fail-closed:** pilihan relasi hanya dimuat dari repository company aktif, dan id yang dikirim klien diverifikasi ada di company itu sebelum disimpan — tanpa itu id company lain bisa tersimpan sebagai relasi menggantung yang tidak tampil di layar mana pun.
- **Test** `tests/Feature/CashEntryInputTest.php` 9 test: path modul terdaftar + muncul di navigasi, hanya referensi `assignable` jadi field, label mengikuti terminologi, pengeluaran terbebani proyek, entri tanpa proyek sah, opsi relasi hanya company aktif, id proyek luar company ditolak dan tidak ada yang tersimpan, `direction` di luar enum ditolak, isolasi tenant.
- **Gate:** `DATA_SOURCE=json php artisan test` **938 passed / 4.629 assertions**; `vendor/bin/pint` PASS 5 file task ini; `npm run build` PASS.
- **Catatan:** `vendor/bin/pint --test` melaporkan pelanggaran pre-existing di `tests/Feature/LobbyNavigationTest.php` (`class_attributes_separation`) — file writer lain yang sedang dimodifikasi, dicatat dan tidak disentuh (§0.2).
**T-42 entitas tagihan pelanggan (lapisan data): `DONE`, commit `e663b2c` di worktree `task/T-42`, merged `20464ed`.**

- **Dikerjakan di worktree terpisah** `D:\PROJECTS\agentic-bos-t42` (branch `task/T-42`) karena menyentuh migration dan writer lain masih aktif di `main` (HERMES Parallel Writer Policy butir 3 + 6).
- **Tabel baru** `customer_invoices` + `customer_invoice_lines`, keduanya `company_id` wajib (D-26), migration portabel Schema Builder (B-01), baris `cascadeOnDelete` dari tagihan. Bentuknya meniru `quotations`/`quotation_lines` supaya penomoran dan triplet pajak konsisten. `paid_amount` disiapkan untuk T-44. Relasi `contact_id`/`project_id`/`quotation_id` semuanya nullable — tagihan boleh berdiri sendiri (D-62).
- **Schema JSON** untuk kedua entitas, didaftarkan di `SchemaValidatorTest::entities()`. Referensi `contact_id`/`project_id`/`item_id` ditandai `assignable` (mekanisme dari T-41) sehingga layar T-43 mendapat field relasinya tanpa kode tambahan.
- **Model** `CustomerInvoice` + `CustomerInvoiceLine` dengan cast decimal:2 / decimal:4. Docblock menegaskan ini bukan `Invoice` (tagihan langganan platform, D-23).
- **Test** `tests/Feature/CustomerInvoiceDataLayerTest.php` 15 test, negatif lebih dulu: pemisahan tabel dari `invoices` beserta larangan kolom khas billing platform, isolasi tenant di kedua tabel, nomor duplikat dalam satu company ditolak, nomor sama antar company diterima, baris ikut terhapus bersama tagihan, enam varian nilai/field ditolak validator (`grand_total`/`paid_amount`/`subtotal` negatif, `status` di luar enum, `number` dan `issue_date` hilang), kapasitas kolom pada batas dan di atas batas, serta tagihan berdiri sendiri vs menempel proyek.
- **Temuan presisi (SQLite):** kolom decimal di SQLite disimpan sebagai REAL, jadi di atas ~13 digit signifikan sen hilang ke mantissa float — terukur `1234567890123.45` kembali sebagai `1234567890123.40`, dan batas kolom `9999999999999999.99` kembali sebagai `10000000000000000.00`. Itu batas SQLite dev/test, bukan batas schema; penjagaan kapasitas kolom diuji di tingkat validator, paritas MySQL tetap urusan T-21b. Dicatat sebagai komentar di test agar tidak ditafsirkan sebagai bug uang.
- **Catatan setup worktree:** `vendor` sebagai junction ke repo utama **tidak bisa dipakai** — autoloader Composer memetakan `App\` dan `Tests\` ke direktori proyek induk, sehingga `database_path()` menunjuk ke luar worktree dan schema baru "tidak ditemukan". Solusinya `composer install` sendiri di worktree. Selain itu `public/build` wajib disalin: tanpa manifest Vite, 61 test view gagal 500.
- **Gate:** worktree `DATA_SOURCE=json php artisan test` **955 passed**; main pasca-merge **957 passed / 4.677 assertions**; `migrate:fresh --seed` OK di worktree dan di main; `vendor/bin/pint --test` PASS 479 file di worktree. Di main Pint masih menyisakan pelanggaran pre-existing `tests/Feature/LobbyNavigationTest.php` milik writer lain (dicatat, tidak disentuh).
**T-43..T-47: `DONE`. Fase 8 selesai penuh — laporan lengkap `docs/worker-reports/FASE_8_CUSTOMER_INVOICING.md`.**

- **T-43 layar tagihan:** `ContractScreen` + view. Item `accounting/invoices` di-repoint ke `customer_invoices` dan navigasinya dinyalakan (label tetap `term('invoices')`). Nilai selalu dihitung ulang dari baris lewat `TaxRateService` dengan profil `BusinessIdentityStore`; total dari klien tidak dipercaya. Penomoran `INV-YYYYMM-NNNN` per company. Draf bisa diubah, tagihan terbit terkunci.
- **T-44 pencatatan pembayaran:** menulis `cash_entries` (`in`, `project_id`, `source_type = 'customer_invoice'`, `source_id`). `paid_amount` **dihitung ulang** dari entri kas yang menunjuk tagihan itu, bukan ditambahkan — angka lunas tidak bisa menyimpang dari uang yang tercatat. Overpayment ditolak, draf tidak bisa dibayar, lunas tidak bisa dibayar lagi, klik kedua tidak menggandakan.
- **T-45 termin:** `project_milestones.invoice_id` → `customer_invoice_id` menunjuk `customer_invoices`. Kolomnya ternyata **tidak punya FK** ("No FK yet, invoices table is T-12"), jadi migration cukup `renameColumn` tanpa rebuild tabel SQLite. `projects/billing` jadi `list` atas `project_milestones`. Layar tagihan dapat pilihan "Tagih dari termin"; termin yang sudah tertaut hilang dari pilihan dan ditolak bila dipaksa. **`InvoiceCreatePartial` TIDAK dihapus** — rencana awal menyebutnya kelas mati, ternyata dipakai `Project::checkAndTriggerMilestones()` dan diuji `ProjectWorkflowTest`; hanya komentarnya yang dibereskan.
- **T-46 laba-rugi basis kas:** `MarginScreen` + view, item `projects/margin` (`requires_all: projects + finance.cashbook`). Hanya membaca `cash_entries`: pendapatan `in`, biaya `out`, laba selisih. Layar menyebut "basis kas" eksplisit plus peringatan bahwa tagihan belum dibayar tidak dihitung. Entri tanpa proyek dilaporkan terpisah sebagai belum terbebani, tidak dibuang diam-diam.
- **T-47:** fixture demo `project_milestones.json` tiga company ikut di-rename mengikuti schema baru — tertangkap `JsonDataSourceTest`, bukan review manual. `PRESET_COVERAGE.md` dapat ringkasan Fase 8.
- **Cakupan hasil:** Entri Kas untuk setiap preset ber-`finance.cashbook`; Tagihan di **29 preset**; Termin & Opname di **3 preset**; Laba-Rugi di **10 preset**. Delapan preset dapat rangkaian penuh: agency, bengkel, contractor, desain_interior, fotografi, it_support, kantor_hukum, mebel_custom.
- **Gate:** `DATA_SOURCE=json php artisan test` **993 passed / 4.809 assertions**; `migrate:fresh --seed` OK; Pint PASS pada file Fase 8; `npm run build` PASS. Pint masih menyisakan pelanggaran pre-existing `tests/Feature/LobbyNavigationTest.php` milik writer lain (dicatat, tidak disentuh).
- **Sisa risiko:** rangkaian ini belum pernah dijalankan dari HP pada tenant produksi; belum ada cetak/kirim tagihan ke pelanggan.

## Fase 9 — Pengerasan Sisi Tenant (SELESAI, T-48..T-58)

Audit sisi tenant pasca-Fase 8 (2026-09-22) menghasilkan delapan temuan, semuanya
berbukti di kode. Sudah masuk antrean `EXECUTION_PLAN.md` §Fase 9 sebagai
T-48..T-56. Ringkas:

- **`READY` sekarang:** T-48 (jatuh tempo & umur piutang — `due_date` disimpan tapi tidak pernah dirender, ujung longgar T-43; tidak ada konsep terlambat untuk piutang tenant), T-50 (otorisasi peran di layar uang — tidak ada pemeriksaan peran di layar mana pun kecuali `PipelineScreen::actorRole()`), T-53 (penawaran → tagihan; `quotation_lines` punya migration tanpa schema JSON), T-55 (ekspor hanya 4 file, data uang Fase 8 tidak ikut), T-56 (BI-B2 rebase panel Kesehatan Usaha).
- **`BLOCKED` keputusan Bos:** T-49 (pengingat piutang otomatis — kanal + frekuensi + Hermes node), T-51 (tab Tim & Akses — model peran, kanal undangan, pengaruh ke kuota paket), T-54 (`finance.accounting`/`hr.payroll` aktif di 0 preset dan belum punya schema/layar yang benar — apakah memang dijual).
- **`BLOCKED` dependency:** T-52 (cetak/unduh dokumen tagihan, menunggu T-48).
- **Urutan yang disarankan:** T-48 lebih dulu (paling murah, sekaligus membereskan janji SOP bot soal pengingat piutang), lalu T-50 + T-51 sebagai satu paket — memisahkannya membuka tagihan dan pembayaran untuk semua staf.
- **Janji yang belum ditepati:** SOP bawaan Karyawan AI (`AssistantSettings.php:159`) menjanjikan pengingat piutang H-1 yang belum ada implementasinya. T-48 mewajibkan teks itu disesuaikan sampai T-49 mendarat.

**T-48, T-50, T-52, T-53, T-55: `DONE`.** Lima task `READY` Fase 9 diselesaikan serial di `main`.

- **T-48 jatuh tempo & umur piutang:** kolom jatuh tempo dirender (sebelumnya disimpan dan dikirim ke view tapi tidak pernah tampil), tagihan lewat jatuh tempo ditandai beserta umurnya, daftar diurutkan tunggakan terlama di atas (sort PHP 8 stabil sehingga urutan tanggal terbit tetap terjaga untuk yang tidak menunggak), dan header memuat ringkasan jumlah + nilai tagihan yang telat. Umur tunggakan nol untuk tiga keadaan berbeda: belum jatuh tempo, tanpa tanggal, atau sudah lunas; draf tidak pernah menunggak. **Teks SOP bawaan Karyawan AI dikoreksi** — tidak lagi menjanjikan pengingat otomatis yang belum ada, dan ada test yang menjaga janji itu tidak kembali. 7 test baru (waktu dibekukan `travelTo` supaya umur tidak bergantung jam mesin).
- **T-50 otorisasi peran:** `issue()` dan `recordPayment()` di layar tagihan kini owner-only, `delete()` di layar daftar juga. Diperiksa **server-side** dari sumber tepercaya (`CompanyRoleResolver::isOwnerOfCompany`) dengan jalur session hanya untuk fixture JSON tanpa DB, mengikuti pola `Settings`. Menyembunyikan tombol tidak dianggap pengamanan. 3 test negatif: staf tidak bisa menerbitkan, staf tidak bisa mencatat pembayaran, dan peran diperiksa saat aksi dijalankan bukan saat komponen dipasang.
- **T-52 dokumen cetak:** route `/app/invoices/{invoice}/print` + view cetak (identitas usaha, penerima, rincian, pajak, sudah dibayar, sisa) dengan tombol cetak/simpan PDF. Isolasi tenant bukan dari pemeriksaan tambahan melainkan dari repository yang ter-scope company — id usaha lain sederhananya 404. Draf ditolak 403. 4 test.
- **T-53 penawaran → tagihan:** `quotation_lines.schema.json` ditambahkan (tabelnya sudah ada sejak `2026_09_18_141709` tapi tanpa schema, sehingga barisnya tak terlihat layar generik). Layar tagihan dapat pilihan "Tagih dari penawaran" yang menyalin seluruh baris penawaran; nilai diambil dari basis data, penawaran yang sudah ditagih hilang dari pilihan, penawaran kosong dan penawaran company lain ditolak. 4 test.
- **T-55 ekspor lengkap:** `BuildCompanyExport` tidak lagi memakai daftar tabel hardcoded — entitas diturunkan dari katalog `database/schemas/*.schema.json`, disaring oleh keberadaan model dan kolom `company_id`. Entitas baru ikut terekspor tanpa menyunting job. Ditambah `manifest.json` (jumlah baris per entitas) dan `memberships.json`. 4 test, termasuk negatif: tidak ada satu baris pun dari company lain, dan permintaan dari non-owner tidak menghasilkan arsip. Catatan: gap `downloadUrl` dari daftar lama ternyata **sudah tertutup** — view `data-export.blade.php` merendernya.
- **Gate (T-48/50/52/53/55):** Pint PASS 12 file task ini; `npm run build` PASS.

**T-56 BI-B2 panel Kesehatan Usaha: `DONE`, commit `3524598`.** Dikerjakan setelah WIP writer lain di-commit (`b0ef5b0`) sehingga tree bersih dan `Dashboard.php`/`dashboard.blade.php` tidak lagi kotor.

- **Rebase, bukan merge:** branch `task/bi-b` @ `49bcaed` dibangun pra-MQ-01 (mount `mount(DashboardComposer)`), sedangkan `main` sudah `mount(DashboardComposer, ...)` dengan `loadError`/`themeError`. Perubahannya diterapkan ulang di atas kontrak MQ-01 sekarang, bukan di-merge.
- **Komponen:** `Dashboard::mount()` dan `reload()` kini juga menerima `CompanyRoleResolver` + `BusinessHealthAnalyzer` (di-resolve container). Properti `$health` null untuk non-owner (tidak bocor ke snapshot Livewire) dan tetap null bila analyzer melempar — dashboard yang sudah dimuat tidak ikut jatuh (fail-closed dua arah, D-50).
- **View:** markup panel dipindah ke partial `resources/views/livewire/dashboard/health-panel.blade.php` dan disertakan lewat `@include` di bawah `@if ($health !== null)`. Memisahnya ke partial mengurangi risiko rebase berikutnya bertabrakan lagi di satu berkas besar. String statis, tanpa `term()` maupun kata industri (D-31).
- **Test:** `tests/Feature/DashboardHealthPanelTest.php` (dari `49bcaed`, diadaptasi) 5 test: owner lihat angka nyata, insufficient-data empty state, non-owner tidak dapat panel maupun data finansial di snapshot, analyzer gagal = fail-closed dashboard tetap hidup, reload memuat ulang health.
- **Gate:** `DATA_SOURCE=json php artisan test` **1.022 passed / 4.959 assertions**; Pint PASS file task ini; `npm run build` PASS.
- **Sisa Fase 9:** hanya T-49, T-51, T-54 yang menunggu keputusan Bos (kanal pengingat; model peran + kanal undangan; apakah akuntansi penuh & payroll dijual). Seluruh task `READY` Fase 9 sudah selesai.

**Catatan:** WIP writer lain (polesan UX mobile, landing publik, quick actions dashboard, pin `DATA_SOURCE=json` di phpunit.xml) di-commit apa adanya di `b0ef5b0` atas instruksi Bos supaya tidak hilang dan supaya T-56 tidak terhalang file kotor — belum direview, bukan tulisan sesi ini.

### Keputusan yang membuka empat task terakhir

Bos memutuskan: pengingat piutang lewat **WhatsApp via Hermes** (D-63), akuntansi
penuh dan payroll **dijual** (D-64), keanggotaan tim **dua peran di pivot
`company_user`** (D-65), dan japri staf ke bot internal **diizinkan baca-saja**
(D-66). Keempatnya tercatat LOCKED di `00-DECISIONS.md` (`d425c38`, `0a5bb00`).
D-65 menolak peran per-modul dengan alasan konkret: `PresetDefinitionValidator`
(baris 209) mengunci kosakata `['owner','staff','system']` dan 40 preset
memakainya, jadi memperluasnya sekelas D-32 — izin halus nanti lewat kolom izin
per anggota, bukan peran baru.

**T-57 japri owner ke bot internal: `DONE`, commit `5967ca1`.** Cacat produksi,
bukan peningkatan.

- Filter membandingkan pengirim dengan `$profile->owner?->phone`, tapi `User`
  **tidak punya kolom `phone`** — yang ada `wa_number` + `wa_is_verified`
  (migration `2026_09_17_222052`). `$cleanOwner` selalu kosong, cabang
  fail-closed selalu menyala, jadi **owner tidak pernah bisa japri bot-nya
  sendiri**.
- Test lama `primary profile allows dm from owner` hijau karena menulis
  `$owner->phone` pada model belum tersimpan: Eloquent menerima atribut sembarang
  di memori, jadi test itu membuktikan kolom yang tidak ada di skema. Pelajaran
  yang dicatat: test yang menulis atribut ke model tak tersimpan tidak
  membuktikan skema.
- Test negatif baru mengunci ketiga arah: nomor hanya sah dari `wa_number`
  (sengaja diisi ke `phone` saja supaya regresi langsung merah), nomor cocok tapi
  `wa_is_verified` salah tetap ditolak, dan owner tanpa nomor tidak pernah
  "cocok" dengan pengirim tanpa nomor (dua string kosong).

**T-51 tab Tim & Akses: `DONE`, commit `b6bce50`.** Tab `team` sebelumnya stub
berbadge "Segera", jadi usaha ber-staf menjalankan semuanya dari satu akun owner.

- Pivot `company_user` (`owner|staff`), `company_invitations` (kode sekali pakai,
  kedaluwarsa 72 jam, dapat dicabut), dan `max_users` pada `plans` +
  `company_memberships`. `CompanyRoleResolver` membaca pivot, bukan hanya
  `owner_user_id`.
- Kuota ditegakkan `UserQuotaGate` **sebelum** undangan dibuat, bukan saat
  penerimaan — kalau tidak, kuota bisa terlampaui oleh undangan yang sudah
  beredar. Tier gratis 1, Starter 3, Pro 10, Enterprise 100.
- Kosakata peran tidak diperluas: **nol sentuhan ke 40 preset**.
- Temuan saat kerja: `max_users` `NOT NULL` tanpa default membuat tiga jalur
  provisioning gagal (`TrialProvisioner`, `InvoiceCreationService`,
  `InvoiceConfirmationService`) — ditutup dengan `$plan->max_users ?? 1`.
- Test negatif: undangan tidak bisa menambah staf ke company lain, staf tidak
  bisa menaikkan perannya sendiri, pencabutan berlaku seketika, kuota penuh
  menolak undangan (fail-closed), kode kedaluwarsa/terpakai ditolak.

**T-54 akuntansi penuh & payroll: `DONE`, commit `ed84b7f` + `5849035`
(didelegasikan ke sub-agent).** Urutan D-64 diikuti: schema dulu, layar, lalu
repoint menu — penyalaan di preset ikut gerbang paket D-52 dan **di luar lingkup
task ini**.

- `chart_of_accounts.schema.json`, `accounting_journals.schema.json`, dan
  `payrolls.schema.json` dibuat; `ReportScreen` dibangun supaya pola `report`
  tidak jatuh ke kartu kontrak; item menu `accounting/coa`,
  `accounting/journals`, `hrd/payroll` di-repoint ke entitas yang benar
  (sebelumnya "Bagan Akun" menunjuk `cash_entries` dan "Payroll" menunjuk
  `employees` — layar menampilkan entity berbeda dari judulnya).
- **Koreksi terhadap rencana:** pola layar `accounting/journals` diganti
  `ledger` → `list`. `LedgerScreen::amountField()` akan fallback ke `'id'` untuk
  entitas ini dan **menjumlahkan id baris sebagai "saldo"** — angka finansial
  palsu, bukan sekadar tampilan salah.
- `ModuleSidebarTest` disunting: asersi `/app/accounting/reports` yang dulu jatuh
  ke kartu kontrak diganti, dan jaminan fallback dipindah ke test baru
  `test_screen_pattern_without_a_component_falls_back_to_the_contract_card`
  supaya jaminannya tidak hilang bersama asersi lama.
- **Empat temuan di luar lingkup, sengaja TIDAK diperbaiki** (perlu task
  sendiri):
  1. `unique` di schema `chart_of_accounts.account_code` dan
     `accounting_journals.journal_number` **belum punya unique index di
     migration** — jalur Eloquent/MySQL masih menerima duplikat. Ini gap data
     finansial, bukan kosmetik.
  2. `accounting_journal_lines` belum punya schema JSON, jadi debit/kredit tidak
     terlihat layar generik.
  3. Belum ada fixture demo untuk tiga entitas baru — layar Bagan Akun, Jurnal,
     dan Payroll kosong di tenant demo.
  4. Penyalaan kapabilitas di preset belum dilakukan (memang milik D-52).

**T-49 pengingat piutang otomatis: `DONE`, commit `7986662`.** Menutup janji SOP
bawaan Karyawan AI yang sejak T-48 harus ditulis ulang karena belum ada
implementasinya.

- **Penerima adalah pemilik usaha, bukan pelanggan.** Mengirim langsung ke
  pelanggan menyentuh persetujuan pihak ketiga dan reputasi nomor WA tenant, jadi
  itu keputusan terpisah — bukan efek samping sebuah task pengingat.
- `config/receivables.php` mengatur tahap (H-3, H, H+3, H+7) dan batas harian.
  `customer_invoice_reminders` menyimpan satu baris per (tagihan, tahap) sebagai
  kunci idempoten, jadi scheduler yang jalan dua kali tidak bisa mengirim dua
  kali. Command `bos:remind-receivables` dijadwalkan `dailyAt 07:30`.
- Pengiriman lewat `HermesNodeClient::sendWhatsAppMessage()` yang company-scoped
  dan fail-closed (D-63) — bukan `sendWhatsApp()` yang tidak ter-scope, dan bukan
  `DunningLadder`/`BillingCheckExpiring` yang melayani tagihan langganan platform
  (D-23).
- 12 test, negatif lebih dulu: replay tidak mengirim dua kali, nomor belum
  terverifikasi fail-closed, tagihan lunas/draf tidak pernah diingatkan, isolasi
  tenant.
- **Aktivasi produksi tetap `HUMAN:SECRET`** — kredensial Hermes belum dipasang,
  jadi jalur ini belum pernah mengirim dari tenant nyata.

**T-58 identitas WA per orang: `DONE`, commit `2ba77c7`.** Sebelumnya bot tidak
punya identitas per orang: japri hanya dibandingkan dengan nomor owner, dan di
grup bot tidak tahu siapa yang bicara sehingga peran tak pernah bisa diterapkan.

- `WhatsAppSenderIdentity` menautkan nomor terverifikasi ke keanggotaan
  `company_user` dengan tiga penjaga: `wa_is_verified` wajib benar (nomor WA
  berpindah tangan, jadi kecocokan bukan bukti identitas), keanggotaan wajib
  aktif (pencabutan menutup akses WA seketika tanpa menyentuh apa pun di sisi
  WA), dan satu nomor di lebih dari satu company **fail-closed** sampai T-37
  menghadirkan pemilih konteks — menebak company berarti berisiko menjawab
  dengan data usaha yang salah.
- Japri staf **baca-saja** (D-66). Tidak ada tabel izin terpisah untuk WA: aksi
  bot melewati pintu otorisasi yang sama dengan web, sehingga WA tidak menjadi
  jalan memutar aturan owner-only T-50. Aturan grup WA-04 tidak dilonggarkan.
- **Cacat produksi kedua yang ikut tertutup:** filter memanggil
  `$company->moduleSettings()`, sementara relasinya bernama `settings()` —
  `BadMethodCallException` setiap ada pesan grup dengan konteks company. Tidak
  pernah terlihat karena test lama **selalu** mengirim `$company = null`. Pola
  yang sama dengan cacat T-57: test lolos karena memakai nilai fabrikasi.
- `WhatsAppInteractionFilterTest` dipindah dari `tests/Unit/` ke
  `tests/Feature/WhatsApp/` dengan `RefreshDatabase`, karena jalur "bukan owner"
  sekarang menyentuh basis data.

**Gate penutup Fase 9:** `DATA_SOURCE=json php artisan test` **1.082 passed /
5.128 assertions, 0 gagal**; `migrate:fresh --seed --force` OK;
`vendor/bin/pint` PASS pada file T-49 + T-58; `npm run build` PASS. Pint masih
menyisakan satu pelanggaran pre-existing `tests/Feature/LobbyNavigationTest.php`
(`class_attributes_separation`) milik writer lain — dicatat, tidak disentuh.

**Sisa risiko Fase 9:**

- Seluruh rangkaian belum pernah dijalankan dari HP pada tenant produksi.
- Aktivasi Hermes produksi untuk T-49, T-51 (undangan WA), dan T-58 menunggu
  `HUMAN:SECRET`; sampai itu ada, ketiganya hanya terbukti lewat
  `FakeHermesNodeClient`.
- Empat temuan T-54 di atas masih terbuka, yang paling berisiko adalah unique
  index yang belum ada untuk `account_code` dan `journal_number`.
- `b0ef5b0` (WIP writer lain) belum direview.

## UR  Product Usage Readiness (docs/plans/product-usage-readiness-plan.md)

**UR-00  convergence & final QA baseline: `DONE`, commit `f942299` (laporan `docs/worker-reports/UR-00.md`).**
- Foreign-writer audit fresh: tidak ada writer asing aktif; main stabil `9e0364b` (saat itu).
- Verifikasi fresh: 856 passed/4.376 assertions, Pint PASS, `npm run build` PASS, smoke browser 0 temuan, migrate dev OK.
- QA independen MQ-01 (OpenCode read-only) verdict **LAYAK**: delta MQ-01C6 + checklist UR-00 semua PASS; 4 temuan LOW/INFO non-blocking dicatat (audit-stamp expiry, test null-expires_at, trim-parity displayName, current_company_id residu).
- Lane BI-A (`9d032af`) ternyata sudah merged lama di main = bagian baseline, tidak ada aksi. Lane BI-B (`49bcaed`, panel Kesehatan Usaha) verdict **PARKIR**: konflik merge nyata dengan MQ-01 di `app/Livewire/Dashboard.php` (base pra-MQ-01); kualitas intrinsik oke; perlu task rebase + adaptasi kontrak MQ-01 (BI-B2), bukan launch blocker.

**UR-01  bootstrap identitas produksi: pra-approval scope `DONE` di main (merge `4a9db57` via worktree `task/ur01-provisioning`).**
- Audit: `RequireSuperAdmin` fail-closed 403; `User` `#[Hidden(password, remember_token)]` + cast hashed; negative test non-admin 403 `/admin` sudah ada (`AdminImpersonationTest`).
- Command baru `bos:provision` (idempoten by email/slug, admin + owner + company pilot; password via `--password` atau prompt `secret()` tersembunyi; slug conflict owner lain = FAILURE; output hanya tabel non-secret email+id). 4 test: idempotensi, hash bukan plaintext, tidak ada password di log channel, slug-conflict ditolak.
- Gate: worktree & main post-merge **860 passed / 4.392 assertions**, Pint PASS.
- **Sisa UR-01 (menunggu approval Bos `HUMAN:DEPLOY` + `HUMAN:SECRET`):** jalankan `bos:provision` di produksi dengan email/data pilot asli dari Bos (via prompt interaktif, password tidak masuk shell history). Tidak pakai `DogfoodTenantSeeder`.
**UR-01  bootstrap identitas produksi: `DONE` (gate `HUMAN:DEPLOY`+`HUMAN:SECRET` dibuka Bos 2026-09-21).**
- `bos:provision` dijalankan: admin Bos = user existing `bos@nalar.army` (id 2, flag `is_platform_admin` diaktifkan, tidak dibuat ulang); owner pilot baru `pilot@nalar.army` (id 18) + company `usaha-pilot` (id 14, preset `custom`). Password pilot digenerate acak via PHP (`random_bytes`), diinput via prompt tersembunyi, TIDAK pernah masuk shell history/argumen/log; file `cache/pilot-credentials.txt` (gitignored, dihapus setelah diserahkan ke Bos via Telegram).
- Verifikasi: users=7, admins=1, pilot_companies=1; login browser nyata sukses (Playwright): `/login` -> `/app/dashboard`, dashboard render 'USAHA PILOT', KPI 0 (tenant baru), 0 error.
- Temuan infra (dicatat untuk UR-02): server 8010 http-tanpa-proxy membuat asset `https://127.0.0.1:8010` gagal dimuat bila diakses langsung via http (APP_URL https); akses publik normal via proxy https (401 Basic Auth = sesuai snapshot). Login diverifikasi pada server verifikasi terpisah port 8005 dengan env APP_URL konsisten.
- Verifikasi agregat non-secret di `docs/worker-reports/UR-01.md`.

**UR-02  web + queue + scheduler: local implementation `DONE` (commit `10bc76c`); aktivasi produksi menunggu `HUMAN:DEPLOY`.**
- Topologi runtime terkelola siap: `ecosystem.production.config.cjs` diperluas jadi 3 proses PM2 (web 8010 + queue worker `--tries=3 --backoff=30 --max-time=3600` + scheduler `schedule:work`), semua path absolut, `APP_ENV=production`, log terpisah per proses di `storage/logs/pm2-{production,queue,scheduler}-{out,error}.log`, autorestart + restart delay + NSSM service `PM2-AgenticBOS` StartMode Auto (reboot resilience).
- Bukti lokal nyata: job uji diproses **tepat sekali** (`InfrastructureProbeJob` dispatch -> `queue:work --stop-when-empty` -> marker file 1 baris, 12,79s tanpa duplikasi); scheduler `schedule:work` 70 detik = tick per menit aktif; `failed_jobs=0` setelah flush (1 artefak tinker lama dibersihkan); `schedule:list` = `billing:check-expiring 0 0 * * *`.
- Runbook: `docs/RUNBOOK_RUNTIME_SERVICE.md` (restart per proses, health check, probe tepat-sekali, prosedur aktivasi).
- Test: `InfrastructureProbeJobTest` 2 test (dispatch tepat 1 + handle menulis 1 baris). Gate: full **862 passed / 4.397 assertions**, Pint PASS, build PASS (tak ada perubahan Blade/CSS/JS).
- **Sisa UR-02 (butuh `HUMAN:DEPLOY`):** restart service `PM2-AgenticBOS` agar daemon PM2 memuat 3 app baru, lalu health check + probe di runbook.
- Temuan infra dari UR-01 tetap berlaku: akses produksi harus via proxy https `agentic-bos.nalar.army` (Basic Auth 401 aktif); akses http langsung 8010 membuat asset gagal termuat.

**UR-02  web + queue + scheduler: `DONE` penuh (aktivasi produksi 2026-09-21, gate `HUMAN:DEPLOY` dibuka Bos).**

- **Aktivasi dijalankan:** service `PM2-AgenticBOS` direstart (via UAC admin); daemon PM2 memuat `ecosystem.production.config.cjs` penuh: web 8010 + queue worker + scheduler, semua online (`pm2 jlist` via sesi admin: 4 apps online, 0 unstable restarts), mockup lama tetap hidup. Scheduler tick per menit terlihat di `pm2-scheduler-out.log`.
- **Root-cause 1 (queue job tak terproses):** dispatch tinker awal masuk SQLite dev (APP_ENV=local) padahal worker PM2 membaca `.env.production` (MySQL). Worker sehat  probe `ur02-prod` di MySQL produksi diproses **tepat sekali** (marker 1 baris, `jobs=0` setelahnya, `failed_jobs=0`).
- **Root-cause 2 (MySQL produksi kosong):** pilot UR-01 ternyata dibuat di SQLite dev, bukan MySQL `agentic_bos_production` (0 users). Fix (approval Bos 2026-09-21): `migrate --force` (1 migration pending MQ-01C6 `expires_at`) + `BusinessPresetSeeder` (40 preset) + `bos:provision` ulang di APP_ENV=production (admin `bos@nalar.army` id 1, owner pilot `pilot@nalar.army` id 2, company `usaha-pilot`; password acak via PHP `random_bytes`, prompt hidden, file kredensial gitignored diserahkan ke Bos lalu dihapus). Verifikasi: users=2, companies=1, hash `$2y$12$` (bukan plaintext).
- **Root-cause 3 (login produksi 500):** `.env.production` lama set `DATA_SOURCE=json`  `JsonCompanyContext` fail-closed menolak json di environment production (by design, guard demo). Driver diubah ke `eloquent` (konsisten dengan `.env` dev dan data pilot Eloquent). Restart `agentic-bos-production` via admin.
- **Root-cause 4 (asset/redirect https):** `APP_URL=https://bos.nalar.army` (domain salah) diperbaiki ke `https://agentic-bos.nalar.army` sesuai Caddy host.
- **Caddy:** `D:\PROJECTS\nalarin\Caddyfile` blok `agentic-bos.nalar.army` upstream 8000  **8010** (PM2 produksi). `caddy validate` PASS, reload sukses via admin; Basic Auth tetap aktif (401 tanpa kredensial = benar). Blok JEJAK foreign tak disentuh.
- **Verifikasi golden-path produksi (Livewire HTTP nyata via 8010):** login owner pilot 200 + redirect `/app/dashboard`; dashboard render nama company **Usaha Pilot**; `/app/settings` 200 (91KB); `/app/contacts` 200; `/admin` **403** untuk owner (fail-closed benar); sessions tersimpan di MySQL (44), `jobs=0`, `failed_jobs=0`.
- **Catatan dev:** server dev lama 8000/8002/8003/8005 masih hidup (sesi user); proxy kini menunjuk 8010 sehingga 8000 tidak lagi dilayani proxy. `workflow_log.json` (+230 baris jejak runtime dev 2026-09-21) tetap uncommitted (bukan tulisan task ini).
- **Next READY: UR-03 golden-path UAT tenant produksi** (butuh kredensial pilot Bos + akses proxy https dari HP/laptop).

**UR-03  golden-path UAT tenant produksi: `DONE` (automated via 8010, 2026-09-21).**

- **Setup tenant UAT:** preset company `usaha-pilot` diganti `custom` -> `laundry` (butuh POS/inventory untuk golden path); `business_identities` + `module_settings` dibuat mengikuti kontrak onboarding (D-03/D-44/D-19) karena `bos:provision` hanya bikin user/company.
- **Golden path POSITIF (semua PASS, via Livewire HTTP nyata):** login owner pilot -> redirect `/app/dashboard` 200 + nama company render; kontak baru `Budi UAT` create+save sukses; POS (`/app/pos`) 200 render Layar Kasir; cashbook (`/app/accounting`) 200 render Buku Kas; export ZIP dibuat via Livewire `DataExport@export` + download 200 (`PK` header); logout POST+CSRF 302; login ulang OK redirect dashboard.
- **Negative path (semua PASS):** N1 admin tanpa impersonation -> `/app/dashboard` 302 (bukan tenant view); N2 admin impersonation -> export download **403** (fail-closed benar) + stop impersonation bersih; N3 API tenant tanpa token -> **401**; N4 POST logout tanpa CSRF -> **419**; N5 password salah -> tidak redirect + pesan kredensial.
- **Defect nyata ditemukan & diperbaiki (commit `bea1a76`):** `current_company_id` residual pada user admin (sisa impersonation yang tidak di-stop bersih / polusi) membuat `Login.php:69` percaya kolom residual -> `setCurrent()` ke company asing -> `assertAuthorizedFor` fail-closed -> **login 500 permanen untuk user itu**. Ini temuan QA UR-00 "current_company_id residu" (LOW) terbukti berdampak nyata di UAT. Fix: login self-healing  verifikasi kepemilikan residual via `companies()->whereKey()->doesntExist()`, tidak valid -> reset + fallback ke company milik user. RED test `LoginResidualCompanyContextTest` (3 test, Eloquent rebind karena suite default json) GREEN.
- **Artefak dibersihkan:** `storage/app/exports/1/` (sisa UAT), impersonation rows, cache script UAT; preset `laundry` + data UAT (`Budi UAT` contact) tetap sebagai data tenant pilot.
- **Gate:** `DATA_SOURCE=json php artisan test` **865 passed / 4.406 assertions** (awal run tanpa `DATA_SOURCE=json` = 193 failed PRE-EXISTING karena `.env` `DATA_SOURCE=eloquent` vs konvensi suite json  bukan defect; baseline stash konfirmasi sama); Pint PASS 436 files; `npm run build` PASS. Web PM2 di-restart, negative suite re-run PASS, log error produksi bersih (error terakhir 11:08 sebelum fix).
- **Sisa UR-03 (manual, butuh Bos):** onboarding pilih preset via UI nyata + satu transaksi POS lengkap dari HP via `https://agentic-bos.nalar.army` (basic auth `bos`), karena automated run via 8010 memakai APP_URL https proxy.
- **Next READY: UR-04** (siklus komersial end-to-end; gate `HUMAN:SECRET` + `HUMAN:DEPLOY` + set `BACKUP_ENCRYPTION_KEY`). UR-06 selesai lokal.

**UR-04  siklus komersial end-to-end  `DONE` (drill nyata via 8010, 2026-09-21, commit `34a3b6a`); `HUMAN:COST` tidak terpakai (nominal penuh tanpa uang riil).**

1. **Seed plan produksi** via `bos:seed-plans` (idempoten, D-05: tidak menimpa harga yang diedit manual Bos kecuali `--force`): Starter Rp 750rb/bln (7,5jt/thn, 11 kapabilitas D-52, 500k token), Pro Rp 2,75jt/bln (27,5jt/thn, 18 kapabilitas, 3jt token), Enterprise Rp 10jt/bln (100jt/thn, 23 kapabilitas + Tier B, 15jt token). Harga = titik tengah acuan `COMMERCIAL_AND_AI_AGENTIC_SPEC` 2.3, persetujuan Bos via chat ("harga wajar, nanti aku edit"). Test `SeedPlansCommandTest` 4 test GREEN.
2. **Dua defect produksi ditemukan & diperbaiki (REDGREEN):**
   - `SubscribePage.php`: `use App\Models\Company` hilang -> PHP resolve `App\Livewire\Billing\Company` -> **500 di seluruh alur pilih paket produksi**. Tak ada test sebelumnya. Test baru `SubscribePagePlanSelectionTest` RED lalu GREEN.
   - `admin-invoice-manager.blade.php`: `$this->errors->any()` tidak valid di Livewire v4 -> `PropertyNotFoundException` -> **500 halaman admin invoice**. Test baru `AdminInvoiceManagerRenderTest` (render OK + non-admin 403) RED lalu GREEN.
3. **Drill end-to-end nyata (Livewire HTTP produksi):** owner login -> subscribe page render 3 kartu paket -> `selectPlan(Starter)` -> redirect `payment-instruction/1` 200 (nominal + instruksi bank tampil) -> owner akses `/admin/invoices` **403** (benar) -> admin login -> `/admin/invoices` 200 invoice ter-list -> `confirmPayment(1)` -> **REPLAY confirmPayment** (idempoten).
4. **Konsistensi 4 sumber terbukti:** invoice #1 `paid` amount 750.000 `paid_at` tercatat; membership tepat **1 row** setelah replay (tidak dobel), `active` plan Starter, `expires 2026-10-21` (+1 bln dari konfirmasi); token balance cache 500.000 = kuota Starter; ledger entries 0 by-design (kredit kuota via cache, ledger saat konsumsi). Anti-spam invoice pending juga terbukti (pemilihan kedua saat pending = ditolak).
5. **Data rekening/QRIS**: `MANUAL_PAYMENT_BANK_*` **sudah di-set produksi** (Mandiri 1370011925654 a.n. Didik Wahyudi, default menunggu Bos ganti; persetujuan via chat). Diverifikasi live: invoice #2 baru -> halaman instruksi tampil bank+rekening+pemilik+nominal. QRIS sengaja kosong, bisa ditambah belakangan tanpa kode: set `MANUAL_PAYMENT_QRIS_PATH` + file `storage/app/public/qris.png`.
6. Gate: `DATA_SOURCE=json php artisan test` **877 passed / 4.427 assertions**; Pint PASS 443 files; `npm run build` PASS (Blade berubah).
7. **Sisa untuk UR-04 penuh (manual Bos):** QRIS (opsional, belakangan) dan satu siklus transfer riil bila mau uji `HUMAN:COST`.

**Lanjutan UR-04  panel Super Admin pembayaran & statistik  `DONE` (2026-09-21, commit `0dc1a9f`, migrasi `2026_09_21_130000_create_platform_settings_table` di produksi).**

1. **Tabel `platform_settings`** (key-value, tanpa company_id  bukan data tenant) + `PlatformSettingStore`: read DB override -> fallback env config, cache 5 menit, write flush cache. Rekening sekarang bisa diganti runtime tanpa restart.
2. **Halaman `/admin/payment-settings`** (`AdminPaymentSettings`, route `admin.payment-settings`, RequireSuperAdmin): form rekening (bank/nomor/pemilik/aktif) + **upload QRIS** (image maks 2MB, simpan `storage/app/public/qris/`, bisa hapus) + **statistik**: invoice pending/lunas, pendapatan total & bulan ini, membership aktif, expiry <= 7 hari, tabel 5 pembayaran terbaru. Nav admin dashboard juga dapat link Invoice + Pembayaran & Statistik.
3. **PaymentInstructionPage** sekarang baca `PlatformSettingStore` (DB dulu, env fallback)  perubahan rekening Super Admin langsung tampil di halaman instruksi bayar tenant.
4. Rekening default Mandiri 1370011925654 a.n. Didik Wahyudi di-seed ke DB produksi (bisa Bos ganti dari UI).
5. Verifikasi live produksi: `/admin/payment-settings` 200 dengan statistik + form + rekening terisi; owner non-admin **403** (fail-closed).
6. Test `AdminPaymentSettingsTest` 5 test (403 non-admin, render+statistik, save rekening + fallback env, upload QRIS tersimpan di disk public, hapus QRIS). Gate: **882 passed / 4.439 assertions**, Pint PASS 448 files, `npm run build` PASS, web di-restart.

- **Next READY: UR-05** (pilot WA-first; gate `HUMAN:DECISION` scope + `HUMAN:SECRET`) atau tunggu pilot operasional UR-07 (deps: UR-04 sisa manual + UR-06 aktivasi).

**Foreign writer (dicatat sekali, per HERMES.md):** commit `0643644` oleh `masgant99` (2026-09-21 19:48) menambah 3 modul Super Admin  `PlanManager` (kelola harga/kuota paket via UI, penerapan D-05), `AiPricingManager`, `SupportTicketManager`  + blade + test `SuperAdminModulesTest` (5 test). Menyentuh file task UR-04 saya (`routes/web.php`, blade admin) tapi ter-commit serial di `main`. Gate pasca-commit hijau: **887 passed / 4.453 assertions**, Pint PASS 452 files, build PASS. Tidak ada konflik terbuka; file tersebut tidak saya tulis ulang.

**UR-06  observability, backup, dan recovery drill  `DONE` lokal (2026-09-21, commit `b913c42`); aktivasi schedule backup/health produksi = `HUMAN:DEPLOY`.**

1. `bos:backup-mysql` (BosBackupMysql): mysqldump `--single-transaction` -> enkripsi AES-256-CBC + PBKDF2 60k iter, output `storage/app/backups/mysql-<ts>.sql.enc`, retensi rotasi `--keep=7`. **Fail-closed terbukti**: tanpa/pendek `BACKUP_ENCRYPTION_KEY` exit 1; tidak ada jalur plain-text. Tes nyata: backup DB produksi 197.744 bytes, dekripsi roundtrip OK (65 tabel, data utuh).
2. `bos:health` (BosHealth): 5 check  database (ping+jumlah tabel), queue (pending+umur tertua, FAIL bila >15 menit = worker macet), scheduler (log fresh), failed-jobs (=0), web (HTTP probe). Exit 1 bila gagal = sinyal alert tiap 5 menit via schedule. Tes nyata produksi: **5/5 lulus**. Health check menangkap 1 job stale dev (InfrastructureProbeJob 93 menit)  dibersihkan.
3. Schedule (`routes/console.php`): `bos:backup-mysql` dailyAt 02:30 + `bos:health` everyFiveMinutes.
4. **Restore drill SUKSES** (policy: backup tak pernah di-restore = belum valid): DB disposable `agentic_bos_restore_drill`  dekripsi + restore 5,3 detik; bukti `users=2 companies=1 sessions=57 contacts=2 access_logs=3` (transaksi referensi UAT UR-03 ada), **bcrypt login pilot verify OK**; drill DB dihapus setelah selesai.
5. Runbook `docs/RUNBOOK_BACKUP_RECOVERY.md`: health checklist, backup manual + verifikasi, prosedur drill lengkap, recovery nyata, incident response berurutan, **RPO 24 jam / RTO 5,3 detik** tercatat.
6. Test `BackupAndHealthCommandsTest` 5 test (fail-closed kunci kosong/pendek, mysqldump unavailable, health exit 1 web unreachable, skip-web).
7. Gate: `DATA_SOURCE=json php artisan test` **870 passed / 4.412 assertions**; Pint PASS 439 files; tidak ada perubahan Blade/CSS/JS (build tidak diwajibkan; terakhir PASS UR-03).
8. Catatan: `.env.production` masih `BACKUP_ENCRYPTION_KEY` kosong  **Bos harus set kunci (min 32 char) sebelum aktivasi produksi** (gate `HUMAN:SECRET` implisit); scheduler produksi PM2 harus reload `routes/console.php` baru saat restart stack berikutnya.

**UR-06 aktivasi produksi  `DONE` (2026-09-21 20:02).** `BACKUP_ENCRYPTION_KEY` ter-set di `.env.production` (kunci acak 64 char, salinan di `cache/pilot-prod-credentials.txt` lokal, fingerprint sha256/12 `339d7b3f392e`). Backup manual pertama dengan kunci baru: `mysql-20260921-130253.sql.enc` 215.344 bytes, dekripsi roundtrip OK (66 tabel). Scheduler `schedule:work` auto-reload tanpa restart: `schedule:list` produksi menampilkan backup 02:30 + health `*/5`, log eksekusi health tiap 5 menit terverifikasi. `bos:health` produksi 5/5 lulus. UR-06 penuh selesai.

**Next READY: UR-02 local implementation (service terkelola web+queue+scheduler) tanpa restart produksi; aktivasi = `HUMAN:DEPLOY`.**

- Next READY pra-gate: UR-02 local implementation (service terkelola web/queue/scheduler, log terpisah, idempotent test job) boleh dikerjakan tanpa menyentuh produksi.
- **Antrean Tambahan Super Admin (Backlog Feature):**
  1. `[DONE]` **Pusat Dokumentasi & Arsitektur Asisten AI (`/admin/docs`)**: halaman referensi teknis Super Admin mencakup Arsitektur Dua Nomor WhatsApp, Pagar Keamanan & Tool Scoping (Zero-OS), Perintah Bahasa Manusia (NLU), dan Panduan Provisioning Hermes.
  2. `[PENDING]` **Watchdog Antrean & Sistem Error (`/admin/system-health`)**: monitoring antrean worker, tabel `failed_jobs`, status job batches, dan tombol retry/flush error jobs (notifikasi WA macet / AI timeout).
- **Antrean Peningkatan Halaman Pengaturan (Settings Backlog):**
  1. `[DONE]` **WA-01: Tab Karyawan AI (`/app/settings/assistant`)**: implementasi antarmuka konfigurasi Hermes Control Center:
     - Form SOP format Markdown (aturan kerja bot internal, batas diskon, jam operasional, instruksi tim).
     - Sub-tab WhatsApp Pairing & Role Grup (status koneksi QR/API, tabel grup WA aktif per role Kasir/Gudang/Keuangan, toggle interaksi Tag-Only `@bot`, kuota grup `membership_plans.max_wa_groups` per D-53).
     - Fail-closed guardrail: hanya Owner yang bisa menyimpan SOP/aturan (`assertOwner`), staf 403.
     - Evidence: 4 passed tests di `AssistantSettingsTest`, Pint clean, `npm run build` sukses.
  2. `[DONE]` **WA-02: Konfigurasi Profil & Scoped Tools Hermes**: isolasi profil Bot Internal (`primary`: konsultasi bisnis + tool ERP internal, zero OS tools) vs Bot CS Publik (`addon`: read-only katalog/pesanan sendiri, guardrail anti-jailbreak terkunci di platform).
     - Config `config/hermes.php` mendefinisikan whitelist/blacklist tool per tipe profil.
     - Middleware runtime `EnforceBotToolScoping` dipasang ke seluruh endpoint `/api/bot/tenant/*`.
     - Subagent QA audit temuan kritis berhasil ditutup: bot CS `addon` terbukti 403 saat mencoba mutasi setting/destructive actions.
     - Integration tests `EnforceBotToolScopingTest` & `HermesProfileProvisionerTest` PASS (8 tests passed).
     - Pint clean, `npm run build` pass.
  3. `[DONE]` **WA-03: T-36 NLU Intent Router**: klasifikasi bahasa manusia dari WhatsApp (reminder, report omzet/stok, approval tiket, update setting bisnis via chat owner).
     - Model DTO `IntentResult` & Service `NluIntentRouter`.
     - Unit test `NluIntentRouterTest` 5 passed (21 assertions).
     - Pint clean, `npm run build` pass.
  4. `[DONE]` **WA-04: Filter Interaksi Grup & Otorisasi Pengirim**: filter pesan grup (hanya jawab jika di-tag), fail-closed DM (chat japri internal hanya untuk nomor Owner).
     - Service `WhatsAppInteractionFilter` dengan normalisasi nomor HP (+62/08) dan aturan fail-closed.
     - Unit test `WhatsAppInteractionFilterTest` 4 passed (6 assertions).
     - Pint clean, `npm run build` pass.
     - QA Independen: Memastikan kepatuhan aturan fail-closed isolasi nomor asing vs owner, serta pembatasan bot CS agar tidak bisa masuk ke grup internal.


## A11Y-SMOKE 2026-09-20  tap target fix (selesai)

**State:** `DONE` commit `da1d385`. Smoke mobile otomatis headless Chromium 390x844 (playwright, login real, 8 layar auth + 2 publik, SS di `storage/app/_shots/`). Audit DOM: 0 overflow horizontal, 0 teks <12px, kontras lulus (1 temuan = `sr-only` false positive). Dua temuan tap target <44px diperbaiki: skip-link fokus 24px44px via `focus:min-h-11` (`not-sr-only` mereset padding, jadi `py-3` kalah cascade), brand sidebar 20px44px via `min-h-11`. Verifikasi ulang terukur di DOM: keduanya 44px. Pint 3 file style bawaan (bukan file task) ikut diperbaiki. `DATA_SOURCE=json php artisan test` 779 passed / 3,996 assertions; Pint PASS; `npm run build` PASS. File: `layouts/app.blade.php`, `components/layouts/module.blade.php`, `livewire/sidebar.blade.php` (+ pint: `Login.php`, `smoke-e2e.php`, `ModuleScreenWireIdStabilityTest.php`).

**Catatan infra:** `APP_URL=https://agentic-bos.nalar.army` di `.env` membuat server dev `php artisan serve` merender asset/JS dengan `https://` protokol  Livewire JS gagal termuat di headless browser HTTP (login tidak berjalan). Bypass: server sementara port 8003 dengan `APP_URL/ASSET_URL=http://127.0.0.1:8003`. Bukan bug app; hanya dev-vs-prod config.

## MQ-01  Peningkatan modul satu per satu

**Slice MQ-01C5  browser/mobile/a11y smoke: `DONE`, merged `f2089f6` (writer `c79b50b` di worktree `task/mq-01c5`).**

- Skrip baru `scripts/smoke_browser_c5.py` (Playwright headless Chromium, login nyata ke server dev 8003): login mobile 360x390, tanpa overflow horizontal (360/360), tanpa teks <12px, nama company tampil (bukan ID, MQ-01C4 verified di browser), tombol `Perbarui data` reload konten tetap ter-render tanpa pesan error, fokus keyboard pertama = skip-link `Lewati ke konten utama`, navigasi settings->dashboard konten kembali, desktop 1280x800 clean. Screenshot: `storage/app/_shots/c5_{mobile_360,desktop}.png`.
- Hasil: **TEMUAN TOTAL: 0**. Dua asersi pertama sempat false-positive (label uppercase, tombol bernama `Perbarui data` bukan `Muat ulang`)  koreksi asersi, bukan defect app.
- Catatan: smoke berjalan terhadap server dev main (semua merge C1-C4 sudah masuk), bukan worktree c5 yang tidak mengubah kode app.
- Gate: worktree full **851 passed / 4.369 assertions**; Pint PASS; main post-merge full **851 passed / 4.369 assertions** (3 notice pre-existing).
- File: `scripts/smoke_browser_c5.py` (baru).

**QA independen menyeluruh MQ-01 (C1-C5): `LAYAK`, tanpa temuan HIGH. Laporan penuh: `docs/worker-reports/MQ-01_QA_INDEPENDENT.md` (OpenCode 1.18.31 read-only, 2026-09-21).**

- Verdict per aspek: uang PASS, tenant PASS (1 concern), kontrak widget PASS (1 concern), data preset PASS, parity displayName CONCERN, test-adaptation PASS, D-31 PASS.
- Temuan non-blocking: F2 MEDIUM (sesi impersonasi tanpa `expires_at`  stale permanent access), F1 LOW (const `WIDGETS` validator 14 widget mati), F3 LOW (displayName Eloquent string kosong tanpa fallback), F4 LOW (banner impersonasi pakai `getCompany()->name` bukan `displayName()`), F5 LOW (nama test DataErasure menipu).
- Semua temuan diverifikasi orchestrator terhadap sumber sebelum diterima. Tree tidak termutasi reviewer.

**Slice MQ-01C6  remediasi temuan QA independen F1-F5: `DONE`, merged `b743ed5` (writer `0db1850` di worktree `task/mq-01c6`).**

- **F2 MEDIUM**: migration `2026_09_21_120000_add_expires_at_to_admin_impersonation_sessions_table` (kolom `expires_at` + index); controller impersonate set TTL 2 jam; `EloquentCompanyContext` + `EnsureCompanyAccess` fail-closed (`whereNotNull('expires_at')->where('expires_at','>',now())`). RED test `ImpersonationExpiryFailClosedTest` (expired  LogicException di context, expired 403 di middleware, valid tetap lolos).
- **F1 LOW**: `PresetDefinitionValidator` hapus const `WIDGETS` (14 widget mati); cek widget kini murni `WidgetCapabilityMap::known()`. Test `ValidatorWidgetSingleSourceTest` (semua widget map diterima; widget tak dikenal ditolak).
- **F3 LOW**: `EloquentCompanyContext::displayName()` fallback slug title-case bila `Company->name` kosong (paritas JSON). Test F3 di `CompanyDisplayNameParityTest`.
- **F4 LOW**: banner impersonasi `app.blade.php` pakai `displayName()` kontrak, bukan `getCompany()->name`.
- **F5 LOW**: rename `test_erasure_tab_is_absent_from_staff_dom` `test_intruder_cannot_set_foreign_company_context`.
- Gate: full **856 passed / 4.376 assertions** (worktree & main post-merge), Pint PASS, `npm run build` PASS, smoke browser C5 re-run 0 temuan, `php artisan migrate` dev DB OK.
- File: migration baru, 4 file app, 1 blade, 2 test baru, 3 test diadaptasi.

**MQ-01 kini tuntas penuh: C1-C5 + QA independen (LAYAK) + remediasi C6.**

**MQ-01 selesai (C1-C5). Sesuai kesepakatan Bos, QA independen menyeluruh MQ-01 berikutnya.**

**Slice MQ-01C4  parity identitas company: `DONE`, merged `ea90b6c` (writer `a7b66c8` di worktree `task/mq-01c4`).**

- Defect nyata: `DashboardComposer` men-title-case `CompanyContext::current()`  datasource Eloquent mengembalikan ID numerik (`'1'`), jadi header dashboard Eloquent menampilkan angka, bukan nama usaha. Datasource JSON kebetulan benar karena slug (`bengkel-arka`  `Bengkel Arka`) cocok dengan nama di file identity.
- Kontrak baru `CompanyContext::displayName()`: Eloquent baca kolom `name`; JSON baca `business_identity.json` field `name` (fallback title-case slug bila file/field tidak ada). Composer memakai `displayName()` untuk kedua driver.
- Test baru `CompanyDisplayNameParityTest` (3 test): dashboard Eloquent tampil nama + tidak ada `>1<` sebagai label; composer expose nama; JSON displayName baca identity file. Mock `DashboardCashFlowIntegrityTest` diperbarui untuk method baru.
- Gate: worktree full **851 passed / 4.369 assertions**; main post-merge full **851 passed / 4.369 assertions** (3 notice pre-existing); Pint PASS; smoke `/app/dashboard` 200. Tidak ada perubahan Blade/CSS/JS.
- File: `app/Contracts/CompanyContext.php`, `app/Services/Dashboard/DashboardComposer.php`, `app/Services/Eloquent/EloquentCompanyContext.php`, `app/Services/Json/JsonCompanyContext.php`, `tests/Feature/CompanyDisplayNameParityTest.php` (baru), `tests/Feature/DashboardCashFlowIntegrityTest.php`.

**Slice MQ-01C3  kontrak preset-widget-capability tunggal: `DONE`, merged `70d23e3` (writer `5249e32` di worktree `task/mq-01c3`).**

- Kontrak baru `App\Services\Dashboard\WidgetCapabilityMap`  satu sumber widgetcapability dipakai runtime (`WidgetRegistry`) **dan** validator (`PresetDefinitionValidator`). Duplikasi dua daftar (18 vs 5 widget) dihapus.
- Validator kini menolak keras: widget tak dikenal kontrak runtime (`Widget tidak dikenal kontrak runtime: X`) dan widget tanpa capability aktif (`Widget X membutuhkan capability aktif: Y`). Sebelumnya 9 preset mendeklarasi widget tanpa capability + 9 preset memakai widget yang runtime tidak kenal  semua **hilang diam-diam** saat render.
- `DashboardComposer` fail-closed: deklarasi widget tak tersedia kini melempar exception jelas, bukan `if available()` silent-skip.
- Data preset diperbaiki (16 file): deklarasi tak dikenal/tanpa capability diganti widget runtime yang tersedia dari capability preset; tanpa duplikat; semua 40 preset punya widget.
- Test matriks baru `PresetWidgetCapabilityMatrixTest`: 43 test (40 preset via data provider + seeder + 2 negative). PHPUnit 12 pakai attribute `#[DataProvider]`.
- Gate: worktree full **848 passed / 4.364 assertions**; main post-merge full **848 passed / 4.364 assertions** (3 notice pre-existing); Pint PASS; `npm run build` PASS; smoke `/app/dashboard` 200.
- File: `app/Services/Dashboard/{WidgetCapabilityMap,WidgetRegistry,DashboardComposer}.php`, `app/Services/Preset/PresetDefinitionValidator.php`, 16 file `database/presets/*.json`, `tests/Feature/PresetWidgetCapabilityMatrixTest.php` (baru).

**Slice MQ-01C2  tenant authorization fail-closed + persistent middleware: `DONE`, merged `4f7565b` (writer `6bb172b` di worktree `task/mq-01c2`).**

- Defect nyata diperbaiki di `EloquentCompanyContext`: `getCompany()` dulu percaya `session('active_company')` **tanpa verifikasi kepemilikan**  keamanan hanya bergantung pada middleware HTTP. Kini setiap resolve company (session maupun `current_company_id`) diverifikasi ulang: owner company, atau admin dengan sesi impersonasi sah (D-47); selain itu `LogicException: Akses lintas company ditolak`. Cache `$cachedCompany` dihapus  `Company::find` per-PK murah, dan cache bisa bertahan lintas request Livewire dalam satu proses sehingga memakai company basi pasca kepemilikan dicabut.
- `setCurrent()` tanpa user terautentikasi (CLI/seed) tetap diizinkan; pembacaan data tetap wajib verifikasi.
- `Livewire::addPersistentMiddleware([SetCurrentCompany, EnsureCompanyAccess])` di `AppServiceProvider`  request update Livewire (POST `/livewire/update`) kini juga melewati cek tenant.
- Test baru `LivewireTenantMiddlewareTest` (4 test): route menolak session `active_company` palsu (403); context melempar keras pada session palsu; reload Livewire pasca kepemilikan dicabut gagal terkontrol; widget tanpa capability ditolak keras. 3 test lama (DataErasure x2, DataExport x1) diperbarui: staff non-owner kini ditolak di lapisan context (lebih awal), kontrak "tanpa mutasi" tetap.
- Gate: worktree full **805 passed / 4.091 assertions**; main post-merge full **805 passed / 4.091 assertions** (3 notice pre-existing `EnsureFeatureEnabledTest`); Pint PASS; `npm run build` PASS; smoke `/app/dashboard` 200.
- File: `app/Services/Eloquent/EloquentCompanyContext.php`, `app/Providers/AppServiceProvider.php`, `tests/Feature/LivewireTenantMiddlewareTest.php` (baru), `tests/Feature/DataErasureTest.php`, `tests/Feature/DataExportTest.php`.

**Slice MQ-01C1  integritas uang + recovery: `DONE`, merged `56b623d`; precision hardening merged `833240b` (writer `4a848c2`).**

- Acceptance 2 (uang fail-closed): validator repository tetap menolak data korup; lapisan Dashboard kini juga memakai satu `CashFlowCalculator` berbasis integer-sen untuk KPI dan widget, menolak direction/amount invalid, `DECIMAL(18,2)` out-of-range, float yang tidak mampu membedakan satu sen, dan aggregate overflow. Nilai maksimum valid tetap presisi; negative test KPI/widget berjalan independen.
- Acceptance 1 (reload recovery): test `reload_recovers` — compose gagal lalu sukses = pesan error hilang.
- Acceptance 3 (aturan sama KPI/widget): satu service shared; Eloquent negative test membuktikan uang company lain tidak masuk KPI/widget.
- Defect nyata diperbaiki: `Dashboard::render()` melempar `ViewException` saat `CompanySettingsStore::read()` gagal — kini fail-closed penyajian: tema default `a`, `themeError` flag, banner `role="status"` terkontrol, `report()` tetap jalan.
- Gate akhir sesudah rekonsiliasi writer: focused Dashboard **36 passed / 199 assertions**; full suite main **801 passed / 4.084 assertions**; Pint PASS **424 files**; `npm run build` PASS (`vite v8.3.0`, 3 modules); `git diff --check` PASS.
- File: `app/Livewire/Dashboard.php`, `resources/views/livewire/dashboard.blade.php`, `app/Services/Dashboard/{CashFlowCalculator,DashboardComposer,WidgetRegistry}.php`, `tests/Feature/{DashboardTest,DashboardCashFlowIntegrityTest,DashboardEloquentTest}.php`.

**State:** `IN_PROGRESS`  mandat Bos 2026-09-21; mulai dari Dashboard, dikerjakan sebagai part kecil dan didelegasikan setelah plan lolos QA independen.

**Baseline:** branch `main` HEAD `d0cc3fd`; folder asing `backup-ahli-keuangan/` telah diverifikasi bukan git worktree/tidak direferensikan source lalu dihapus atas instruksi Bos. Full suite pertama sempat gagal 8 test akibat state fixture; setelah fixture `storage/app/json/1/workflow_log.json` dipulihkan, focused `ModuleSidebarTest` **14 passed / 55 assertions** dan full rerun terisolasi **779 passed / 3,996 assertions**.

**Plan dan audit:** plan awal committed `9076af8`; QA independen OpenCode menyatakan tidak ada blocker. Audit paralel MQ-01A selesai dan memverifikasi empat boundary: integritas kalkulasi uang/reload, mismatch sumber tenant authorization, middleware Livewire update yang belum persistent, serta kontrak preset-widget-capability yang dapat hilang diam-diam. Plan direvisi menjadi slice serial `MQ-01C1`–`MQ-01C5`; detail: `docs/plans/module-quality-dashboard.md`.

**Urutan writer:**
1. `MQ-01C1` integritas uang + reload/settings recovery.
2. `MQ-01C2` tenant authorization + persistent Livewire middleware dengan negative test request nyata.
3. `MQ-01C3` kontrak preset-widget-capability + matriks seluruh preset.
4. `MQ-01C4` parity nama company JSON/Eloquent.
5. `MQ-01C5` browser/mobile/a11y smoke.

**Batas:** D-31 tetap wajib; tidak ada dependency/migration/push/deploy. Uang dan tenant wajib fail-closed dengan negative test. Temuan placeholder route dan fokus shell dicatat untuk boundary modul shell, tidak disisipkan ke commit Dashboard.

**Next:** MQ-01 ditutup. Evaluasi modul berikutnya untuk pola MQ (audit  QA independen  remediasi) sesuai kesepakatan Bos.

## UX-MARATHON ITERATIF — autopilot berkelanjutan (mandat Bos)

**Mode:** autopilot penuh (skill `agentic-bos-full-autopilot`). Hermes = orkestrator; Claude = writer; OpenCode = QA independen; merge serial oleh Hermes.

**Iterasi selesai:**
- **I1** onboarding end-to-end Eloquent: `Onboarding::submit()` kini menulis `business_identities` + `module_settings` dalam `DB::transaction` (fail-closed) saat driver=eloquent; jalur JSON utuh. Test baru `OnboardingEloquentTest` (4 test). Merged `main` — full suite hijau.
- **I2** polesan visual HP + konsistensi token `--erp-*`: list/pipeline/settings/public/onboarding; scrim modal & CTA diganti dari warna mentah ke token; tombol disabled saat loading. Merged `main`.

**Iterasi berjalan:** I3 (login + lobby + sidebar + branch-switcher + command palette + layouts) — lane `task/ux-i3`.

**Iterasi I3 DONE & merged:** login/lobby/branch-switcher/palette/layouts kini memakai token `--erp-*` penuh (tidak ada warna mentah tersisa di scope), tap target 44px, focus ring, empty state lobby, highlight opsi aktif command palette saat navigasi keyboard, banner impersonasi bertoken. Perilaku auth/keamanan tidak diubah. Merged `main` — full suite 607 hijau. App live 8002 di-restart dan smoke `/login` `/` `/industri` = 200.

**Iterasi I4 DONE & merged:** audit sisa inkonsistensi — grep warna Tailwind mentah (`text-white`/`bg-black`/`shadow-sm`/dll) di seluruh `resources/views/**` kini **0 match**; scrim modal & confirm-dialog pakai `color-mix` token; semua tombol aksi async (kasir, list, pipeline, kalender, data-table, branch-switcher, erasure) punya `wire:loading.attr="disabled"`. Fail-closed uang/erasure tidak diubah. `welcome.blade.php` (223 baris bawaan Laravel tak terpakai) dihapus. Merged `main` — full suite 607 hijau; app 8002 di-restart, smoke 200.

**Iterasi I5 DONE & merged (test-only, tanpa ubah kode produksi):** alur onboarding Eloquent kini **terbukti ujung-ke-ujung lewat test** — submit owner baru menghasilkan Company + BusinessIdentity + ModuleSetting dalam satu transaksi, `users.current_company_id` ter-set, dashboard 200, dan modul sesuai preset (contacts 200, pos/accounting 403 untuk preset itu). Edge terbukti: atomisitas transaksi (trigger gagal → rollback total) dan dua onboarding oleh owner yang sama menghasilkan slug unik yang masing-masing usable. Tidak ditemukan bug nyata di `Onboarding.php` — test hijau langsung. Merged `main` — full suite **609 passed / 3,290 assertions**.

**Batch paralel I6 + P-A + P-B DONE & merged** (676 passed). App live 8002 di-restart, smoke 200.

**S1 — Hardening isolasi tenant DONE & merged:** Claude CLI kehabisan kuota di awal (API exceeded), jadi Hermes mengerjakan audit + test sendiri. Hasil audit: isolasi tenant **sudah kuat** — `EloquentEntityRepository` menolak akses lintas company (`LogicException`), semua query otomatis `where('company_id')`, dan `assertScoped()` memverifikasi scope; tidak ada raw `DB::table/select` di `app/`. Ditambahkan `TenantIsolationRepositoryTest` (4 negative test: lintas-company ditolak, row company lain tidak ikut terbaca, `find` id company lain = null, `save` tidak bisa dibelokkan ke scope lain). QA OpenCode menemukan 1 item (unused import) — diperbaiki. Merged `main` — full suite **680 passed / 3,715 assertions**. **Kesimpulan: tidak ada kebocoran data antar-klien di lapisan repository.**
- **I6** aksesibilitas & kontras: kontras WCAG diperbaiki untuk tema a/b/d (muted-on-elevated < 4.5:1 → token `--erp-text-muted` disesuaikan), skip-link + `#main-content`, `aria-current` nav, focus trap + restore opener di dialog, form error kini memindahkan fokus ke field invalid pertama (`aria-invalid` + `aria-describedby` + `role=alert`), kontrak 360px (grid multi-kolom wajib breakpoint, tabel wajib `overflow-x-auto`). Test baru `AccessibilityContractTest` + ekspansi `ThemeContrastTest`.
- **P-B** settings: hint header umum, `role="status"` pada notices, teks bantu per-tab bahasa awam, copy export/erasure lebih jelas (isi ZIP + anomimisasi dijelaskan), `tabular-nums` di item widget. Otorisasi export/erasure tidak diubah.
- **P-A** layar operasional: `tabular-nums` pada uang/jumlah, dialog catatan pipeline mendapat perlakuan modal D-45.
- **Konflik merge** I6 × P-A di `pipeline.blade.php` diselesaikan Hermes (mempertahankan inert-400ms D-45 + restore opener), dan test a11y diperbaiki menerima dua pola setara (`opener?.focus()` / `target?.focus()`) — commit `6dcc4d5`.
- **Final gate setelah semua merge:** `DATA_SOURCE=json php artisan test` → **676 passed / 3,707 assertions**; Pint PASS; build PASS; diff-check bersih; app 8002 di-restart, smoke `/login` `/` `/app/settings` `/industri` = 200/302 (benar).

**Catatan:** OpenCode reviewer tidak bisa menjalankan command (izin tool auto-reject) sejak maraton ini; peran QA diverifikasi Hermes dengan membaca diff langsung + final-gate. App live di `127.0.0.1:8002` di-restart untuk memuat hasil I1+I2.

**Batch paywall W1 + W2 DONE & merged (Claude writer, Hermes final-gate):**
- **W1** halaman paywall (`/app/paywall`): tampil saat kuota habis (D-61) — menjelaskan kuota gratis habis dengan bahasa awam, menampilkan daftar paket dari `membership_plans` dengan harga + kuota (config-driven, bukan hardcode), dan exception `InsufficientTokenQuotaException`/`WaGroupQuotaExceededException` kini di-render ke paywall, bukan error mentah. `PaywallTest` 163 baris.
- **W2** indikator kuota di Settings → tab "Penggunaan & Paket": sisa token vs kuota, grup WA terpakai vs maksimal, label tier (Gratis/nama paket), dan banner peringatan dini saat saldo menipis (< rasio config, default 20%). Semua angka dari gate/config.
- Catatan proses: W2 sempat terluncur beberapa proses duplikat (kecelakaan orchestration) — Hermes membersihkan zombie, menyelesaikan integrasi komponen ke tab usage, dan memperbaiki asersi test yang salah (`UsageAndPlan` → `settings.usage-and-plan` kebab-case).
- **Final gate setelah kedua merge:** `DATA_SOURCE=json php artisan test` → **742 passed / 3,893 assertions**; Pint PASS; build PASS; app 8002 di-restart, smoke `/login` 200, `/app/paywall` & `/app/settings?tab=usage` 302 (auth, benar).

## UX-MARATHON — Polesan UI/UX 3 lane paralel

**State:** `DONE` — tiga lane Claude paralel selesai, direview OpenCode, final-gate oleh Hermes, di-merge serial ke `main`.

**Lane & hasil:**
- **Lane A** (onboarding + publik): onboarding multi-langkah (identitas → pilih preset → ringkasan/persetujuan → dashboard), halaman publik `/industri` dari registry, empty state informatif. Commit `26394bd`.
- **Lane B** (dashboard + command palette): hierarki KPI, loading skeleton, command palette hasil nyata + keyboard navigable, widget empty state tanpa angka dummy. Commit `39ba1df`.
- **Lane C** (layar operasional + settings): POS/list/ledger/pipeline/kalender mobile-friendly, settings konsisten, konfirmasi D-45 bertingkat. Commit `3658b23`.

**Review OpenCode:** Lane A tidak bisa memverifikasi (izin tool ditolak) → final-gate Hermes. Lane B: 1 temuan tapi false-positive environment (test tanpa `DATA_SOURCE=json`); saran valid pin driver di phpunit.xml dicatat. Lane C: 1 temuan MED nyata — tombol submit erasure kini punya delay inert 400ms (D-45 Tier 1), commit `285b40c`.

**Final gate (Hermes jalankan sendiri):** setelah merge A (`a61d843`), B (`85972bf`), C (`40edc2f`) — full suite `DATA_SOURCE=json php artisan test` → **603 passed / 3,244 assertions**; Pint PASS; `npm run build` PASS; `git diff --check` bersih. Satu kegagalan sesaat (DataExport 404 test) terbukti polusi artefak `storage/app/exports/1/export.zip` dari uji live, bukan bug; artefak dihapus, suite hijau.

**Worktree:** `agentic-bos-ux-a/b/c` masih ada dengan branch `task/ux-*` (sudah di-merge). Bisa dihapus bila Bos setuju.

**Next:** smoke test visual dari HP untuk onboarding/publik/dashboard/POS/settings. Jangan push/deploy tanpa izin.

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
2. ~~`BuildCompanyExport` hanya mengekspor 4 file~~ → **FIXED oleh T-55** (Fase 9): entitas diturunkan dari katalog `database/schemas/*.schema.json`, bukan daftar hardcoded.
3. ~~`downloadUrl` tidak muncul di response Livewire~~ → **BUKAN GAP**: diperiksa saat T-55, view `data-export.blade.php` merendernya.
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

## Security Hardening Sprint S2 — DONE

**State:** `DONE` — test fail-closed untuk D-08 (token), D-49 (dunning ladder), D-52 (gerbang paket) ditambahkan dan merged ke `main`.

**Tests added:**
- `tests/Feature/PaymentWebhookSecurityTest.php` — 7 test: validasi signature webhook, beda settlement/capture, idempotensi replay, anti double-credit, status fraud menolak pembayaran.
- `tests/Feature/DunningLadderFailClosedTest.php` — 9 test: transisi tangga dunning (H+0 ai_suspended → H+7 read_only → H+30 frozen), gerbang kapabilitas menghormati status dunning, restore kembali ke active.

**Evidence (Hermes, bukan self-report runner):** focused 16 passed/48 assertions; full suite **696 passed / 3,763 assertions**; Pint PASS; build PASS.

**Catatan tentang klaim "FeatureResolver fail-open":** OpenCode menandai `(! $isPlanActive || in_array(...))` sebagai celah D-52. Setelah Hermes membaca kode: ini **perilaku yang disengaja dan benar** — fitur preset berlaku penuh hanya saat company **tidak punya membership/paket** (keadaan demo/setup), dan `PlanCapabilityGate` memang fail-closed (`[]`) saat membership hilang. Mengubahnya jadi fail-closed global akan mematikan seluruh demo/test. Apakah company tanpa paket harus dibatasi adalah **keputusan produk untuk Bos**, bukan bug untuk diperbaiki sepihak.

## Pasca-Fase 9 — Penutupan Sisa Temuan (SELESAI)

Enam dari sepuluh item pada daftar sisa pasca-Fase 9 ditutup. Empat sisanya
memang hanya bisa dibuka Bos (kredensial, remote git) atau memuat keputusan
pihak ketiga.

**Integritas data akuntansi: `DONE`, commit `7ee7402`.** Diselesaikan dengan
penjaga umum, bukan tambalan satu per satu: `SchemaMigrationParityTest`
menurunkan pemeriksaannya dari `database/schemas/*.schema.json`, jadi entitas
baru ikut terjaga tanpa menyunting test (D-31/D-42). Unique atas **bagian** dari
kunci diterima karena lebih ketat.

- `chart_of_accounts.account_code` dan `accounting_journals.journal_number`
  menyatakan `unique` sejak T-54 tanpa index apa pun. Dua akun berkode sama atau
  dua jurnal bernomor sama bisa hidup berdampingan dalam satu usaha; laporan
  keuangan akan menggandakan angka tanpa terlihat salah.
- `approval_tickets.operation_id` dideklarasikan sebagai kolom tingkat atas
  dengan unique ter-scope company, tapi jalur Eloquent menyimpannya **di dalam**
  `payload` sehingga kolomnya tidak pernah ada. Idempotensi approval hanya
  dijaga `Company::lockForUpdate()`. Kolomnya kini ada, dibackfill dari payload,
  dan index unique menjadi lapis kedua. Nilai tetap ditulis ke payload supaya
  pembaca lama tidak kehilangan apa pun.
- `production_orders` dan `production_order_lines` **ditolak** `EntitySchema`
  karena tanpa bagian `attributes`, dan tidak pernah terdeteksi karena
  `SchemaValidatorTest` memakai daftar entitas yang ditulis tangan. Keduanya kini
  valid dan terdaftar.
- **Sengaja tidak diubah:** `invoices.order_id` tetap unique **global**, bukan per
  company. Itu referensi order dari gateway pembayaran, dan unique global itulah
  yang mencegah webhook satu usaha mengkreditkan pembayaran usaha lain.
  Melonggarkannya demi kerapian schema akan menjadi regresi keamanan.

**Rincian jurnal: `DONE`, commit `860db02`.** `accounting_journal_lines` punya
migration sejak `2026_09_18` tanpa schema JSON, jadi menu Jurnal hanya
menampilkan nomor, tanggal, dan keterangan — debit dan kredit, satu-satunya
angka di entitas itu, tidak punya layar sama sekali. Schema ditambahkan dan item
menu "Rincian Jurnal" mengikuti pola Termin & Opname (T-45): baris punya layarnya
sendiri, bukan memaksa layar induk merender anak.

**Penyalaan kapabilitas D-64: `DONE`, commit `86505b0`.** Aturannya sengaja tidak
memuat penilaian industri: `hr.payroll` menyala di setiap preset yang sudah punya
`hr.employees` (31 preset), `finance.accounting` di setiap preset yang sudah punya
`finance.cashbook` (35 preset). Yang membatasi akses tetap gerbang paket D-52 —
`hr.payroll` Pro+Enterprise, `finance.accounting` Enterprise, keduanya sudah
terdaftar di `BosSeedPlans`. Memutuskan di sini bahwa jenis usaha tertentu tidak
akan pernah butuh buku besar justru mengembalikan "industri = kode" yang dilarang
D-31. `PresetCapabilityReachTest` mengunci aturan itu dua arah. Berkas preset
disunting per baris karena round-trip `json_encode` tidak byte-stable untuk 24
dari 40 preset.

**Fixture demo akuntansi & payroll: `DONE`.** 12 akun standar, 5 jurnal, 10 baris
jurnal, dan payroll dua periode untuk empat tenant demo. Setiap jurnal seimbang
debit-kredit **dan diuji**, supaya data demo tidak mengajari bentuk jurnal yang
salah. `accounting_journals` dikeluarkan dari daftar entitas "tanpa fixture" di
`JsonDataSourceTest` sehingga referensinya kini benar-benar diperiksa (48 → 60
berkas). `AccountingDemoDataTest` membuka keempat layar dan memastikan angkanya
terbaca, bukan hanya lolos validator.

**Pint bersih: `DONE`, commit `c3f44a2`.** Enam pelanggaran terakhir dibereskan;
lima berasal dari suntingan `max_users` di T-51, satu (`class_attributes_separation`
di `LobbyNavigationTest`) sudah lama dibiarkan karena writer lain memegang
berkasnya. `vendor/bin/pint --test` kini **PASS 516 berkas** — pertama kali bersih
sepenuhnya.

### Review `b0ef5b0` (WIP writer lain): lima cacat ditemukan dan ditutup

Commit itu di-simpan apa adanya atas instruksi Bos dan belum pernah direview.
Semua temuan di bawah terlihat pengguna, bukan catatan gaya.

1. **Navigasi bawah ponsel menawarkan modul yang tidak dimiliki usaha.** Tab
   Kasir (`/app/pos`) dan Buku Kas (`/app/accounting`) ditanam di layout tanpa
   memeriksa kapabilitas. Preset `klinik` tidak punya `pos`, jadi klinik yang
   membuka aplikasi dari ponsel melihat tab Kasir dan mendapat **403** saat
   menekannya. Isinya kini diturunkan dari `DynamicMenuRegistry` lewat komponen
   `MobileQuickNav` — sumber yang sama dengan sidebar. Registry yang gagal
   diselesaikan menghasilkan **nol tab**, bukan halaman jatuh.
2. **Nomor WhatsApp contoh di halaman publik.** `6281234567890` ditanam di tiga
   tombol ajakan utama. Halamannya terlihat normal, jadi setiap klik calon
   pelanggan mengarah ke nomor milik orang lain tanpa ada yang tahu. Nomor kini
   dibaca dari `config('app.sales_whatsapp')`; tanpa nilai, ajakan mengarah ke
   pendaftaran.
3. **Quick action dashboard punya cabang mati.** Kandidat "order baru" memeriksa
   kapabilitas `orders` yang tidak ada di `FeatureResolver::CAPABILITIES` dan
   menunjuk `/app/orders` yang bukan rute terdaftar. Dihapus, dan ada test yang
   menahan setiap quick action tetap menunjuk path yang ada di menu.
4. **Kredensial di dalam repo.** `scripts/smoke_settings.py` menuliskan email
   pilot beserta password apa adanya dan menyasar port 8010 — port server
   produksi di mesin ini. Kredensial kini wajib dari environment, tanpa nilai
   bawaan.
5. **Duplikat test.** `tests/Feature/Livewire/Public/IndustryListTest.php` adalah
   himpunan bagian dari `tests/Feature/Public/IndustryListTest.php`; dihapus.

Sekalian ditutup: `DashboardComposer` memakai `InvalidArgumentException` **tanpa
meng-import-nya** di dua cabang fail-closed yang tidak pernah diuji, jadi data
runtime yang menyimpang dari preset akan memunculkan "kelas tidak ditemukan"
alih-alih pesan fail-closed — tepat di jalur yang seharusnya menjelaskan masalah.

Catatan positif dari review: pin `DATA_SOURCE=json` di `phpunit.xml` membuat suite
**deterministik tanpa menyetel environment lebih dulu**. Diverifikasi dengan
menjalankan suite tanpa variabel itu.

**Paritas MySQL (T-21b) diverifikasi ulang: `DONE`, commit `5595738`.**
Verifikasi awal T-21b dilakukan sebelum Fase 8/9, jadi `customer_invoices`,
`cash_entries`, tiga entitas akuntansi, unique index baru, dan
`approval_tickets.operation_id` belum pernah diuji di MySQL sama sekali.

- Diuji pada **MySQL 8.4.3**: seluruh migration jalan, kolom uang menyimpan sen
  secara utuh — termasuk `1234567890123.45` yang di SQLite kembali sebagai
  `1234567890123.40` — dan unique ter-scope company yang ditambahkan lewat
  `Schema::table()` benar-benar ditegakkan.
- **Kesimpulan presisi:** batas sen yang tercatat sejak T-42 memang milik SQLite,
  bukan schema. Dikarakterisasi eksplisit di test, bukan disembunyikan.
- Koneksi `mysql_parity` dipisah dari `mysql` supaya pemeriksaan ini tidak pernah
  menyentuh basis data aplikasi; basis data ujinya dibuat sendiri bila belum ada.
  Test melewati dirinya bila MySQL tidak tersedia, jadi mesin dan CI tanpa MySQL
  tetap hijau. `migrate:fresh` sekali per kelas (16s, bukan 50s).

**Gate penutup:** `DATA_SOURCE=json php artisan test` **1.118 passed / 5.474
assertions, 0 gagal**; `vendor/bin/pint --test` **PASS 516 berkas**;
`migrate:fresh --seed --force` OK; `npm run build` PASS.

**Klien node Hermes nyata: `DONE`, commit `90a42e9`.** `App\Services\HermesNodeClient`
sebelumnya **selalu melempar** "not configured for actual delivery in this
environment" — jadi T-49, T-51, dan efek workflow `notify_owner_wa` tidak punya
implementasi pengiriman sama sekali, hanya mock.

- Rantai fail-closed: company ada → profil `primary` yang **melayani company itu
  lewat pivot** (bukan sekadar satu pemilik) → profil berstatus siap → node
  `active` → rahasia node diselesaikan dari **referensi** lewat config (basis data
  tetap bebas kredensial; galat menyebut nama referensi, tidak pernah nilainya) →
  respons harus 2xx **dan** mengonfirmasi terkirim.
- Pemeriksaan badan respons itu yang mencegah pengingat tercatat "terkirim"
  padahal node gagal, lalu tidak pernah dicoba ulang.
- `bos:hermes-ping` memeriksa node tanpa mengirim pesan apa pun. **Diverifikasi
  terhadap Hermes lokal di `127.0.0.1:9119` — menjawab HTTP 200.**
- 11 test baru dipalsukan pada lapisan HTTP (`Http::fake`), bukan dengan
  memalsukan kelasnya sendiri, karena justru kelas itulah yang diuji.

**Dua temuan baru yang dicatat, bukan ditebak:**

1. Ada **dua tipe bernama `HermesNodeClient`**: `App\Contracts` (tidak ter-scope,
   untuk dunning platform D-23, implementasinya fake yang hanya menulis log) dan
   `App\Services` (ter-scope company, yang nyata). Memakai yang pertama untuk
   pesan tenant adalah persis kesalahan yang dilarang D-63 — nomor dikirimi pesan
   tanpa memeriksa profil company mana pun, dan fake-nya mengembalikan `true`
   tanpa mengirim apa pun. Keduanya kini saling merujuk di docblock; penyeragaman
   nama layak jadi task sendiri karena menyentuh jalur billing.
2. Status profil Hermes dipakai dengan **tiga kata untuk keadaan siap yang sama**:
   `paired` (`CleanupExpiredTrials`), `connected` (factory), `active`. Tidak ada
   yang memvalidasinya. Daftar status siap dibuat permisif dan hanya `unpaired`
   yang ditolak — menebak satu kata yang "benar" justru bisa mematikan pengiriman
   yang sah.

**Yang tetap terbuka dan hanya Bos yang bisa membukanya:** remote git untuk push,
pengiriman tagihan langsung ke nomor pelanggan (menyentuh persetujuan pihak ketiga
serta reputasi nomor WA tenant — keputusan bisnis, bukan task), dan **node
WhatsApp sungguhan**. Yang berjalan di PC ini adalah Hermes **agent** (WebUI pada
`9119` plus gateway chat-nya); ia tidak punya endpoint kirim WhatsApp — sudah
diperiksa pada daftar rute `hermes-webui`. Kanal WA (NalarPesan) tidak berjalan di
mesin ini, jadi belum ada satu pesan pun yang benar-benar terkirim. Begitu kontrak
endpoint node diketahui, `HERMES_SEND_PATH` dan satu baris di `hermes_nodes` sudah
cukup — tanpa perubahan kode.

## Fase 10 — Kanal WhatsApp Hermes, White-Label, & Skill Bisnis (BARU, sebagian jalan)

Antrean T-59..T-78 ditulis di `EXECUTION_PLAN.md` §Fase 10 beserta D-68..D-71 di
`00-DECISIONS.md`. **Tiga task selesai; sisanya terhalang hal yang tidak bisa
diselesaikan dari dalam repo ini.**

**T-60 `DONE` — D-68..D-71 dicatat.** White-label wajib di permukaan percakapan
(dev internal dikecualikan); skill di Hermes tanpa aturan bisnis dan hanya lewat
TenantBot API; transport WhatsApp sebagai data per company dengan jalur resmi
**hanya untuk CS**; Struktur B untuk WABA (nomor klien, portfolio kita,
pembayaran lewat kita) beserta tiga konsekuensi yang diterima sadar: risiko
kredit, status kita sebagai pemroses data, dan portabilitas keluar yang sulit.
Sekalian: klausa (d) D-67 yang salah **dicabut di tempat** supaya dokumen
tie-breaker tidak memuat dua pernyataan yang bertabrakan.

**T-59 `DONE` — koreksi steering.** `AGENTS.md`, `CLAUDE.md`, `HERMES.md`, dan
D-67 diperbaiki. Klaim lama "Hermes di PC ini tidak punya endpoint kirim
WhatsApp" **salah**: hermes-webui memang tidak punya, tetapi **Hermes agent
punya** kanal Baileys dan `whatsapp_cloud`. Akar masalahnya dicatat sebagai
aturan proses, bukan hanya diperbaiki: `grep_search` tidak menjangkau luar
workspace dan mengembalikan "no matches" tanpa peringatan, jadi kesimpulan
"tidak ada" tentang repo lain wajib datang dari membuka berkas.

**T-66 + T-67 `DONE` — `docs/HERMES_NODE_CONTRACT.md` diganti dari asumsi menjadi
fakta.** Dua asumsi besar batal:

1. **`api_server` bukan API pengiriman.** Ia API kompatibel OpenAI untuk
   *mengobrol dengan agent*: `/v1/chat/completions`, `/v1/responses`, `/v1/runs`
   (+ SSE events, approval, stop), `/api/sessions/*`, `/health`. Auth
   `API_SERVER_KEY`, port bawaan 8642, multi-profil lewat prefiks `/p/<profil>/`
   bila `gateway.multiplex_profiles` aktif. **Nol** endpoint kirim, pairing, atau
   QR. Bentuk `POST /api/wa/send` yang dipakai `HermesNodeClient` karena itu tidak
   cocok dengan apa pun yang ada di Hermes hari ini.
2. **`whatsapp_cloud` tidak bisa diarahkan ke kirimdev.**
   `GRAPH_API_BASE = "https://graph.facebook.com"` adalah konstanta modul dan
   daftar env var-nya tidak punya override base URL.

Temuan positif: prefiks `/p/<profil>/` adalah cara mengalamatkan profil per
tenant lewat satu listener — persis yang dibutuhkan model "satu tenant = satu
profil", dan tidak perlu dibangun.

**Empat prasyarat sisi Hermes (H-01..H-04)** kini tercatat di `EXECUTION_PLAN.md`:
endpoint kirim pesan (memblokir T-69, T-71), endpoint sesi WA + QR (memblokir
T-72), endpoint pairing pengguna (memblokir T-77 — logikanya sudah lengkap di
`PairingStore`, hanya pembungkus HTTP yang belum ada), dan override base URL
Cloud API (memblokir jalur resmi T-71). Semuanya di repo `hermes-agent`.

**Tiga keputusan Bos yang menghalangi sisanya:** Q-11 (H-01..H-04 ditambal lokal
atau diusulkan upstream), Q-12 (jalur resmi opsi A/B/C — opsi B mengubah
arsitektur karena melahirkan otak kedua), Q-10 (portfolio WABA di kirimdev, yang
menentukan D-71 bisa dijalankan).

**Yang tidak bisa dikerjakan dari mode ini dan alasannya:** T-61 butuh scan QR
fisik dan menyunting berkas di luar workspace; task kode wajib TDD sedangkan
eksekusi perintah (`php artisan test`, Pint, build, git) tidak tersedia di sesi
ini — menulis kode tanpa bisa menjalankan 1.131 test yang ada bertentangan dengan
HERMES.md §Verification.

**Amandemen D-70 (Bos): jalur resmi dikerjakan manual, penyembunyian kirimdev
dibatalkan.** Ini menyederhanakan, bukan sekadar menunda. Karena manual, jalur
resmi memakai **Meta Cloud API langsung** — dan adaptor `whatsapp_cloud` Hermes
sudah lengkap untuk itu (outbound Graph API, webhook verify-token, HMAC
`X-Hub-Signature-256`, proteksi replay `wamid`, media, jendela 24 jam + fallback
template). `GRAPH_API_BASE = "https://graph.facebook.com"` yang tadinya penghalang
**justru sudah benar**. Akibatnya: **H-04 keluar dari jalur kritis**; **T-71 tidak
diperlukan** karena transport adalah konfigurasi Hermes per profil dan tidak
terlihat kode kita; **T-73 ditunda** karena kanal ditentukan kita, bukan dipilih
tenant; **T-74 + T-75 ditunda**. Penggantinya satu task ringan: **T-79 runbook
manual WABA resmi** (dokumen, nol kode). kirimdev ditunda menjadi keputusan
tersendiri, dan nanti pertimbangannya adalah kenyamanan onboarding, bukan
kemampuan mengirim.

**T-79 `DONE` — `docs/RUNBOOK_WABA_MANUAL.md`.** Prosedur manual jalur resmi per
tenant: prasyarat Meta (System User token permanen dengan
`whatsapp_business_messaging` + `whatsapp_business_management`, App Secret, verify
token), env per profil di `profiles/<tenant>/.env` karena adaptor membaca lewat
secret scope per profil, pendaftaran webhook, dan **tujuh langkah verifikasi yang
bisa dijalankan orang lain**. Langkah yang paling tidak boleh dilewati: §4.4 —
POST dengan `X-Hub-Signature-256` sembarang **harus** ditolak, karena kalau lolos
siapa pun bisa menyuntikkan pesan palsu ke bot tenant. Juga dicatat: reverse proxy
tidak boleh mengubah badan permintaan karena HMAC diverifikasi atas raw body.
Dua hal belum terpenuhi dan tertulis di §9 runbook: demo (belum ada tenant yang
benar-benar dipasang) dan **perilaku bentrok port `8090` antar profil** — bawaannya
sama untuk semua profil, jadi tenant **kedua** akan bertabrakan; tenant pertama
tidak terpengaruh.

**Risiko yang tetap hidup dan tercatat sebagai Q-13:**
Struktur B menaruh tagihan Meta di pihak kita sejak pesan pertama, sedangkan
penagihan manual tidak punya penjaga teknis — hanya disiplin; ambang kapan meter
wajib mendarat belum ditentukan.

**Gelombang 1 SELESAI — empat task, semuanya Laravel murni.**

- **T-70 `41f3b77`** super admin bisa **mendaftarkan** node, bukan hanya
  menyunting. Jalan buntu nyata ditutup: `saveNode()` dulu berhenti bila
  `editingNodeId` kosong, sehingga tabel `hermes_nodes` yang kosong **tidak bisa
  diisi dari UI sama sekali** — seluruh integrasi Hermes bergantung pada satu
  baris yang tidak punya cara dibuat. Ditambah tombol periksa kesehatan (node
  tidak terjangkau dilaporkan gagal, **tidak** menjatuhkan halaman) dan pemisahan
  profil platform dari profil tenant. 8 test.
- **T-68 `816a734`** profil milik platform melayani nol company. Kelonggaran
  `billing_addon_id` **sempit** — hanya bila `is_platform_provided`, karena
  memalsukan baris billing adalah jebakan; add-on **tenant** tanpa billing tetap
  ditolak dan D-37 tidak dilonggarkan. `HermesNodeClient` menolak profil platform
  di lajur tenant walau ditautkan lewat pivot, dan `WhatsAppSenderIdentity`
  fail-closed padanya. 6 test.
- **T-63a `bee5986`** lima penjaga arsitektur atas permukaan tulis bot. **Hijau
  sejak awal**, jadi characterization test — tidak ada cacat yang ditemukan, dan
  itu dicatat apa adanya. Satu penjaganya langsung terbukti berguna: ia mewajibkan
  rute bot baru terdaftar di `ROUTE_TOOL_MAP`, dan T-65 memang menambah rute.
- **T-65** standar dokumen per tenant + `GET /api/bot/tenant/document-standards`,
  disimpan di `module_settings`. Bawaannya **didokumentasikan** karena tenant baru
  belum menyetel apa pun dan itu keadaan mayoritas; standar tersimpan ditimpakan
  di atas bawaan per jenis dokumen sehingga menyetel `tone` saja tidak
  menghilangkan `sections`. Baca-saja bagi bot, dan test menguncinya. 6 test.

**Gate gelombang 1:** `DATA_SOURCE=json php artisan test` **1.156 passed / 5.582
assertions, 0 gagal**; `vendor/bin/pint --test` **PASS 526 berkas**;
`migrate:fresh --seed --force` OK; `npm run build` PASS.

**Urutan kerja disusun ulang atas mandat Bos "yang berat belakangan"**
(`EXECUTION_PLAN.md` §Urutan pengerjaan). Empat gelombang: (1) ringan dan Laravel
murni — **T-68, T-70, T-65, T-63a**, nol prasyarat luar; (2) ringan tapi butuh Bos
— T-61; (3) menunggu H-01..H-04 + Q-11/Q-12 — T-69, T-72, T-77, T-71, T-62,
T-63b, T-64; (4) berat + menunggu Q-10 dan nomor WA kedua — T-74, T-75, T-73,
T-76. Tiga ketergantungan yang terlalu ketat dilepas supaya gelombang 1 benar-benar
bisa jalan: T-70 tidak lagi menunggu T-69 (`bos:hermes-ping` sudah ada sejak
`90a42e9`), T-65 dibalik mendahului T-64, dan T-63 dipecah menjadi T-63a (sisi
kita, ringan) + T-63b (profil Hermes, gelombang 3). Gelombang 2 kini T-61 + T-79;
gelombang 4 berisi yang ditunda: T-71, T-73, T-74, T-75, T-76.

**Jalur klien pertama: T-69, T-80, T-81 + runbook.** Mandat Bos berubah arah dari
"selesaikan antrean" menjadi "nomor CS untuk proyek ini, lalu coba satu klien",
dan ketiga task ini adalah yang benar-benar menghalangi itu.

- **T-69 `72923c2`** kontrak bridge diganti dari asumsi menjadi fakta, dibaca
  langsung dari `scripts/whatsapp-bridge/bridge.js`: `POST /send` dengan
  `{chatId, message}` dan `chatId` berformat `628xxx@s.whatsapp.net`, `GET /health`,
  port 3000, **tanpa autentikasi apa pun**. Dua konsekuensi yang tidak bisa
  ditawar: (a) `/health` menjawab **HTTP 200 walau WhatsApp terputus**, jadi
  memeriksa kode HTTP saja akan melaporkan bridge mati sebagai sehat — `ping()`
  sekarang membaca field `status`; (b) ketiadaan autentikasi harus **dinyatakan**
  lewat `api_secret_reference = 'none'` dan hanya sah untuk loopback. Memaksa
  operator mengisi referensi rahasia palsu supaya lolos aturan kita berarti
  menyimpan kebohongan di basis data dan menyembunyikan bahwa jalur itu tidak
  terlindungi. **H-01 dicoret**: endpoint kirim ternyata sudah ada sejak awal.
- **T-80 `272ff0d`** jalur pembuatan profil. `ensurePrimaryProfile()` ada sejak
  lama tetapi **tidak pernah dipanggil dari mana pun**, jadi tidak ada satu cara
  pun membuat baris `hermes_profiles` — dan tanpa baris itu `HermesNodeClient`
  selalu menolak, bot tenant tidak punya token, bot CS platform tidak bisa
  didaftarkan. Bentuk cacat yang sama dengan `hermes_nodes` sebelum T-70: skema
  siap, jalurnya tidak ada. `bos:hermes-profile` melayani dua jalur (tenant dan
  platform), idempoten, menghormati `max_capacity`, dan mencetak token sekali di
  terminal tanpa menulisnya ke log. 11 test. Satu test yang saya rencanakan
  dibatalkan: "company tanpa owner" mustahil karena `companies.owner_user_id`
  NOT NULL — alasannya dicatat di berkas test supaya tidak dicoba lagi.
- **T-81** dua utang runbook dibayar. **(a)** Alamat bridge menjadi milik profil
  (`hermes_profiles.api_url`, nullable, jatuh kembali ke node). Satu bridge = satu
  nomor = satu port; selama alamat hanya ada di `hermes_nodes`, setiap nomor
  memaksa satu baris node dan `max_capacity` jadi dekorasi. **(b)** Status profil
  diturunkan dari bridge lewat `ProfileStatusRefresher` — `bos:hermes-profile-status`,
  tombol di `/admin/hermes-nodes`, dan penjadwal tiap sepuluh menit. Status yang
  diketik tangan bisa berbohong: profil bertanda `paired` padahal nomornya lepas
  membuat setiap pengiriman dicoba lalu gagal tanpa petunjuk. Sisi tulis hanya
  mengenal `paired`/`unpaired`; sisi baca tetap permisif supaya baris lama tidak
  mendadak berhenti mengirim. Profil tanpa alamat tidak dihubungi sama sekali dan
  dilaporkan dilewati, bukan menjatuhkan pemeriksaan seluruh armada. 11 test.
- **Runbook `cf97bed`** `docs/RUNBOOK_KLIEN_PERTAMA.md`: urutan konkret untuk nomor
  CS dan klien pertama, beserta §5 "yang belum diverifikasi" dan §6 utang. Runbook
  itu **belum pernah dijalankan dari awal sampai akhir** — ia disusun dari kontrak
  yang dibaca di kode, dan klien pertama adalah ujinya.

**Gate:** `DATA_SOURCE=json php artisan test` **1.181 passed / 5.643 assertions,
0 gagal**; `vendor/bin/pint --test` **PASS 532 berkas**; `migrate:fresh --seed
--force` OK; `npm run build` PASS.

**Yang masih menghalangi klien pertama, dan bukan pekerjaan kode:** allowlist
Hermes. Siapa yang boleh bicara dengan bot ditentukan dua gerbang bertumpuk —
allowlist di sisi Hermes lalu otorisasi kita (D-66) — dan gerbang pertama hanya
bisa disetel dari sisi Hermes. Untuk klien pertama berarti **hanya nomor owner
yang jalan**; mengundang staf lewat T-51 baru berguna setelah H-03. Pada instalasi
sekarang allowlist kosong, jadi bridge jatuh ke mode self-chat dan menolak semua
orang dengan `self_chat_mode_rejects_non_self`.

## READY Berikutnya
Fase 8 (T-41..T-47) dan Fase 9 (T-48..T-58) selesai penuh; Fase 6b/katalog D-56
sudah dibangun seluruhnya (T-28..T-35). Sisa temuan pasca-Fase 9 juga sudah
ditutup. Fase 10: T-59, T-60, T-63a, T-65, T-66, T-67, T-68, T-69, T-70, T-79,
T-80, T-81 selesai; sisanya terhalang H-02/H-03 dan keputusan Bos (Q-09, Q-10,
Q-11, Q-13).

Satu-satunya task `READY` yang tersisa di `EXECUTION_PLAN.md` adalah **T-36 NLU
Intent Router (WA)** di §Fase 7 (D-60 masih *draft*). T-37..T-40 `BLOCKED` di
belakangnya. Catatan yang relevan: D-66 menjadikan T-37 (pemilih konteks company)
prasyarat untuk melonggarkan fail-closed nomor WA yang terdaftar di lebih dari
satu usaha.

Menunggu Bos, bukan menunggu pekerjaan:

- Aktivasi Hermes produksi untuk T-49/T-51/T-58 — `HUMAN:SECRET`. Sampai itu ada,
  ketiganya hanya terbukti lewat `FakeHermesNodeClient`.
- Remote git belum dikonfigurasi; seluruh pekerjaan masih commit lokal.
- Pengiriman tagihan langsung ke nomor pelanggan (UR-05) — menyentuh persetujuan
  pihak ketiga dan reputasi nomor WA tenant.
- Satu putaran manual dari HP pada tenant nyata. Ini sisa risiko terbesar:
  1.118 test hijau tidak pernah salah menekan tombol di layar sempit.

## Riset control plane Hermes → D-72 + T-82..T-87 (2026-09-22, sesi Kiro)

**Tidak ada kode aplikasi yang disentuh.** Yang berubah hanya dokumen:
`docs/HERMES_NODE_CONTRACT.md`, `docs/00-DECISIONS.md` (D-72 + amandemen Q-11),
`docs/EXECUTION_PLAN.md` (H-01..H-03 dicabut, H-05 baru, gelombang 5 T-82..T-87,
status T-69/T-72/T-77 disesuaikan), dan berkas ini. Karena tidak ada PHP/Blade/JS
yang berubah, gate `php artisan test` / `pint --test` / `npm run build` **tidak
dijalankan** — dinyatakan apa adanya, bukan diklaim PASS.

**Pemicu:** Bos menanyakan apakah bisa membuat "MCP ke Hermes" supaya setting
dilakukan dari software kita, lalu menegaskan arahnya: profil Hermes muncul dan
bisa dipantau di software kita, **mesin tetap Hermes**.

**Tiga temuan yang membatalkan catatan sebelumnya**, semuanya dari membuka berkas
di `%LOCALAPPDATA%\hermes\hermes-agent`, bukan dari grep:

1. **Bridge Baileys adalah servis HTTP.** `scripts/whatsapp-bridge/bridge.js`
   (Express) `app.listen(PORT, '127.0.0.1')`, default 3000, dengan `POST /send`,
   `/send-media`, `/send-poll`, `/send-location`, `/edit`, `/typing`, `/read`,
   `GET /messages`, `/chat/:id`, `/health`. §1.3 kontrak sebelumnya menulis
   "bukan servis HTTP" — salah, dan kode kita sendiri (T-69, T-81) sudah
   membuktikannya salah. Mode `--pair-only` memang tidak menyalakan server HTTP;
   itu kemungkinan asal salah bacanya.
2. **Dashboard Hermes adalah control plane HTTP yang lengkap.**
   `hermes_cli/web_server.py` menyajikan profil (CRUD + SOUL + model), device
   pairing + **QR** (`/api/messaging/whatsapp/onboarding/*`, membalas
   `qr_payload`), user pairing (`/api/pairing*`), config/env, kendali gateway,
   dan pemantauan — semuanya profile-scoped. Jadi **H-01, H-02, dan H-03 dicabut**;
   T-72 dan T-77 tidak lagi terhalang ketiadaan endpoint.
3. **MCP tidak menjawab kebutuhan setting.** `mcp_serve.py` memuat 10 tool dan
   semuanya messaging (nol tool profil/config/pairing), transport **stdio saja**.
   Sebaliknya Hermes **mendukung MCP server remote** (`--url` + bearer + filter
   `tools.include`, didaftarkan per profil lewat `POST /api/mcp/servers`), yang
   berguna untuk arah sebaliknya — kita provider, Hermes klien (T-87).

**Satu prasyarat baru yang nyata (H-05):** `_require_token` hanya menerima
`_SESSION_TOKEN` ephemeral yang disuntik ke HTML SPA atau menyerah pada gate
cookie. Seam bearer generik ada di `hermes_cli/dashboard_auth/token_auth.py`,
tetapi satu-satunya rute terdaftar adalah `/api/gateway/drain`
(`plugins/dashboard_auth/drain/__init__.py:280`). Kerja sisi Hermes = satu plugin
`dashboard_auth`, **bukan** patch core.

**Risiko yang dicatat, bukan diselesaikan:**

- Port dashboard yang sama menyajikan `/api/fs/write-text`, `/api/files/upload`,
  `/api/tools/terminal/*`, `/api/git/*`, `/api/profiles/{name}/open-terminal`.
  Token dashboard = eksekusi kode di host Hermes. D-72 menjawabnya dengan
  daftar-putih path + penjaga arsitektur T-86; tanpa itu D-69 menjadi hiasan.
- Rute `/api/*` dashboard **tidak berversi**. Pembaruan Hermes bisa
  memindahkannya; dikurung dalam satu kelas klien (T-82) + test kontrak.
- Inventaris rute yang saya susun **belum tentu lengkap**: `web_routers/sessions.py`
  memakai router bernama (`list_router`, `search_router`, `manage_router`)
  sehingga lolos dari pola pencarian dekorator yang pertama saya pakai. Klaim
  "tidak ada endpoint kirim di dashboard" sudah diulang dengan pencarian yang
  lebih luas di seluruh pohon, tetapi klaim "tidak ada" tentang repo lain
  sebaiknya tetap diperlakukan sebagai dapat dibantah.
- `POST /api/messaging/platforms/{id}/test` belum dibaca — jangan diandalkan
  sebagai jalur kirim sampai seseorang membukanya.

**Next READY:** T-82 (serial, menyentuh migration). Setelahnya T-83 + T-84 boleh
paralel, T-86 menyusul. Menunggu Bos: Q-11 (H-05 ditambal lokal atau diusulkan
upstream) dan `HUMAN:APPROVAL` dependency untuk T-87.

### Susulan sesi yang sama: topologi armada → Q-14 + T-88/T-89

Bos mengarahkan "satu Hermes akhirnya mengelola banyak Hermes, dan pusat ini kita
kendalikan". Pembacaan kode memberi satu koreksi arah:

- **Orkestrator armada Hermes sudah ada, tetapi pusatnya Nous.**
  `hermes_cli/gateway_enroll.py` mendaftarkan gateway ke **relay connector**
  dengan token `portal.nousresearch.com`; tenant otoritatif diturunkan dari **org
  Nous** lewat `GET /api/oauth/account`, "never from anything the gateway
  asserts"; instalasi managed **tidak** self-enroll karena **NAS** yang mint
  secret dan menstempelnya ke env container; `hermes_cli/dashboard_register.py`
  mendaftarkan klien OAuth dashboard ke portal yang sama. Dokumentasinya sendiri
  menyebut skema auth relay **EXPERIMENTAL, dapat berubah tanpa siklus
  deprecation**. Jadi memakai jalur itu memindahkan pusat ke Nous, bukan ke kita,
  dan menyentuh D-68.
- **Yang benar-benar berarti "kita kendalikan"** adalah dua mekanisme lain:
  **managed scope** (`hermes_cli/managed_scope.py` — `$HERMES_MANAGED_DIR` atau
  `/etc/hermes`, menang atas config pengguna per-leaf-key) dan **profile
  distribution** (`hermes_cli/profile_distribution.py` — profil sebagai repo git,
  `install`/`update`, memori dan kredensial lokal tidak disentuh). Keduanya cocok
  dengan cara kita sudah bekerja (D-69: artefak produk hidup di repo).
- **Dua peringatan yang tidak boleh hilang.** Managed scope v1 "enforcement is
  filesystem permissions only", POSIX-first — di Windows ia **konvensi, bukan
  penjagaan**; dan pembacaannya **fail-open**, berkas rusak dicatat keras lalu
  tidak diterapkan, sehingga kebijakan bisa berhenti berlaku tanpa disadari.
  Karena itu T-88 mewajibkan pemeriksaan "nilai yang kita paksa masih aktif?"
  dari sisi kita, bukan keyakinan bahwa berkasnya ada.
- **`max_capacity` = 100 adalah angka yang ditebak.** Kenyataannya satu nomor WA
  butuh satu port bridge sendiri dan satu proses gateway melayani banyak profil,
  jadi satu proses jatuh menjatuhkan semua tenant di host itu. T-89 mengubahnya
  menjadi angka berdasar, sekalian menjadikan alokasi port bridge sumber daya
  yang dikelola — sekarang port dipilih tangan dan dua profil berport sama akan
  saling menendang tanpa pesan jelas.

Tercatat: **Q-14** (topologi armada — keputusan Bos), **T-88** `READY` (riset
managed scope + distribution, tidak memblokir T-83/T-84), **T-89** `BLOCKED` T-84.
Kontrak Hermes dapat **§7 Armada** (tiga mekanisme dibedakan) dan §8 bertambah
tiga butir "belum diuji". Masih dokumen saja — tidak ada kode aplikasi tersentuh,
gate test/pint/build tidak dijalankan.

### Batas kerja Hermes + kesiapan banyak host → T-105, T-106 (sesi yang sama)

Bos khawatir Hermes punya batas kerja sehingga kita harus siap menangani banyak
gateway. **Kekhawatiran itu berdasar, dan angkanya bukan satu.** Dari
`hermes_cli/config_defaults.py` dan `gateway/platforms/api_server.py`:

- `max_live_sessions: 16` — batas LRU **lunak** atas sesi in-memory; yang digusur
  hanya sesi **detached** dan dipulihkan dari disk, jadi bukan kehilangan data,
  tetapi ia langit-langit "berapa yang benar-benar bekerja serentak".
- `max_concurrent_sessions: None` — knop batas global tersedia, **bawaannya tanpa
  batas**.
- `gateway.api_server.max_concurrent_runs` — run agent yang melebihi dijawab
  respons "concurrency limited".
- `agent.restart_drain_timeout: 0`, dengan kontrak tertulis di kodenya: "if you
  restart the gateway, in-flight work stops" (plus `gateway_timeout: 1800`,
  `max_turns: 500`).

**Ketiga batas pertama berlaku per proses gateway.** Dan `/api/status` melaporkan
`gateway_mode` = `multiplex` (satu proses melayani banyak profil, lewat
`profiles_to_serve(True)`) / `multiple` (gateway per profil) / `single` / `none`,
beserta `{"profile","ports","served_profiles"}` per gateway hidup — jadi mode dan
port bisa **dibaca**, tidak perlu dicatat tangan. Rekomendasi yang dicatat di §7.1
kontrak: tenant berbayar memakai `multiple`, `multiplex` untuk internal/demo.

**Bahaya yang belum diverifikasi dan wajib diuji sebelum dua tenant berbagi satu
proses:** `POST /api/messaging/whatsapp/onboarding/{id}/apply` me-restart gateway
sendiri. Bila di mode `multiplex` restart itu menjatuhkan seluruh
`served_profiles`, maka memasangkan WhatsApp satu tenant memutus pekerjaan tenant
lain. T-89 wajib menjawabnya.

**Tiga cacat di sisi kita yang terbukti dari kode, bukan dugaan:**

1. **Tidak ada penempatan node.** `HermesProfileProvisioner::ensurePrimaryProfile()`
   — satu-satunya jalur yang dipakai onboarding — **tidak pernah mengisi
   `node_id`** (kolomnya nullable), sehingga profil lahir tanpa node dan
   `HermesNodeClient` menolak dengan "Profil Hermes belum ditempatkan pada node".
   Tenant baru akan mendapat bot yang tidak pernah bisa mengirim, dan gejalanya
   muncul jauh dari penyebabnya.
2. **`active_profiles` hanya bisa naik.** `HermesProvisionProfile` memanggil
   `increment('active_profiles')`; tidak ada satu pun jalur yang menurunkannya.
   Penghitung yang menyimpang ke atas akan melaporkan node penuh padahal lowong —
   tepat pada angka yang dipakai untuk memutuskan penempatan.
3. **Port bridge tidak dimodelkan.** `hermes_profiles.api_url` menyimpan
   `host:port` sebagai string bebas; dua profil pada satu node bisa memakai port
   yang sama dan saling menendang tanpa pesan jelas. Webhook Cloud API bawaannya
   8090, jadi tabrakan dengan platform lain juga mungkin.

Sekalian: `hermes_nodes.status` hanya `active|maintenance|down` — tidak ada
`draining`, padahal Hermes punya `POST /api/gateway/drain`. Tanpa keadaan itu,
memindahkan tenant dari satu host berarti mematikannya mendadak.

Tercatat: **T-105** `READY` (penempatan + alokasi port + kapasitas yang tidak bisa
berbohong + status `draining`; **serial**, menyentuh migration), **T-106**
`BLOCKED` T-105/T-88 (runbook + supervisi host Hermes kedua, termasuk prosedur
drain dan bagian "belum diverifikasi"). T-89 diperketat: alokasi port dipindah ke
T-105, dan uji isolasi `multiplex` ditambahkan sebagai pertanyaan yang harus
dijawab tegas. Masih dokumen saja — tidak ada kode aplikasi tersentuh.

**Writer lain aktif bersamaan, dan tulisannya belum di-commit.** Saat sesi ini
menulis, `docs/EXECUTION_PLAN.md` + `docs/00-DECISIONS.md` sudah memuat **D-73 +
gelombang 6 (T-90..T-104)** milik writer lain, sementara `git show HEAD` pada
kedua berkas itu **tidak memuat D-72 maupun D-73** — artinya pekerjaan dua writer
sekarang **bercampur sebagai perubahan belum ter-commit di berkas yang sama**.
Konsekuensi operasional yang harus dipatuhi: **jangan commit keempat berkas dokumen
ini tanpa koordinasi**, karena commit apa pun dari salah satu writer akan
menyertakan tulisan writer lain yang belum direview — pola yang sama dengan
`b0ef5b0`. Berkas baru `docs/worker-reports/PROMPT_QA_FASE10_HERMES.md` juga bukan
tulisan sesi ini; dicatat, tidak disentuh (HERMES §Writing Rules). Karena itu nomor task sesi ini digeser ke **T-105/T-106**; nomor
T-90/T-91 yang sempat saya tulis lebih dulu sudah dibetulkan di tempat. Ada satu
catatan untuk peninjau: D-73 memakai lajur `resmi` untuk pesan transaksional,
sedangkan §7.1 kontrak Hermes menyimpulkan tenant berbayar sebaiknya memakai
gateway **per profil** — keduanya tidak bertabrakan, tetapi keputusan kapasitas
(Q-14) sekarang menyentuh dua gelombang sekaligus.

## Riset kanal WhatsApp resmi → D-73 + T-90..T-104 (2026-09-22, sesi Kiro)

**Tidak ada kode aplikasi yang disentuh.** Yang berubah hanya dokumen:
`docs/00-DECISIONS.md` (D-73 baru, D-71 dicabut sebagian, Q-10 ditutup, Q-13
diturunkan), `docs/EXECUTION_PLAN.md` (state T-71/T-73 → `DIGANTIKAN`,
T-74/T-75 → `DITURUNKAN`, dua butir "Catatan risiko Fase 10" dikoreksi,
gelombang 6 T-90..T-104 ditulis), dan berkas ini. Karena tidak ada PHP/Blade/JS
yang berubah, gate `php artisan test` / `pint --test` / `npm run build` **tidak
dijalankan** — dinyatakan apa adanya, bukan diklaim PASS. Belum di-commit.

**Pemicu:** Bos meminta riset kirimdev — bisakah kita jadi reseller, memakai
platform mereka di tempat kita, atau jadi white-label. Lalu, setelah temuannya
keluar, Bos mengambil empat keputusan yang menjadi D-73.

**Temuan yang menentukan, dari dokumentasi Meta dan kirimdev (bukan dari
halaman pemasaran):**

1. **Meta mengenal tiga tingkat otorisasi**, dan itulah yang menentukan
   segalanya, bukan pilihan vendornya: *Tech Provider* (akses API + hosting
   Embedded Signup), *Tech Partner* (+ badge), *Solution Partner* (ex-BSP, +
   **lini kredit Meta**). Dokumentasi Embedded Signup menyatakan pembagian lini
   kredit **"hanya Mitra Solusi"**. Kirimdev menyatakan dirinya **bukan BSP**.
   Jadi **D-71 tidak dapat dijalankan lewat penyedia kelas Tech Provider mana
   pun** — bukan kekurangan produk kirimdev, tapi sifat tingkatannya.
2. **Aset Embedded Signup selalu milik klien.** *"Pelanggan bisnis… memiliki
   semua aset WhatsApp mereka… juga memiliki akses penuh ke Pengelola WhatsApp.
   Ingat, Anda tidak bisa membatasi akses ini dengan cara apa pun."* Rumusan
   D-71 "WABA di bawah portfolio kita" karena itu **mustahil**, bukan sekadar
   sulit. Yang bisa berpindah hanyalah lini kreditnya. **Q-10 tertutup.**
3. **HMAC kirimdev tidak sepadan dengan Meta** — menutup butir terbuka §7
   `HERMES_NODE_CONTRACT.md`. `X-Kirim-Signature: t=…,v1=…` atas
   `"{t}.{raw_body}"` gaya Stripe, versus `X-Hub-Signature-256: sha256=<hex>`
   atas raw body. Konsekuensinya rekomendasi lama **opsi A** (tambal
   `GRAPH_API_BASE`) hanya menyelesaikan outbound dan membuat inbound ditolak.
   Ada **opsi D** yang lebih baik bila kirimdev dipakai: plugin resmi
   `kirimdev-hermes`, yang menangani tanda tangannya sendiri.
4. **Ekonomi Solution Partner tidak cocok untuk pasar kita.** 360dialog (Meta
   Solution Partner, program ISV/reseller eksplisit, zero message markup)
   €250–1.000/bulan + €15–49 per channel ≈ **Rp870rb/tenant/bulan** di 10
   tenant, dibanding ≈ **Rp20rb/tenant** di kelas Tech Provider. Selisih ~40x.
   Itu yang membuat Bos memilih Struktur A.
5. **Kebutuhan yang belum tercakup task mana pun:** di luar jendela 24 jam Cloud
   API **wajib template disetujui Meta per WABA**. Baileys mengirim teks bebas,
   jadi kebutuhan ini hanya muncul di lajur resmi — tanpa T-97, pengingat piutang
   akan gagal di produksi.

**Empat keputusan Bos (D-73):** nomor wajib milik klien atas dasar privasi;
tagihan Meta ke klien (**Struktur A**, membalik D-71); tujuan akhir **kita
sendiri jadi Tech Provider langsung ke Meta** sehingga kode wajib netral
penyedia; dan **layar pilihan kanal ada** dengan kanal belum siap berlabel
**"Sedang disiapkan"** — mencabut amandemen D-70 butir (e).

**Koreksi atas kesalahan saya sendiri di sesi ini, dicatat supaya tidak
terulang.** Rencana gelombang 6 versi pertama disusun di atas premis "jalur kirim
mati, empat fitur transaksional hanya terbukti lewat fake, menunggu H-01".
Premis itu **sudah tidak berlaku** dan saya baru menemukannya saat hendak menulis
ke antrean: H-01 dicabut, `config/hermes.php` menunjuk `POST /send` bridge yang
nyata, T-69/T-80/T-81 sudah mendarat (HEAD `a9747d9`). Penyebabnya: pembacaan
`config/hermes.php` dan `HermesNodeClient.php` di awal sesi **basi** dibanding
keadaan repo, dan saya sempat menyusun rencana di atasnya. Saya juga sempat
merencanakan nomor `D-72` dan `T-80..T-94` yang **semuanya sudah terpakai**.
Pelajarannya, dan ini pelengkap catatan "grep tidak menjangkau luar workspace"
yang sudah ada dua kali: **sebelum menulis ke antrean, ID tertinggi dan keadaan
kode wajib diverifikasi ke `git log` + berkasnya**, bukan ke ingatan sesi.

**Risiko yang dicatat, bukan diselesaikan:**

- **Biaya pindah penyedia tumbuh seiring jumlah tenant**, karena setiap migrasi
  menuntut Embedded Signup diulang per tenant **dengan OTP** — sesi berdampingan,
  tidak bisa diotomasi. Ini risiko utama gelombang ini, ditekan oleh seam netral
  penyedia (T-93) dan batas jumlah tenant (T-101). Secara hukum kita tidak
  terkurung; secara operasional kita terkurung.
- **Penularan pelanggaran.** ToS penyedia menaruh pelanggaran AUP end-customer
  pada Operator Platform, dan memberi mereka hak menangguhkan akun kita. Satu
  tenant yang blast promosi bisa menjatuhkan **semua** tenant lajur resmi. T-98
  menutup sisi kita; blast radius akunnya tetap milik kita.
- **Plafon laju dan kuota onboarding mungkin dipakai bersama.** Write per menit
  dibagi seluruh tenant dan dilarang ditambah dengan mencetak kunci API baru.
  Bila app Facebook yang menjalankan Embedded Signup milik penyedia, batas Meta
  10/200 klien baru per 7 hari kemungkinan dibagi dengan operator lain — belum
  terverifikasi, pertanyaan pertama di T-92.
- **Batas tanggung jawab penyedia ≈ 3 bulan langganan.** Untuk paket Rp199rb itu
  sekitar Rp600rb, dan tanpa jaminan uptime. Untuk kanal yang menjadi inti
  produk, itu seluruh recourse — salah satu alasan tujuan akhirnya Tech Provider
  sendiri.
- **Tenggat keras: Embedded Signup v2/v3 disetop 15 Oktober 2026.** Berlaku untuk
  jalur langsung maupun perantara.
- **Umur penyedia.** Changelog kirimdev dimulai 26 Mei 2026 ("initial release"),
  ToS terakhir diperbarui 5 Juni 2026, dan harganya jauh di bawah kelas partner
  internasional. Dari luar tidak bisa dibedakan antara strategi pasar lokal yang
  agresif dan harga yang belum menemukan biayanya.

**Next `READY`:** T-91 (koreksi kontrak) dan T-92 (checklist Tech Provider +
tujuh pertanyaan penyedia) — keduanya dokumen. Lalu T-93 di sisi kode, yang
seluruhnya dapat di-TDD dengan `Http::fake()` tanpa akun penyedia. **Butir terbuka terakhir sudah ditutup Bos** dan menjadi
D-73 butir 9: lajur `resmi` hanya untuk **bot CS dan hal yang berhubungan dengan
publik**, dan aturannya dirumuskan berbasis **penerima**, bukan fitur — penerima
di luar organisasi tenant boleh `resmi`, penerima internal tetap `bawaan`. Jadi
**pengingat piutang masuk**, sedangkan **undangan staf, `notify_owner_wa`, dan
dunning langganan platform tidak**. Alasan dirumuskan berbasis penerima: daftar
putih per fitur akan usang saat fitur baru ditambah, sementara pertanyaan "apakah
penerima ini orang luar" tetap terjawab untuk fitur yang belum ditulis. T-93 dan
T-95 sudah disesuaikan: kelas penerima eksplisit dengan default `internal`, dan
`resmi` **menolak** penerima internal walau company itu sudah mengaktifkannya.

### Antrean control plane: status nyata setelah writer lain bekerja (2026-09-22)

Tulisan sesi riset (D-72, §7 kontrak, gelombang 5) **sudah masuk `main`** — tetapi
bukan sebagai commit sendiri: ia tersapu ke dalam commit writer lain `3ac7e5f` dan
`73725f8` karena keduanya menyetel `git add` pada berkas dokumen yang sama.
Dicatat apa adanya, bukan diperbaiki dengan menulis ulang riwayat.

Sejak itu writer lain **sudah mengeksekusi dua baris antrean itu**:

- **T-82 `DONE 75a2cd7`** — `HermesControlPlaneClient`, `ControlPlanePaths`, tujuh
  exception spesifik (401/404/410/429/5xx/"node tanpa control plane" tidak lagi
  menjadi satu galat tak dikenal), migration `control_url` +
  `control_secret_reference`, `bos:hermes-control-ping`, form node di
  `/admin/hermes-nodes`, `ControlPlaneClientTest`, laporan
  `docs/worker-reports/T-82_CONTROL_PLANE.md`.
- **T-86 `DONE 4f49670`** — `ControlPlaneBoundaryTest` +
  `ControlPlanePathCoverageTest` (termasuk penjaga "setiap path pada daftar-putih
  wajib dipakai satu test", supaya daftar itu tidak menumpuk izin mati) dan
  prosedur rotasi rahasia di `RUNBOOK_RUNTIME_SERVICE.md`.

Kedua baris itu masih tertulis `READY` di `EXECUTION_PLAN.md` padahal kodenya
sudah mendarat; statusnya dibetulkan di sesi ini beserta rujukan commit.

**Satu cacat rujukan yang saya perbaiki, milik tulisan saya sendiri:** state T-106
menyebut ketergantungan "T-90". Nomor itu sejak `73725f8` menjadi milik gelombang 6
(D-73), sehingga rujukannya berubah arti menjadi task yang sama sekali lain.
Dependensinya adalah penempatan node = **T-105**.

**Gate yang saya jalankan sendiri di `main` (bukan laporan delegasi):**
`php artisan test` **1.226 passed / 5.771 assertions**, `vendor/bin/pint --test`
**PASS 551 berkas**. `npm run build` tidak dijalankan — tidak ada perubahan
Blade/CSS/JS di sesi ini.

**Sisa yang bukan tulisan sesi ini dan tidak disentuh:**
`storage/app/json/1/workflow_log.json` termodifikasi, plus `qa_test_output.txt`,
`qa_pint_output.txt`, `qa_build_output.txt` sebagai berkas baru di akar repo —
artefak putaran QA writer lain. Dicatat, tidak ikut di-commit.

**Next `READY` di jalur control plane:** T-83 (cermin profil + rekonsiliasi yatim),
T-84 (pemantauan armada), T-88 (uji managed scope + distribution), T-105
(penempatan node + alokasi port; **serial**, menyentuh migration). Masih menunggu
Bos: **Q-11** (H-05 ditambal lokal atau upstream), **Q-14** (topologi armada),
dan `HUMAN:APPROVAL` dependency untuk T-87.

## Sesi maraton control plane + hardening bug scout (SELESAI sampai batas non-manusia)

Delapan task diselesaikan tanpa intervensi Bos, berhenti tepat di batas
`HUMAN:SECRET` / `HUMAN:DECISION` / repo Hermes. Urut commit:

- **`0888cf1` T-83 + T-84** — cermin profil node (read-through, rekonsiliasi
  yatim/hilang, tanpa penghapusan otomatis) + pemantauan kanal armada
  (`bos:hermes-fleet-status`, keadaan mati disalin dari `_PLATFORM_DEAD_STATES`
  Hermes, tidak menulis `hermes_profiles.status`). Laporan
  `docs/worker-reports/T-83_T-84_MIRROR_AND_FLEET.md`.
- **`abb6756` BS-01** — settlement gateway mengaktifkan membership (dulu hanya
  `paid` + kredit token; pelanggan Midtrans membayar tetapi layanannya tidak
  menyala).
- **`e84b321` BS-03** — `AuthenticateTenantBot` menegakkan D-66 (`wa_is_verified`
  + normalisasi `08`↔`628`); normalisasi dikonsolidasi ke `User::normalizeWaNumber()`.
- **`da84cad` BS-02** — fallback token `?? 1` dihapus (fail-closed 422); grant
  diisi sejak invoice dibuat (subscription dari kuota paket, topup dari mapping
  `billing.topup.tokens_per_rupiah`).
- **`51e6fe9` T-105** — penempatan node otomatis (`NodePlacement`, kunci baris,
  menolak bila tak ada node layak), port bridge sebagai sumber daya
  (`bridge_port` unique per node, rentang dikonfigurasi), status `draining`
  (melayani profil lama, menolak penempatan baru).
- **`4d23fa2` T-88** — riset managed scope + distribution, diuji pada instalasi
  ini: managed scope resolve di Windows, menang per-leaf, **fail-open** saat
  berkas rusak; kontrak `profile update` dibaca dari CLI Hermes. `HERMES_NODE_CONTRACT`
  §7.4 + §7.4a.

Sebelumnya di sesi yang sama: **`0ca4e7f`** menutup lima temuan QA independen
Fase 10 (idempotensi profil platform, penghitung `active_profiles` yang
menyimpang, redirect Guzzle yang melewati aturan loopback, penjaga arsitektur
yang bocor) dan mengamankan token bot dengan hash SHA-256 (keputusan Bos).

**Gate akhir sesi:** `DATA_SOURCE=json php artisan test` **1.290 passed / 5.954
assertions, 0 gagal**; `vendor/bin/pint --test` **PASS 562 berkas**;
`migrate --force` DONE (3 migration baru: control plane, pencabutan token
plaintext, penempatan). `npm run build` tidak dijalankan pada T-88/T-105 (tidak
ada perubahan Blade/CSS/JS di dua task itu; view T-83/T-84 dibangun di `0888cf1`).

**Catatan status tabel `EXECUTION_PLAN.md`:** T-69, T-83, T-84, T-88, T-105,
BS-01, BS-02, BS-03 masih tertulis `READY` di tabel karena penandaan tabel
dikerjakan sesi antrean, bukan sesi writer ini — laporan per task ada di
`docs/worker-reports/`. Semua sudah `DONE` di kode.

**Yang tersisa di antrean, semuanya di luar jangkauan autopilot:**
- **Gelombang 6 (T-90..T-104, D-73 kanal WhatsApp resmi Meta)** — mandat Bos
  "yang berat belakangan, coming soon". Tidak dikerjakan.
- **T-36** (NLU intent router) — D-60 masih *draft*.
- **T-61** (bot dev WA) / **nomor CS** — `HUMAN:SECRET`, scan QR.
- **T-62/T-63b/T-64** — rantai SOUL, butuh bot hidup untuk verifikasi.
- **T-72/T-77/T-85** dan seluruh **aksi tulis control plane** — menunggu **H-05**
  (auth server-ke-server dashboard API) di repo `hermes-agent`.
- **T-87** — `HUMAN:APPROVAL` dependency.
- Keputusan Bos yang membuka lanjutan: **Q-11** (H-05 tambal lokal vs upstream),
  **Q-14** (topologi armada), ambang mode pajak D-74 bila menyentuh onboarding.

**Utang yang dicatat, bukan disembunyikan:** suite test **tidak aman dijalankan
dua proses bersamaan** — berbagi `storage/framework/testing` menghasilkan
`UnableToWriteFile`/galat identitas usaha yang berubah antar-jalankan dan
menyesatkan. Muncul berkali-kali sesi ini; setiap kali hijau saat diisolasi.
Gate yang hasilnya berubah antar-jalankan tidak bisa dipakai sebagai gate —
layak jadi task tersendiri (isolasi disk test per proses).

## Fase 11 — Paritas Modul: tiga lajur mandiri mendarat (MP-06, MP-04, MP-11)

Dikerjakan paralel sementara lajur Hermes aktif di `main`, masing-masing di
worktree sendiri, bukti di `docs/worker-reports/MP-0*.md` + `MP-11.md`. Ketiganya
dipilih justru karena file target-nya **disjoint** dari lajur Hermes (tidak ada
migration, dependency, route, registry, atau berkas Hermes yang disentuh) — jadi
tidak menunggu MP-00 dan tidak bertabrakan. Merged serial ke `main` setelah lajur
Hermes berhenti (`8ff1051`), `merge-tree` bersih untuk ketiganya, gate penuh
dijalankan **setelah tiap merge**.

- **MP-06 Buku Kas** (`3e69c82` → merge). Dua cacat yang muncul saat menulis
  test, bukan dugaan: kolom "Keterangan" menampilkan `entry_date` karena
  `SchemaPresenter::titleField()` jatuh ke kolom string pertama, sehingga
  keterangan operator tak pernah terlihat; dan baris berarah di luar `in|out`
  dihitung sebagai uang masuk (cabang lama hanya cek `=== 'out'`), membuat Buku
  Kas berselisih dengan Laporan Keuangan. Total kini lewat `CashFlowCalculator`
  (sen integer, kontrak yang sama dengan Dashboard) dan baris tertolak
  ditampilkan bertanda, tidak disembunyikan. Ditambah penyaring
  periode/arah/relasi (relasi dari `references.assignable`, label lewat
  `term()`), pilihan periode dari data. **Saldo tidak pernah ditulis ulang oleh
  penyaring**; kolom saldo berjalan wajib cocok dengan agregat atau layar
  berhenti. 16 test (6 lama tak disentuh).
- **MP-04 Laporan Keuangan** (`884726b` → merge). Layar sebelumnya nol `wire:*`.
  Ditambah pemilihan periode (opsi dari bulan yang punya transaksi, terbaru
  dulu) dan komposisi uang keluar per kategori dengan persentase. Saldo berjalan
  tetap kumulatif dan saldo kas header selalu posisi seluruh riwayat — menyaring
  hanya menyembunyikan baris (pola sama dengan MP-06). Entri tanpa kategori
  dikelompokkan bukan dibuang sehingga komposisi = total keluar. Basis kas D-62
  tetap, tanpa nama kategori di kode. 17 test (10 lama tak disentuh). Sisa: "5
  item terlaris" mockup menuntut `order_lines` (di luar basis kas), ditunda.
- **MP-11 Istilah di Pengaturan** (`be0215c` → merge). **Koreksi premis:**
  kustomisasi istilah sudah ada di tab Fitur Bisnis (`updateTerminology`,
  `TerminologyResolver` bervalidasi), jadi task dipersempit ke dua yang benar
  hilang: `resetTerminology()` (menghapus override, bukan menimpa nilai preset,
  supaya tetap ikut preset bila istilahnya berganti; owner-only) dan batas 40
  karakter yang ditegakkan server-side, bukan hanya `maxlength` HTML. 16 test
  (10 lama tak disentuh).

**Gate penutup di `main` pasca tiga merge:** `php vendor/phpunit/phpunit/phpunit`
**1.311 passed / 6.019 assertions** (3 PHPUnit Notices pre-existing),
`vendor/bin/pint --test` **PASS 562 file**, `npm run build` **PASS**.

**MP-05 sengaja dilewati, bukan dilupakan.** Premis rencananya ("board check-in
belum bisa dijalankan") keliru: `BoardScreen` sengaja baca-saja (docblock-nya
eksplisit — transisi tahap tinggal di pola `pipeline`), dan
`BookingsDepositCollect`/`Settle` **tidak terdaftar** di `WorkflowEngine` maupun
didekларasikan preset mana pun. Menuliskan aksi tulis di board menduplikasi jalur
transisi yang kode itu larang. **Perlu dirumuskan ulang Bos** sebelum dikerjakan.

**Temuan di luar lingkup (dicatat, TIDAK diperbaiki):**
`JsonCompanySettingsStore::update()` menulis lewat `Storage::disk()->path()` +
`fopen`/`Filesystem::replace` native, melewati `Storage::fake`. Kelemahan isolasi
test yang sudah ada; tidak mengubah perilaku produksi. (Gejala: `php artisan
test` yang terputus bisa meninggalkan `settings.json` nyata; `phpunit` bersih
hijau.) Beririsan dengan task isolasi disk test per proses yang sudah dicatat di
STATUS ini.

**Sisa Fase 11 (belum bisa jalan otonom):** MP-00 (serial, seam menu/route —
harus langsung di `main`), lalu MP-01/02/03/08/09/10 yang bergantung padanya, dan
MP-12 (barrier penutup). MP-02 (integritas POS: stok + jurnal saat checkout)
adalah yang paling berdampak dan sebaiknya didahulukan setelah MP-00 mendarat.

## Empat keputusan Bos + D-76 lead capture (2026-09-22, lanjutan sesi)

Bos menjawab empat keputusan yang menghalangi:

1. **Q-11 → D-75: H-05 tambal lokal.** Temuan saat mencatat: kerangka
   `dashboard_auth` **sudah lengkap** di Hermes (`register_token_route`,
   provider stacking, fail-closed) — H-05 tinggal satu plugin (provider bearer
   + registrasi path daftar-putih), bukan sistem auth baru. Di repo
   `hermes-agent`, di luar workspace ini.
2. **Q-14 → ditunda** sampai host pertama penuh; tidak menghalangi klien
   pertama.
3. **Gelombang 6 (T-90..T-104)** tetap "coming soon", tidak dibuka.
4. **Q-09 → D-76: bot CS boleh mencatat prospek**, layaknya CS; eksekusi lanjut
   masuk antrean. **Dikerjakan sesi ini** (`0e0abb4`): `POST /api/bot/master/leads`,
   tabel `leads` minimal (nomor, pesan, sumber, status), `updateOrCreate` per
   nomor supaya satu prospek satu kartu. Tidak menyentuh `support_tickets`
   maupun company.

**Gate akhir setelah D-76:** `DATA_SOURCE=json php artisan test` **1.317 passed
/ 6.037 assertions, 0 gagal**; `vendor/bin/pint --test` **PASS 565 berkas**;
migration `create_leads_table` DONE.

**Insiden proses yang perlu diketahui sesi berikutnya:**

- **Tiga worktree paralel aktif** di `agentic-bos-ux-a`, `agentic-bos-ux-b`,
  `agentic-bos-worker-ui`. Mereka menjelaskan sebagian kontensi test yang
  tercatat sesi-sesi sebelumnya.
- **`public/build/manifest.json` bukan per-worktree** — build salah satu
  worktree bisa menimpa manifest yang dipakai worktree lain, menghasilkan
  `Unable to locate file in Vite manifest` di **puluhan** test sekaligus
  (terjadi sesi ini: 68 gagal, semuanya 500 karena manifest, nol berkaitan
  dengan kode yang sedang dikerjakan). **Diagnosis, bukan tebakan:** dikonfirmasi
  dengan `npm run build` ulang lalu re-run — langsung hijau. Kalau gate tiba-tiba
  merah masif dengan pesan Vite manifest, jalankan `npm run build` dulu sebelum
  mencurigai kode.
- **Dua kali kena artefak editing tool** yang menyisipkan 2-3 karakter sampah
  tepat sebelum `<?php` pada berkas yang baru disunting (`Settings.php`,
  `MasterBotController.php`), membuat parse error yang mengaku "1 gagal"
  padahal seluruh file testnya tidak jalan. Terdeteksi lewat `php -l` per
  berkas yang baru diubah — kebiasaan yang layak dipertahankan setelah setiap
  `str_replace` pada baris pembuka berkas.
- Utang lama tetap berlaku: suite tidak aman dijalankan berbarengan dengan
  proses `php artisan test` lain (beda soal dari manifest di atas — ini soal
  `storage/framework/testing` yang dibagi).

**Antrean sekarang benar-benar habis untuk pekerjaan tanpa Bos.** Sisa yang ada:
gelombang 6 (ditunda), H-05 (repo lain, keputusan sudah dijawab tapi
eksekusinya manual), `HUMAN:SECRET` nomor CS, dan Q-14 (menunggu data kapasitas
nyata).

### Visi "bot yang ingat dan bertumbuh" → T-107 + Q-16 (2026-09-22, sesi Kiro)

Bos menyatakan inti produknya: WhatsApp yang ingat terus, bertumbuh, semakin dipakai
semakin mengenal usahanya — dan tenant merasakan hal yang sama. Sekalian bertanya
apakah perlu memasang plugin memori dan Obsidian untuk klien. Pembacaan instalasi
Hermes memberi jawaban yang cukup tegas untuk dijadikan task.

**Fakta yang diverifikasi di host ini:**

- Ingatan Hermes ada **per profil** — setiap `profiles/<nama>/` memuat `memories/`,
  `sessions/`, `state.db`, `SOUL.md`, `skills/`, `.env`, `config.yaml` sendiri. Jadi
  isolasi per tenant memang terpenuhi secara struktur. (34 profil hidup di host ini.)
- Bentuk ingatannya **berkas markdown**: `memories/MEMORY.md` + `USER.md`.
- Dan ia **beranggaran, bukan bertumbuh**: `memory_char_limit: 2200` (±800 token),
  `user_char_limit: 1375`, `memory_enabled: true`, `user_profile_enabled: true`,
  ditambah `mem_trim` serta curator aktif (`interval_hours: 168`,
  `stale_after_days: 30`, `archive_after_days: 90`). Artinya "semakin mengenal"
  secara bawaan berwujud **ringkasan yang ditulis ulang**, bukan akumulasi.
- Ingatan panjang tersedia sebagai plugin — `hindsight`, `mem0`, `supermemory`,
  `honcho`, `byterover`, `holographic`, `openviking`, `retaindb` — tetapi **belum ada
  yang dikonfigurasi** (`provider: ''`).
- `skills.external_dirs: []` masih kosong, jadi mekanisme D-69 (skill produk di luar
  jangkauan curator) memang belum dipakai.

**Kesimpulan yang dicatat: Hermes ingat *caranya*, kita ingat *faktanya*.** Fakta
bisnis tidak boleh bersandar pada ingatan yang dibatasi karakter, dipangkas berkala,
tidak terlihat di web, tidak bisa diaudit, dan hilang saat profil dibangun ulang.
Karena itu **T-107** `READY`: entitas `business_notes` (catatan + SOP) yang dibaca
bot lewat pencarian berbatas dan hanya bisa **ditambah** bot — menimpa tulisan
manusia tetap owner-only lewat web. Kosakata tool diperluas `read_knowledge` +
`write_knowledge`, profil `addon`/CS **tidak** mendapat keduanya, kuota + batas
panjang ditegakkan sebelum tulis, catatan sensitif tunduk `AiDataSharingPolicy` dan
tidak pernah dijawab pada japri staf. Serial karena menyentuh migration.

**Dua jawaban atas pertanyaan Bos, dicatat supaya tidak ditanyakan ulang:**

- **Obsidian tidak dipasang untuk klien.** Obsidian membaca folder markdown lokal;
  memasangnya per tenant berarti ada berkas di host dan bot butuh tool berkas —
  tepat yang dilarang D-69. Yang diadopsi bentuknya, bukan aplikasinya: markdown
  tertaut, ditambah ekspor `.md` ber-front-matter sehingga tenant pemakai Obsidian
  bisa membuka vault-nya sendiri.
- **Memory provider belum diadopsi**, dicatat sebagai **Q-16** dengan tiga
  konsekuensi yang harus Bos timbang: kedaulatan data (mayoritas provider adalah
  layanan eksternal → percakapan pelanggan tenant keluar dari server kita,
  menyentuh D-71), perubahan dependensi + biaya per pemakaian (`HUMAN:COST`), dan
  batas bahwa provider ingatan tidak boleh menjadi alasan menyimpan fakta bisnis di
  luar DB kita. Rekomendasi bila kelak "ya": self-hosted, per profil, hanya untuk
  konteks percakapan.

**Temuan keamanan yang muncul tidak sengaja saat membaca config Hermes:**
`config.yaml` memuat **API key provider dalam bentuk plaintext** pada kunci
`delegation.api_key`, dan nilainya sempat tercetak di keluaran terminal sesi ini.
**Disarankan dirotasi.** Ini juga bukti konkret untuk catatan risiko Fase 10 yang
sudah ada: aplikasi web tidak boleh diberi akses ke home Hermes, dan itu bukan
kekhawatiran teoretis.

Perubahan sesi ini dokumen saja (`00-DECISIONS.md`, `EXECUTION_PLAN.md`, berkas ini);
tidak ada kode aplikasi tersentuh, jadi gate test/pint/build tidak dijalankan.
**Writer lain sedang aktif** pada `app/Livewire/Settings.php`,
`app/Models/BusinessIdentity.php`, `app/Services/BusinessIdentityStore.php`, dan test
fiskalnya — berkas itu tidak disentuh dan tidak ikut di-commit.
