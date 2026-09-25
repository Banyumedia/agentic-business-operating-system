# Runbook  Runtime Service Agentic BOS (UR-02)

Topologi: satu service Windows `PM2-AgenticBOS` (NSSM + pm2-runtime)
menjalankan tiga proses foreground terkelola:

| Proses | Command | Log |
|---|---|---|
| web | `php artisan serve --host=127.0.0.1 --port=8010` | `storage/logs/pm2-production-{out,error}.log` |
| queue | `php artisan queue:work database --sleep=1 --tries=3 --backoff=30 --max-time=3600` | `storage/logs/pm2-queue-{out,error}.log` |
| scheduler | `php artisan schedule:work` | `storage/logs/pm2-scheduler-{out,error}.log` |

Config: `ecosystem.production.config.cjs`. Semua path absolut; `APP_ENV=production`.

## Restart

```powershell
Restart-Service PM2-AgenticBOS          # restart seluruh stack
```

Stop/start satu proses saja (PM2 berjalan sebagai LocalSystem  pakai sesi admin):

```powershell
# dari shell admin, PM2_HOME diarahkan ke home daemon LocalSystem
$env:PM2_HOME='C:\Users\User\.pm2'
pm2 restart agentic-bos-queue
pm2 restart agentic-bos-scheduler
pm2 reload agentic-bos-production
```

Restart policy: PM2 `autorestart: true`; web `max_restarts: 10`, queue/scheduler `max_restarts: 100`, delay 3s. NSSM `AppRestartDelay: 5000`. Reboot: service `StartMode: Auto`.

## Health check

```powershell
(Get-Service PM2-AgenticBOS).Status        # Running
curl http://127.0.0.1:8010/login           # 200
php artisan queue:failed                   # "No failed jobs found"
php artisan schedule:list                  # billing:check-expiring 0 0 * * *
```

## Probe queue tepat-sekali (bila diaktifkan di produksi)

```powershell
php artisan tinker --execute="App\Jobs\InfrastructureProbeJob::dispatch('check-1')"
# setelah beberapa detik:
#   storage/app/queue-probe-check-1.txt berisi TEPAT SATU baris
# bila 2 baris => job diproses ganda (regresi); bila 0 => queue down.
```

## Aktivasi penuh (gate HUMAN:DEPLOY)

1. `git pull` / sinkron worktree produksi ke HEAD terakhir.
2. Perluas service: daemon PM2 harus memuat config baru
   `ecosystem.production.config.cjs` (3 apps). Setelah config di-commit dan
   server restart, jalankan health check + probe di atas.
3. Jangan restart tanpa approval: mengubah jumlah proses = cutover runtime.

## Log

- Semua di `storage/logs/`, terpisah per proses, dirotasi manual/bulanan.
- `nssm-stdout.log`/`nssm-stderr.log` untuk bootstrap NSSM sendiri.

## Control plane Hermes (T-82/T-86, D-72)

Bagian ini tentang **dashboard API Hermes**, bukan bridge WhatsApp. Keduanya
proses berbeda di port berbeda dan tidak boleh tertukar:

| | Bridge WhatsApp | Control plane |
|---|---|---|
| Kolom | `hermes_profiles.api_url`, `hermes_nodes.api_url` | `hermes_nodes.control_url` |
| Rahasia | `api_secret_reference` → `hermes.node_secrets` | `control_secret_reference` → `hermes.control_secrets` |
| Autentikasi | tidak ada (`none`, hanya loopback) | bearer token (H-05) |
| Port lokal saat ini | 3000 | 9119 |
| Wewenangnya | kirim pesan sebagai satu nomor | **setara terminal di host Hermes** |

Baris terakhir itu yang menentukan seluruh prosedur di bawah: port yang sama
menyajikan tulis-berkas, unggah berkas, git, dan terminal. Token control plane
karena itu bukan "kredensial monitoring" — ia kunci host.

### Memasang rahasia

1. Pilih **nama** referensi, bukan nilainya, lalu daftarkan di
   `config/hermes.php` → `control_secrets` (satu baris, memetakan nama ke env).
2. Isi nilainya di environment, mis. `HERMES_CONTROL_LOKAL_SECRET=...`. Nilai
   **tidak pernah** masuk basis data; yang tersimpan hanya namanya.
3. Isi **Alamat Control Plane** + **Referensi Rahasia Control Plane** di
   `/admin/hermes-nodes`. Form menolak alamat non-loopback tanpa referensi rahasia.
4. Buktikan:

```powershell
php artisan bos:hermes-control-ping --node=<id>
```

