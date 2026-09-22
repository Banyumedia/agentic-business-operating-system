<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prospek (lead) yang japri bot CS platform sebelum menjadi pelanggan (D-76).
 *
 * Tabel ini sengaja **terpisah** dari `support_tickets` yang company-scoped:
 * prospek justru **belum** punya company, jadi menaruhnya di sana mustahil
 * (relasi wajib) dan salah arti (tiket = pelanggan yang sudah ada).
 *
 * Batas privasi D-76 dibawa ke bentuk tabelnya: yang disimpan minimal - nomor,
 * pesan terakhir, sumber - tanpa menautkan ke identitas internal apa pun. Tidak
 * ada `user_id`/`company_id`: menautkannya berarti menebak identitas dari nomor
 * WA, yang D-66 larang. Nomor jadi kunci unik supaya satu orang yang bertanya
 * berkali-kali tetap satu kartu prospek, bukan tumpukan baris.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            // Nomor WA yang sudah dinormalkan (628...). Unik: satu prospek satu baris.
            $table->string('wa_number', 32)->unique();
            $table->string('name')->nullable();
            $table->text('last_message');
            // Dari mana prospek datang (mis. 'wa_cs'). Bebas supaya sumber baru tidak
            // menuntut migration, tetapi diberi default yang jujur.
            $table->string('source', 32)->default('wa_cs');
            // baru -> dihubungi -> dikonversi | ditutup. Kosakata dijaga di lapisan
            // aplikasi (validasi controller/service) mengikuti pola status lain.
            $table->string('status', 16)->default('baru');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
