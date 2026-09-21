# PLAN — Product Usage Readiness Agentic BOS

**Tanggal audit:** 2026-09-21  
**Tujuan:** memindahkan Agentic BOS dari *feature-complete/demo-ready* menjadi produk yang benar-benar dapat dipakai satu pelanggan pilot secara aman, ditagih, dipulihkan, dan dioperasikan.

## 1. Verdict

Kode memiliki cakupan fitur dan regression test yang kuat, tetapi **belum production-usable untuk pelanggan nyata**. Hambatan utamanya bukan kekurangan modul, melainkan aktivasi operasional:

1. MySQL produksi masih kosong: 0 user, 0 platform admin, 0 company, 0 membership plan, 0 membership, 0 Hermes node/profile, 0 invoice, dan 0 token ledger entry.
2. Belum ada bukti UAT end-to-end menggunakan akun dan tenant produksi.
3. Service web aktif, tetapi belum ada proses `queue:work` dan `schedule:work` yang terverifikasi.
4. Siklus komersial (pilih paket → instruksi bayar → konfirmasi admin → membership/token aktif) belum pernah dibuktikan dengan transaksi pilot.
5. Identitas produk WA-first belum dapat dipakai karena belum ada Hermes node/profile produksi dan belum ada acceptance pilot berdasarkan kontrak D-37/D-40/D-55.
6. Banyak writer eksternal aktif; insiden reset `main` sebelumnya membuat single-orchestrator/final-gate menjadi syarat operasional, bukan pilihan.

**Keputusan prioritas:** hentikan ekspansi fitur baru sampai satu *golden path* pelanggan lulus dari registrasi sampai operasi harian, pembayaran, backup/restore, dan dukungan.

## 2. Evidence snapshot

| Area | Bukti aktual | Kesimpulan |
|---|---|---|
| Git | `main` pada `750fd31`; next resmi di `AUTOPILOT_STATUS.md` = QA independen MQ-01 | MQ-01 belum boleh dianggap tutup sebelum QA independen |
| Foreign writer | Codex/OpenCode aktif dengan cwd repo utama; lane `task/bi-a` dan `task/bi-b` ada | Merge dan write ke `main` harus ditahan/serial |
| Runtime web | `PM2-AgenticBOS` RUNNING; `127.0.0.1:8010` = 200 | Upstream hidup |
| Public guard | `https://bos.nalar.army/` = 401 tanpa Basic Auth | Fail-closed sehat |
| Asset | `public/hot` absent | Tidak menunjuk Vite dev server |
| Config efektif | Dengan `APP_ENV=production`: debug false, URL `bos.nalar.army`, MySQL, JSON, queue/cache/session database | `.env.production` termuat benar |
| Schema produksi | Migration status menunjukkan migration terbaru Ran | Schema tersedia; tetap verifikasi penuh saat gate deploy |
| Data produksi | Seluruh data aktivasi/komersial/WA = 0 | Belum ada tenant yang dapat memakai produk |
| Worker | Proses `artisan queue:*` dan `artisan schedule:*` tidak ditemukan | Billing/notification async belum operationally proven |
| Local demo data | SQLite lokal berisi 6 user/5 company; satu akun masih memakai password demo | Data lokal tidak boleh dijadikan template produksi; password demo wajib dibersihkan sebelum membuka akses |
| Test baseline | Status terakhir: MQ-01C1–C5 selesai; full gate terakhir terdokumentasi, tetapi QA independen belum selesai | Re-run final gate setelah konvergensi branch |

## 3. Prinsip eksekusi

- Command Center/Hermes Orchestrator satu-satunya pemilih task dan integrator ke `main`.
- Planner hanya merinci task yang sudah dipilih; QA/Bug Scout hanya mengembalikan finding/candidate task.
- Writer selalu di branch/worktree khusus. Merge, migration, dependency change, dan deploy selalu serial.
- Sebelum write/merge: `git status --short`, `git worktree list`, proses aktif, dan reflog.
- Tidak ada `git add -A`; stage by-path; tidak pernah commit `.env` atau secret.
- D-31 tetap absolut: industri = data, kapabilitas = kode.
- Uang, token, tenant access, dan WA authorization wajib fail-closed serta punya negative test.
- Perluasan WA-first di luar kontrak D-37/D-40/D-55 tetap **review-only** sampai `HUMAN:DECISION`; perubahan arsitektur baru juga memerlukan `HUMAN:ARCHITECTURE`.
- Production data mutation, deploy, secret, biaya, dan pembukaan akses publik tetap membutuhkan gate manusia.

## 4. Queue baru berorientasi penggunaan nyata