Harus `OK <nama node> — version <versi>`. Kalau `Kredensial control plane
ditolak`, rahasianya belum terpasang di sisi Hermes (H-05) atau namanya tidak
cocok — pesan galatnya menyebut nama referensi, tidak pernah nilainya.

### Merotasi rahasia tanpa mematikan tenant

Rotasi yang naif memutus pairing dan pemantauan di tengah jalan. Urutan yang
tidak menimbulkan jeda:

1. **Tambahkan** nama referensi baru di `control_secrets` beserta env-nya. Jangan
   hapus yang lama.
2. Pasang token baru di sisi Hermes **berdampingan** dengan yang lama (plugin
   `dashboard_auth` H-05 mendaftarkan provider per path, jadi dua token bisa
   berlaku bersamaan selama masa transisi).
3. Ubah `control_secret_reference` node ke nama baru lewat `/admin/hermes-nodes`.
4. `bos:hermes-control-ping --node=<id>` harus `OK`.
5. Baru setelah itu cabut token lama di Hermes, lalu hapus barisnya dari
   `control_secrets`.

Kalau langkah 4 gagal, kembalikan `control_secret_reference` ke nama lama — itu
satu perubahan satu kolom dan tidak menyentuh Hermes sama sekali.

**Jangan** memakai referensi `none` untuk control plane non-loopback. Kode
menolaknya, dan penolakan itu bukan formalitas: control plane tanpa token di
alamat publik berarti siapa pun bisa menulis SOUL, menyetujui pairing, dan
menjangkau permukaan tulis-berkas di host kita.

### Setelah Hermes diperbarui

Rute `/api/*` dashboard Hermes **tidak berversi** dan sebagian besar masih berada
di dalam `web_server.py`, jadi pembaruan dapat memindahkannya tanpa peringatan.
Pengendaliannya sudah terpasang — seluruh pemakaian dikurung di
`HermesControlPlaneClient` dan setiap path punya test — tetapi pemeriksaannya
tetap manual:

```powershell
php artisan bos:hermes-control-ping --node=<id>          # versi node terbaca?
$env:DATA_SOURCE="json"; php artisan test --filter=ControlPlane
```

Yang paling mungkin berpindah, berdasarkan bentuknya sekarang:

1. **`/api/pairing*`** — logikanya hidup di `gateway/pairing.py` (`PairingStore`)
   sementara rutenya masih di `web_server.py`; kandidat kuat untuk diekstrak ke
   `web_routers/pairing.py`.
2. **`/api/messaging/*`** — sama, dan onboarding WhatsApp masih memakai registry
   sesi di memori.
3. **Nama parameter.** Sesi onboarding memakai `{pairing_id}`. Kalau ia berubah
   menjadi `{id}` atau `{session_id}`, path kita tidak akan pernah cocok dan
   `ControlPlanePathCoverageTest` yang merah.
4. **Tempat `profile` dikirim.** Beberapa endpoint membacanya dari query, tiga
   endpoint dari **body** (`pairing/approve`, `pairing/revoke`,
   `onboarding/start`). Ini pergeseran paling berbahaya karena **tidak menimbulkan
   galat**: Hermes hanya mengabaikan profil yang salah tempat lalu memakai profil
   yang sedang aktif — tenant yang salah dikonfigurasi, tanpa jejak.

Bila salah satu test `ControlPlane*` merah setelah pembaruan Hermes: **jangan**
melonggarkan daftar-putih supaya hijau. Perbaiki templatenya di
`ControlPlanePaths`, lalu jalankan `bos:hermes-control-ping` terhadap node
sungguhan.

## App Node (`agentic-bos-node`) masuk ke layanan terkelola

**Masalah yang ditutup.** App Node (Express 5 + React, port 3025, dilayani Caddy di
`asistenbisnis.nalar.army` / `asistenbos.nalar.army`) **tidak pernah dikelola PM2**,
walau `Caddyfile` menulis komentar "PM2 agentic-bos-node" dan repo Node punya
`ecosystem.production.config.cjs` yang mendefinisikannya. Penyebabnya terverifikasi
dari registry layanan:

```
Layanan   : PM2-AgenticBOS  (NSSM, StartMode Auto, akun LocalSystem, Running)
Perintah  : pm2-runtime start D:\PROJECTS\agentic-bos\ecosystem.production.config.cjs
PM2_HOME  : C:\Users\User\.pm2
```

Layanan itu membaca ecosystem **repo Laravel**, yang sebelumnya hanya mendefinisikan
`agentic-bos-production`, `agentic-bos-queue`, dan `agentic-bos-scheduler`. Definisi
`agentic-bos-node` hanya ada di ecosystem **repo Node** — berkas yang tidak pernah
dibaca siapa pun. Jadi app itu selalu dijalankan tangan (`tsx server.ts`), dan hilang
begitu sesi yang memulainya ditutup atau mesin reboot.

