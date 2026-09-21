<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tagihan yang tenant terbitkan ke pelanggannya (D-62).
 *
 * Tabel ini TIDAK menggantikan `invoices`: `invoices` adalah tagihan langganan
 * platform (D-23, `topup`/`subscription`). Keduanya hidup berdampingan karena
 * pihak yang ditagih berbeda.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('number', 64);

            // Tagihan boleh berdiri sendiri: ketiga relasi ini nullable (D-62).
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('quotation_id')->nullable()->constrained('quotations')->nullOnDelete();

            $table->string('title', 191);
            $table->string('status', 16)->default('draft');
            $table->date('issue_date');
            $table->date('due_date')->nullable();

            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('dpp', 18, 2)->default(0);
            $table->decimal('tax', 18, 2)->default(0);
            $table->decimal('grand_total', 18, 2)->default(0);
            $table->decimal('paid_amount', 18, 2)->default(0);

            $table->text('notes')->nullable();
            $table->json('attributes')->nullable();

            $table->timestamps();

            // Nomor unik per company, bukan global: dua usaha boleh memakai
            // nomor yang sama tanpa saling mengganggu (D-26).
            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'issue_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_invoices');
    }
};