| ID | Task | Depends on | Gate | Acceptance utama | State |
|---|---|---|---|---|---|
| **UR-00** | Konvergensi repository dan final QA baseline | — | — | QA independen MQ-01 selesai; branch BI-A/BI-B direview lalu merge/reject serial; foreign artifacts dipisahkan; `php artisan test`, Pint, dan build hijau pada HEAD final; tidak ada writer lain di `main` saat merge | `READY` |
| **UR-01** | Bootstrap identitas produksi secara aman | UR-00 | `HUMAN:DEPLOY`, `HUMAN:SECRET` | Buat tepat 1 platform admin dan 1 owner pilot melalui prosedur idempoten; password acak/dirotasi, tidak muncul di log/docs; admin dan owner memiliki login/otorisasi berbeda; negative test non-admin → `/admin` = 403 | `BLOCKED` gate |
| **UR-02** | Lengkapi topology runtime web + queue + scheduler | UR-00 | local implementation boleh; aktivasi `HUMAN:DEPLOY` | Service web, satu queue worker, dan scheduler berjalan sebagai foreground service terkelola; auto-start/restart diuji; `failed_jobs=0`; job uji diproses tepat sekali; `billing:check-expiring` terjadwal; log path dan restart command terdokumentasi | `READY` setelah UR-00 |
| **UR-03** | Golden-path UAT tenant produksi | UR-01, UR-02 | `HUMAN:DEPLOY` | Owner login → onboarding → pilih preset → dashboard → satu transaksi POS/order → inventory/ledger berubah benar → export berhasil → logout/login ulang; akses tenant lain ditolak; admin impersonation tidak dapat export/mutasi sensitif | `BLOCKED` dependency/gate |
| **UR-04** | Buktikan siklus komersial end-to-end | UR-01, UR-03 | `HUMAN:SECRET`, `HUMAN:DEPLOY`, `HUMAN:COST` bila uang nyata | Seed/config plan produksi; data rekening/QRIS dari secret config; owner memilih paket dan mendapat invoice; hanya admin dapat konfirmasi; replay konfirmasi tidak menggandakan membership/token; status membership, expiry, invoice, dan ledger konsisten; rollback test tersedia | `BLOCKED` gate |
| **UR-05** | Putuskan dan jalankan pilot WA-first minimum | UR-03 | `HUMAN:DECISION`, `HUMAN:SECRET`, `HUMAN:DEPLOY`; `HUMAN:ARCHITECTURE` bila melampaui kontrak terkunci | Bos menyetujui scope pilot berdasarkan D-37/D-40/D-55; satu Hermes node/profile dan satu nomor pilot dipasangkan; inbound owner terautentikasi; satu read action dan satu write action bounded bekerja; tenant/nomor salah ditolak; destructive action tetap butuh approval; token tercatat tepat sekali | `BLOCKED` keputusan |
| **UR-06** | Observability, backup, dan recovery drill | UR-02, UR-03 | `HUMAN:DEPLOY` untuk job produksi | Health checklist web/DB/queue/scheduler; alert untuk HTTP/queue/service; backup MySQL terenkripsi; restore ke DB disposable berhasil; RPO/RTO dicatat; runbook restart, rollback, dan incident response diuji oleh orang selain writer | `BLOCKED` dependency |
| **UR-07** | Pilot operasional 7 hari dengan satu company nyata | UR-03, UR-06; UR-04/UR-05 sesuai scope pilot | `HUMAN:DEPLOY`, `HUMAN:COST` | Tidak ada P0/P1 terbuka; owner menyelesaikan operasi harian tanpa bantuan developer; catat waktu aktivasi, error, tiket support, transaksi, penggunaan WA/token, dan keputusan lanjut/berhenti; finding masuk triage, bukan langsung READY | `BLOCKED` dependency |
| **UR-08** | Go/no-go pembukaan terbatas | UR-07 | `HUMAN:DEPLOY` | Checklist security/backup/payment/support hijau; Basic Auth tetap dipertahankan atau dibuka hanya dengan keputusan eksplisit; rollback tersedia; maksimal cohort kecil, bukan public launch massal | `BLOCKED` dependency |

## 5. Detail acceptance kritis

### UR-00 — Final QA dan pengendalian writer

1. Audit diff `750fd31..HEAD` dan seluruh branch candidate.
2. MQ-01 QA mencakup tenant isolation, exact money values, widget-capability fail-closed, company display name, mobile/a11y.
3. BI-A/BI-B adalah *optional enhancement*, bukan launch blocker. Merge hanya jika test dan scope bersih; selain itu parkir.
4. Setelah setiap merge serial:
   - `php artisan test`
   - `php vendor/bin/pint --test`
   - `npm run build`
