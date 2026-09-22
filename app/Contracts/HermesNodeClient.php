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
 *   `App\Services\Hermes\FakeHermesNodeClient` yang hanya mencatat ke log.
 * - `App\Services\HermesNodeClient`: ter-scope company dan benar-benar memanggil
 *   node tenant. Ini yang WAJIB dipakai untuk pesan atas nama tenant (D-63).
 *
 * Memakai antarmuka ini untuk pesan tenant adalah persis kesalahan yang D-63
 * larang: nomor dikirimi pesan tanpa memeriksa profil Hermes company mana pun,
 * dan implementasi fake-nya mengembalikan `true` tanpa mengirim apa pun.
 */
interface HermesNodeClient
{
    public function sendWhatsApp(string $waNumber, string $message): bool;
}
