# Agentic BOS Autopilot Status

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

**UR-06  observability, backup, dan recovery drill  `DONE` lokal (2026-09-21, commit `b913c42`); aktivasi schedule backup/health produksi = `HUMAN:DEPLOY`.**

1. `bos:backup-mysql` (BosBackupMysql): mysqldump `--single-transaction` -> enkripsi AES-256-CBC + PBKDF2 60k iter, output `storage/app/backups/mysql-<ts>.sql.enc`, retensi rotasi `--keep=7`. **Fail-closed terbukti**: tanpa/pendek `BACKUP_ENCRYPTION_KEY` exit 1; tidak ada jalur plain-text. Tes nyata: backup DB produksi 197.744 bytes, dekripsi roundtrip OK (65 tabel, data utuh).
2. `bos:health` (BosHealth): 5 check  database (ping+jumlah tabel), queue (pending+umur tertua, FAIL bila >15 menit = worker macet), scheduler (log fresh), failed-jobs (=0), web (HTTP probe). Exit 1 bila gagal = sinyal alert tiap 5 menit via schedule. Tes nyata produksi: **5/5 lulus**. Health check menangkap 1 job stale dev (InfrastructureProbeJob 93 menit)  dibersihkan.
3. Schedule (`routes/console.php`): `bos:backup-mysql` dailyAt 02:30 + `bos:health` everyFiveMinutes.
4. **Restore drill SUKSES** (policy: backup tak pernah di-restore = belum valid): DB disposable `agentic_bos_restore_drill`  dekripsi + restore 5,3 detik; bukti `users=2 companies=1 sessions=57 contacts=2 access_logs=3` (transaksi referensi UAT UR-03 ada), **bcrypt login pilot verify OK**; drill DB dihapus setelah selesai.
5. Runbook `docs/RUNBOOK_BACKUP_RECOVERY.md`: health checklist, backup manual + verifikasi, prosedur drill lengkap, recovery nyata, incident response berurutan, **RPO 24 jam / RTO 5,3 detik** tercatat.
6. Test `BackupAndHealthCommandsTest` 5 test (fail-closed kunci kosong/pendek, mysqldump unavailable, health exit 1 web unreachable, skip-web).
7. Gate: `DATA_SOURCE=json php artisan test` **870 passed / 4.412 assertions**; Pint PASS 439 files; tidak ada perubahan Blade/CSS/JS (build tidak diwajibkan; terakhir PASS UR-03).
8. Catatan: `.env.production` masih `BACKUP_ENCRYPTION_KEY` kosong  **Bos harus set kunci (min 32 char) sebelum aktivasi produksi** (gate `HUMAN:SECRET` implisit); scheduler produksi PM2 harus reload `routes/console.php` baru saat restart stack berikutnya.

**Next READY: UR-02 local implementation (service terkelola web+queue+scheduler) tanpa restart produksi; aktivasi = `HUMAN:DEPLOY`.**

- Next READY pra-gate: UR-02 local implementation (service terkelola web/queue/scheduler, log terpisah, idempotent test job) boleh dikerjakan tanpa menyentuh produksi.


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

## Security Hardening Sprint S2 — DONE

**State:** `DONE` — test fail-closed untuk D-08 (token), D-49 (dunning ladder), D-52 (gerbang paket) ditambahkan dan merged ke `main`.

**Tests added:**
- `tests/Feature/PaymentWebhookSecurityTest.php` — 7 test: validasi signature webhook, beda settlement/capture, idempotensi replay, anti double-credit, status fraud menolak pembayaran.
- `tests/Feature/DunningLadderFailClosedTest.php` — 9 test: transisi tangga dunning (H+0 ai_suspended → H+7 read_only → H+30 frozen), gerbang kapabilitas menghormati status dunning, restore kembali ke active.

**Evidence (Hermes, bukan self-report runner):** focused 16 passed/48 assertions; full suite **696 passed / 3,763 assertions**; Pint PASS; build PASS.

**Catatan tentang klaim "FeatureResolver fail-open":** OpenCode menandai `(! $isPlanActive || in_array(...))` sebagai celah D-52. Setelah Hermes membaca kode: ini **perilaku yang disengaja dan benar** — fitur preset berlaku penuh hanya saat company **tidak punya membership/paket** (keadaan demo/setup), dan `PlanCapabilityGate` memang fail-closed (`[]`) saat membership hilang. Mengubahnya jadi fail-closed global akan mematikan seluruh demo/test. Apakah company tanpa paket harus dibatasi adalah **keputusan produk untuk Bos**, bukan bug untuk diperbaiki sepihak.

## READY Berikutnya
Tidak ada task READY tersisa di `EXECUTION_PLAN.md` §Fase 6b. Katalog D-56
sudah dibangun seluruhnya (T-28..T-35). Langkah lanjutan menunggu instruksi
Bos (add-on baru, ekspansi preset, atau prioritas lain).
