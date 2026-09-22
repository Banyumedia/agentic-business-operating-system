<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Mencabut token bot yang tersimpan plaintext (QA-08).
 *
 * `hermes_profiles.webhook_secret_reference` dulu menyimpan token apa adanya, dan
 * `AuthenticateTenantBot` mencocokkannya verbatim. Sejak sekarang yang tersimpan
 * adalah **SHA-256** token. Baris lama karena itu **berhenti bekerja dengan
 * sendirinya** - hash dari token yang dikirim tidak akan cocok dengan plaintext yang
 * tersimpan.
 *
 * Yang tidak selesai dengan sendirinya adalah kredensialnya **tetap tergeletak di
 * basis data**, dan itu justru masalah yang sedang ditutup. Nilainya tidak bisa
 * di-hash di tempat karena hash dari plaintext yang sama akan membuat token lama
 * kembali sah - padahal token itulah yang mungkin sudah bocor lewat backup atau dump.
 *
 * Jadi baris lama ditandai perlu diterbitkan ulang. Konsekuensinya dinyatakan
 * terbuka: **setiap bot yang sudah terpasang berhenti bisa memanggil TenantBot API
 * sampai operator menjalankan** `bos:hermes-profile --reissue`. Itu memang jalur
 * fail-closed yang diinginkan; alternatifnya adalah membiarkan kredensial yang
 * mungkin sudah bocor tetap berlaku.
 *
 * Tidak ada `down()` yang berarti: plaintext yang sudah dibuang tidak bisa
 * dikembalikan, dan mengembalikannya pun tidak diinginkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('hermes_profiles')
            ->whereNotNull('webhook_secret_reference')
            // Baris yang sudah dicabut `CleanupExpiredTrials` dibiarkan apa adanya:
            // penandanya sudah menyatakan keadaannya, dan ia bukan kredensial.
            ->where('webhook_secret_reference', 'not like', 'revoked_%')
            ->where('webhook_secret_reference', 'not like', 'needs_reissue_%')
            ->orderBy('id')
            ->each(function ($profile) {
                // Nilai lama tidak disalin ke mana pun. Penandanya diberi komponen acak
                // supaya tidak ada dua baris yang bertabrakan bila kolomnya kelak
                // diberi unique index.
                DB::table('hermes_profiles')
                    ->where('id', $profile->id)
                    ->update(['webhook_secret_reference' => 'needs_reissue_'.bin2hex(random_bytes(8))]);
            });
    }

    public function down(): void
    {
        // Sengaja kosong. Lihat penjelasan di atas.
    }
};
