module.exports = {
  apps: [
    {
      name: 'agentic-bos-production',
      script: 'D:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe',
      args: 'artisan serve --host=127.0.0.1 --port=8010',
      cwd: 'D:/PROJECTS/agentic-bos',
      interpreter: 'none',
      env: {
        APP_ENV: 'production',
      },
      autorestart: true,
      max_restarts: 10,
      restart_delay: 3000,
      out_file: 'D:/PROJECTS/agentic-bos/storage/logs/pm2-production-out.log',
      error_file: 'D:/PROJECTS/agentic-bos/storage/logs/pm2-production-error.log',
      time: true,
    },
    // UR-02: queue worker terkelola. --tries=3 + backoff agar job gagal
    // sementara tidak langsung masuk failed_jobs; max_memory lalu exit,
    // PM2 restart (pola "lean worker").
    {
      name: 'agentic-bos-queue',
      script: 'D:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe',
      args: 'artisan queue:work database --sleep=1 --tries=3 --backoff=30 --max-time=3600',
      cwd: 'D:/PROJECTS/agentic-bos',
      interpreter: 'none',
      env: {
        APP_ENV: 'production',
      },
      autorestart: true,
      max_restarts: 100,
      restart_delay: 3000,
      out_file: 'D:/PROJECTS/agentic-bos/storage/logs/pm2-queue-out.log',
      error_file: 'D:/PROJECTS/agentic-bos/storage/logs/pm2-queue-error.log',
      time: true,
    },
    // UR-02: scheduler terkelola (schedule:work = foreground loop,
    // menjalankan due task tiap menit).
    {
      name: 'agentic-bos-scheduler',
      script: 'D:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe',
      args: 'artisan schedule:work',
      cwd: 'D:/PROJECTS/agentic-bos',
      interpreter: 'none',
      env: {
        APP_ENV: 'production',
      },
      autorestart: true,
      max_restarts: 100,
      restart_delay: 3000,
      out_file: 'D:/PROJECTS/agentic-bos/storage/logs/pm2-scheduler-out.log',
      error_file: 'D:/PROJECTS/agentic-bos/storage/logs/pm2-scheduler-error.log',
      time: true,
    },
  ],
};
