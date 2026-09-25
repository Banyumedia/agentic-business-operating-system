module.exports = {
  apps: [
    // ASISTENBISNIS.NALAR.ARMY / ASISTENBOS.NALAR.ARMY — port Node.js
    // (Express 5 + React 19 + Prisma) di repo terpisah.
    //
    // KENAPA BLOK INI ADA DI SINI, bukan hanya di ecosystem repo Node.
    // Layanan `PM2-AgenticBOS` (NSSM, LocalSystem, StartMode Auto) menjalankan
    // `pm2-runtime start D:\PROJECTS\agentic-bos\ecosystem.production.config.cjs` —
    // yaitu **berkas ini**. Ecosystem di repo Node juga mendefinisikan
    // `agentic-bos-node`, tetapi berkas itu **tidak pernah dibaca** oleh layanan.
    // Akibatnya app Node tidak pernah dikelola siapa pun: ia dijalankan tangan, dan
    // hilang begitu sesi yang memulainya ditutup atau mesin reboot. Caddyfile bahkan
    // menulis komentar "PM2 agentic-bos-node", jadi selisihnya antara niat dan
    // kenyataan sudah lama ada dan tidak terlihat.
    //
    // `pm2-runtime` berjalan di depan (tanpa daemon terpisah), sehingga daftar app
    // diambil dari berkas ini **saat layanan start**. Artinya `pm2 save` tidak
    // relevan — yang menerapkan perubahan adalah **restart layanan**.
    //
    // `cwd` wajib menunjuk repo Node: `load-env.ts` memuat `.env` relatif terhadap
    // cwd proses, dan di sanalah token lajur bot (BOS_BOT_TOKEN_*) berada.
    // `env` di bawah sengaja minimal; sisanya datang dari `.env` repo itu, dan
    // nilai yang sudah ada di environment menang atas berkas.
    {
      name: 'agentic-bos-node',
      script: 'server.ts',
      cwd: 'D:/PROJECTS/business-operating-system-node-js',
      interpreter: 'node',
      node_args: '--import tsx',
      env: {
        NODE_ENV: 'production',
        PORT: '3025',
      },
      autorestart: true,
      max_restarts: 10,
      restart_delay: 3000,
      max_memory_restart: '512M',
      out_file: 'D:/PROJECTS/business-operating-system-node-js/storage/logs/pm2-node-out.log',
      error_file: 'D:/PROJECTS/business-operating-system-node-js/storage/logs/pm2-node-error.log',
      time: true,
    },
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
