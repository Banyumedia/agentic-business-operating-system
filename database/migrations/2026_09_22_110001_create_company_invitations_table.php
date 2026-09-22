<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Undangan staf lewat WhatsApp (D-65).
 *
 * Kode disimpan sebagai hash, bukan teks terang: tabel ini pintu masuk ke data
 * usaha, jadi kebocoran isinya tidak boleh langsung dapat dipakai. Undangan
 * terikat pasangan company + nomor, sekali pakai, dan kedaluwarsa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('invited_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('wa_number', 32);
            $table->string('role', 16)->default('staff');
            $table->string('code_hash');

            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'wa_number']);
            $table->index(['company_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_invitations');
    }
};
