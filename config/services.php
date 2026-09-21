<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'midtrans' => [
        'server_key' => env('MIDTRANS_SERVER_KEY', ''),
        'is_production' => (bool) env('MIDTRANS_IS_PRODUCTION', false),
    ],

    'nalarpesan' => [
        'webhook_secret' => env('NALARPESAN_WEBHOOK_SECRET', ''),
    ],

    'master_bot' => [
        // Kosong berarti API master bot dimatikan total (fail-closed), bukan
        // memakai rahasia default yang bisa ditebak.
        'secret' => env('MASTER_BOT_SECRET', ''),
    ],

    // UR-06: backup MySQL terenkripsi + observability.
    'backup' => [
        // Kunci enkripsi backup (min 32 char). Kosong = bos:backup-mysql
        // menolak jalan (fail-closed: tidak ada backup plain-text).
        'encryption_key' => env('BACKUP_ENCRYPTION_KEY', ''),
        'mysqldump_path' => env('BACKUP_MYSQLDUMP_PATH', 'mysqldump'),
        'openssl_path' => env('BACKUP_OPENSSL_PATH', 'openssl'),
    ],

];
