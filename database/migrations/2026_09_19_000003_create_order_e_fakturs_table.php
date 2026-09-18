<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('order_e_fakturs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('nomor_seri', 64)->nullable();
            $table->string('npwp_lawan_transaksi', 32)->nullable();
            $table->decimal('ppn', 18, 2)->default(0);
            $table->enum('status', ['belum_dikirim', 'terkirim', 'gagal'])->default('belum_dikirim');
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_e_fakturs');
    }
};
