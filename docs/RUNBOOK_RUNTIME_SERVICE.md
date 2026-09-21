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
