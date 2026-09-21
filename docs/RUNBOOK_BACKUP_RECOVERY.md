# Runbook  Backup & Recovery (UR-06)

## Komponen

| Komponen | Artefak |
|---|---|
| Backup harian terenkripsi | `php artisan bos:backup-mysql` (schedule 02:30) |
| Health check | `php artisan bos:health` (schedule tiap 5 menit) |
| Output backup | `storage/app/backups/mysql-YYYYMMDD-HHMMSS.sql.enc` |
| Retensi | default 7 file (rotasi otomatis) |
| Enkripsi | AES-256-CBC + PBKDF2 60.000 iter, kunci = `BACKUP_ENCRYPTION_KEY` (min 32 char) |

Fail-closed: tanpa kunci yang valid, `bos:backup-mysql` menolak berjalan
(exit 1). Tidak ada jalur backup plain-text.

## Health checklist (`bos:health`)

1. `database`  ping + jumlah tabel.
2. `queue`  jumlah pending + umur job tertua (FAIL bila menunggu > 15 menit = worker macet).
3. `scheduler`  log scheduler fresh (< 1 jam).
4. `failed-jobs`  total harus 0.
5. `web`  HTTP probe `/login` (200/302).

Exit code 1 bila ada check gagal  scheduler log baris FAIL tiap 5 menit =
sinyal insiden. Manual: `APP_ENV=production php artisan bos:health`.

## Backup manual

```powershell
# shell produksi (PM2 env), kunci sudah di .env.production
php artisan bos:backup-mysql
```

Jalankan sekali sebelum maintenance/rollback. Verifikasi cepat:

```bash
openssl enc -d -aes-256-cbc -pbkdf2 -iter 60000 \
  -pass pass:$BACKUP_ENCRYPTION_KEY \
  -in storage/app/backups/mysql-<ts>.sql.enc | head -3
# harus terlihat header "-- MySQL dump 10.13"
```

## Restore drill (wajib per backup-verification policy)

Restore ke database disposable, JANGAN langsung ke `agentic_bos_production`.

```bash
MYSQL="D:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe"
KEY="<BACKUP_ENCRYPTION_KEY>"
ENC="storage/app/backups/mysql-<ts>.sql.enc"

"$MYSQL" -u root -e "DROP DATABASE IF EXISTS agentic_bos_restore_drill;
  CREATE DATABASE agentic_bos_restore_drill CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
openssl enc -d -aes-256-cbc -pbkdf2 -iter 60000 -pass pass:$KEY -in $ENC -out cache/drill.sql
"$MYSQL" -u root agentic_bos_restore_drill < cache/drill.sql
# bukti: row counts, user pilot, transaksi referensi
"$MYSQL" -u root -N -e "SELECT COUNT(*) FROM agentic_bos_restore_drill.users;
  SELECT COUNT(*) FROM agentic_bos_restore_drill.contacts;"
php -r "echo password_verify('<password pilot>', (new PDO('mysql:host=127.0.0.1;dbname=agentic_bos_restore_drill','root',''))->query(\"SELECT password FROM users WHERE email='pilot@nalar.army'\")->fetchColumn()) ? 'OK' : 'MISMATCH';"
rm cache/drill.sql
"$MYSQL" -u root -e "DROP DATABASE agentic_bos_restore_drill;"
```

Backup yang belum pernah direstore dianggap belum valid (policy UR-06).

## Drill terakhir (bukti)

- Tanggal: 2026-09-21, file `mysql-20260921-114308.sql.enc` (197.744 bytes).
- Hasil: dekripsi OK; restore OK; `users=2`, `companies=1`, `sessions=57`,
  `contacts=2` (termasuk transaksi referensi UAT UR-03), `access_logs=3`;
  bcrypt login pilot verify **OK**.
- **RTO (dropcreate-dekripsi-restore-verifikasi): 5,3 detik.**
- **RPO: maksimum 24 jam** (backup harian 02:30). Turunkan ke tiap 6 jam
  dengan `->everySixHours()` bila data pilot sudah nyata.

## Recovery nyata (kegagalan DB produksi)

1. Stop stack: `Restart-Service` tidak  pakai `Stop-Service PM2-AgenticBOS`.
2. Rename DB rusak: `RENAME TABLE` per tabel atau `ALTER DATABASE ... RENAME`
   (MySQL 8: rename DB tidak didukung  dump restore ke DB baru + ganti
   `DB_DATABASE`).
3. Restore backup terbaru ke `agentic_bos_restore` lalu verifikasi drill di atas.
4. Ganti `DB_DATABASE=agentic_bos_restore` di `.env.production`, start service.
5. Catat insiden: waktu, penyebab, RTO aktual, data hilang (RPO aktual).

## Incident response (urutan)

1. `APP_ENV=production php artisan bos:health`  identifikasi check FAIL.
2. `php artisan queue:failed` + `storage/logs/pm2-*-error.log` tail.
3. Bila web down: `pm2 restart agentic-bos-production` (shell admin).
4. Bila queue macet (oldest_wait tinggi): restart `agentic-bos-queue`, cek
   `failed_jobs`, jalankan ulang job idempoten.
5. Bila DB rusak: jalankan Recovery nyata di atas.
6. Semua kejadian P0/P1 dicatat ke STATUS; finding masuk triage, bukan
   langsung task baru.
