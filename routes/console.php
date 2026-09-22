<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('billing:check-expiring')->daily();

// UR-06: backup harian terenkripsi + health check dengan alert log.
// D-63/T-49: pengingat piutang pelanggan. Dijalankan pagi supaya pemilik usaha
// menerimanya di awal hari kerja; idempoten, jadi aman bila terpanggil ulang.
Schedule::command('bos:remind-receivables')->dailyAt('07:30');

Schedule::command('bos:backup-mysql')->dailyAt('02:30');
Schedule::command('bos:health')->everyFiveMinutes();

// T-81: status profil diturunkan dari bridge, bukan dari ketikan. Nomor WhatsApp
// bisa lepas sendiri (sesi kedaluwarsa, perangkat dicabut) tanpa ada yang memberi
// tahu kita; tanpa penyegaran berkala, `status = paired` akan tetap terpasang dan
// setiap pengiriman dicoba lalu gagal. Sepuluh menit cukup rapat untuk menangkapnya
// sebelum pesan menumpuk, cukup jarang untuk tidak membebani bridge.
Schedule::command('bos:hermes-profile-status')->everyTenMinutes();
