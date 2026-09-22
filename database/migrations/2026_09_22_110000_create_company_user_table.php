<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Keanggotaan manusia pada sebuah usaha (D-65).
 *
 * Sebelum ini staf tidak punya representasi apa pun: `users.current_company_id`
 * menyimpan company aktif dan `companies.owner_user_id` menyimpan pemilik, tapi
 * tidak ada tabel yang menyatakan "orang ini anggota usaha itu". Akibatnya
 * seluruh usaha dijalankan dari satu akun owner.
 *
 * Perannya sengaja hanya `owner|staff` mengikuti kosakata yang sudah dikunci
 * `PresetDefinitionValidator` dan dipakai 40 preset di transisi workflow.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 16)->default('staff');

            // Jejak siapa yang mengundang, untuk audit pencabutan akses.
            $table->foreignId('invited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();

            $table->timestamps();

            // Satu orang satu keanggotaan per usaha; undangan ulang memperbarui
            // baris yang ada, bukan menumpuk peran ganda.
            $table->unique(['company_id', 'user_id']);
            $table->index(['company_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_user');
    }
};