5. Pastikan `git status --short` hanya berisi artefak yang dipahami; restore fixture test pada worktree pemiliknya, jangan menghapus file asing.

### UR-01 — Provisioning tanpa kredensial bawaan

- Jangan memakai `DogfoodTenantSeeder` di produksi.
- Buat command/prosedur one-shot idempoten yang meminta secret secara interaktif atau melalui secret store.
- Jangan mencetak password/token.
- Catat hanya ID/role/status, bukan nilai secret.
- Paksa rotasi password pertama atau kirim reset link melalui kanal yang disetujui.

### UR-02 — Runtime

Minimum proses terkelola:

1. web upstream;
2. `php artisan queue:work --sleep=3 --tries=3 --timeout=<bounded>`;
3. `php artisan schedule:work` atau trigger scheduler OS per menit.

Semua proses memakai environment produksi yang sama, cwd absolut, log terpisah, graceful stop, dan restart policy bounded. Jangan mengandalkan terminal interaktif yang kebetulan hidup.

### UR-03 — Golden path

Gunakan satu tenant pilot disposable/terkendali. Evidence wajib berupa timestamp, actor role, URL/action, expected vs actual, dan screenshot hanya bila tidak mengandung secret. Negative path minimal:

- owner A tidak dapat membaca/mengubah company B;
- staff tidak dapat owner-only action;
- platform admin tanpa mode impersonation tidak menjadi tenant user;
- impersonation tidak boleh export atau tindakan sensitif;
- transaksi replay tidak membuat posting ganda;
- session/logout/CSRF tetap fail-closed.

### UR-04 — Uang dan token

Sebelum uang nyata, lakukan nominal kecil atau sandbox yang disetujui. Reconcile empat sumber: invoice, membership, token ledger, dan bukti pembayaran. Setiap perubahan status harus idempoten dan dapat diaudit. Tidak ada kredit token hanya berdasarkan request klien.

### UR-05 — WA-first minimum

Scope pilot harus kecil: identifikasi owner, pilih company aktif, baca ringkasan, dan satu write action bounded. Jangan memperluas otomatisasi WA di luar kontrak D-37/D-40/D-55 sebelum scope pilot disetujui. D-60/T-36 tetap khusus tier gratis dan tidak dipakai sebagai ID pekerjaan WA. Bug Scout tetap read-only dan bukan worker produk.

### UR-06 — Recovery

Restore drill harus memakai database disposable dan membuktikan jumlah row, login pilot, serta satu transaksi referensi. Backup yang belum pernah direstore dianggap belum valid.

## 6. Yang ditunda

- Preset/add-on/BI baru yang tidak diperlukan pilot.
- Public launch massal.
- Otomasi WA penuh, destructive action otonom, atau multi-channel sebelum UR-05 minimum lulus.
- Integrasi payment gateway berbayar bila pembayaran manual sudah cukup untuk pilot.
- Refactor kosmetik tanpa finding P0/P1/P2 yang tervalidasi.

## 7. Urutan eksekusi yang disarankan

```text
UR-00
  ├─ UR-01 ──────────────┐
  └─ UR-02 ──────────────┤
                         └─ UR-03 ─┬─ UR-04
                                  ├─ UR-05 (setelah keputusan Bos)
                                  └─ UR-06
UR-03 + UR-06 + scope komersial/WA terpilih
  └─ UR-07
      └─ UR-08
```

**Next READY tunggal:** `UR-00` — QA independen MQ-01 dan konvergensi branch aktif.  
**Next setelah UR-00:** `UR-01` menunggu gate provisioning; `UR-02` dapat dikerjakan lokal lalu berhenti sebelum aktivasi produksi.

## 8. Definition of “berhasil digunakan”

Agentic BOS baru boleh disebut **berhasil digunakan** bila seluruh kondisi berikut terbukti:

1. satu owner nyata dapat masuk, onboarding, dan menjalankan operasi inti tanpa bantuan developer;
2. data tetap tenant-isolated dan transaksi finansial akurat/idempoten;
3. admin platform dapat membantu tanpa menembus batas tenant;
4. layanan pulih setelah restart dan backup berhasil direstore;
5. jalur bayar dapat mengaktifkan paket tanpa kredit ganda;
6. bila WA masuk scope pilot, pesan owner terautentikasi menghasilkan action dan ledger token yang benar;
7. ada evidence UAT dan runbook, bukan hanya test suite;
8. tidak ada P0/P1 terbuka dan rollback tersedia.
