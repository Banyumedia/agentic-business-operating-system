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
  ],
};
