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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            // company_membership_id FK ditambahkan di migration terpisah
            // setelah tabel `company_memberships` ada (2026_09_18_061100)
            // supaya urutan migration portable di MySQL strict FK maupun
            // SQLite (B-01, dibuktikan gagal di T-21b paritas MySQL: error 1824).
            $table->unsignedBigInteger('company_membership_id')->nullable();
            $table->enum('type', ['topup', 'subscription']);
            $table->string('order_id', 64)->unique();
            $table->decimal('amount', 18, 2);
            $table->bigInteger('token_amount_granted')->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->enum('payment_status', ['pending', 'paid', 'expired', 'failed'])->default('pending');
            $table->string('payment_url', 255)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index('company_id', 'idx_invoices_company');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
