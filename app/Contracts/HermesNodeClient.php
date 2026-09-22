<?php

namespace App\Contracts;

/**
 * Pengirim WhatsApp **platform** (tagihan langganan, tangga dunning D-49/D-23).
 *
 * PERHATIAN - ada dua tipe bernama `HermesNodeClient` di proyek ini:
 *
 * - `App\Contracts\HermesNodeClient` (ini): TIDAK ter-scope company. Dipakai
 *   jalur billing platform, di mana penerimanya adalah pelanggan platform dan
 *   bot-nya milik platform. Implementasi bawaannya
 *   `App\Services\Hermes\PlatformHermesNodeClient`, yang mengirim lewat bot **CS**
 *   platform (bukan bot dev). `FakeHermesNodeClient` masih ada untuk test yang
 *   menguji tangga dunning, tetapi harus dinyatakan di test itu - bukan bawaan.
 * - `App\Services\HermesNodeClient`: ter-scope company dan benar-benar memanggil
 *   node tenant. Ini yang WAJIB dipakai untuk pesan atas nama tenant (D-63).
 *
 * Memakai antarmuka ini untuk pesan tenant adalah persis kesalahan yang D-63
 * larang: nomor dikirimi pesan tanpa memeriksa profil Hermes company mana pun.
 *
 * Nilai kembaliannya **wajib diperiksa**. Ia `false` untuk setiap penolakan, dan
 * pemanggil yang mengabaikannya akan mencatat pesan sebagai terkirim padahal tidak
 * - persis cacat yang membuat dunning tampak berjalan selama ini.
 */
interface HermesNodeClient
{
    public function sendWhatsApp(string $waNumber, string $message): bool;
}
