<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak pengingat piutang yang sudah terkirim (D-63).
 *
 * Idempotensi ditegakkan indeks unik, bukan oleh kehati-hatian kode: satu
 * tagihan hanya boleh mendapat satu pengingat per tahap. Tanpa ini, scheduler
 * yang berjalan dua kali atau job yang di-retry akan mengirim pesan berulang ke
 * pemilik usaha.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_invoice_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('customer_invoice_id')->constrained('customer_invoices')->cascadeOnDelete();
            $table->string('stage', 32);
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->unique(['company_id', 'customer_invoice_id', 'stage'], 'customer_invoice_reminders_unique_stage');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_invoice_reminders');
    }
};