**Yang sudah dikerjakan (tanpa menyentuh layanan):** blok `agentic-bos-node`
ditambahkan ke `D:\PROJECTS\agentic-bos\ecosystem.production.config.cjs`, dengan
`cwd` menunjuk repo Node — wajib, karena `load-env.ts` memuat `.env` relatif terhadap
cwd proses dan di sanalah `BOS_BOT_TOKEN_*` berada. Berkasnya diverifikasi tetap
valid: `require()` berhasil dan melaporkan empat app.

**Kenapa `pm2 save` tidak relevan di sini.** Layanan memakai `pm2-runtime`, yang
berjalan di depan tanpa daemon terpisah, sehingga daftar app diambil dari ecosystem
**saat layanan start**. Yang menerapkan perubahan adalah **restart layanan**, bukan
`pm2 save`/`resurrect`.

### Prosedur aktivasi (butuh sesi Administrator)

Sesi non-admin **tidak bisa** melakukannya: daemon PM2 dimiliki LocalSystem, sehingga
`pm2 list` dari sesi biasa gagal dengan `connect EPERM \\.\pipe\rpc.sock`. Itu bukan
kerusakan, itu batas hak akses.

**Blast radius — baca dulu.** Restart `PM2-AgenticBOS` me-restart **seluruh** app di
ecosystem itu: web Laravel `8010`, queue worker, scheduler, dan app Node `3025`.
Pekerjaan queue yang sedang jalan akan terputus. Lakukan saat sepi, dan periksa dulu
tidak ada koneksi aktif:

```powershell
Get-NetTCPConnection -LocalPort 3025,8010 -State Established -ErrorAction SilentlyContinue
```

Langkah, di PowerShell **Run as Administrator**:

```powershell
# 1. Matikan proses Node yang dijalankan tangan, kalau masih ada.
#    Kalau dibiarkan, PM2 gagal mengikat 3025 dan app baru akan crash-restart.
Get-NetTCPConnection -LocalPort 3025 -State Listen -ErrorAction SilentlyContinue |
  ForEach-Object { Stop-Process -Id $_.OwningProcess -Force }

# 2. Restart layanan supaya ecosystem dibaca ulang.
Restart-Service PM2-AgenticBOS

# 3. Tunggu sebentar, lalu pastikan keempat app online.
Start-Sleep -Seconds 12
pm2 jlist | ConvertFrom-Json | Select-Object name, pm2_env, pid |
  ForEach-Object { "$($_.name) -> $($_.pm2_env.status)" }
```

### Verifikasi setelah aktivasi

```powershell
# App Node hidup di loopback (NP-07 mengikat 127.0.0.1 secara bawaan; Caddy di host
# yang sama menembak 127.0.0.1:3025, jadi ini benar dan bukan regresi).
Invoke-WebRequest http://127.0.0.1:3025/api/health -UseBasicParsing | Select-Object StatusCode

# Jalur publik lewat Caddy.
Invoke-WebRequest http://127.0.0.1/api/health -Headers @{ Host = 'asistenbisnis.nalar.army' } -UseBasicParsing |
  Select-Object StatusCode

# Web Laravel tidak ikut rusak.
Invoke-WebRequest http://127.0.0.1:8010 -UseBasicParsing | Select-Object StatusCode
```

Periksa juga log start app Node — ia **wajib** memuat `.env`, kalau tidak lajur bot
menolak semua permintaan dan penyimpanan saldo menolak start (NP-08):

```powershell
Get-Content D:\PROJECTS\business-operating-system-node-js\storage\logs\pm2-node-out.log -Tail 20
```

Yang dicari: `[env] .env dimuat, N kunci diisi: …` (nama kunci saja — nilainya memang
tidak pernah dicatat) dan `[Agentic BOS] Server running on http://127.0.0.1:3025`.

### Kalau gagal

- **App Node `errored`/`restarting` terus.** Lihat `pm2-node-error.log`. Dua penyebab
  paling mungkin: port 3025 masih dipegang proses tangan (langkah 1 dilewati), atau
  `.env` tidak terbaca sehingga `TOKEN_LEDGER_DRIVER` kosong dan NP-08 menolak start
  di `NODE_ENV=production` — itu fail-closed yang benar, perbaikannya menyetel env,
  bukan melonggarkan penjaganya.
- **Balikan:** hapus kembali blok `agentic-bos-node` dari ecosystem ini dan restart
  layanan; app Node kembali ke keadaan dijalankan tangan seperti sebelumnya.
