<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_identity_id')->constrained('business_identities');
            $table->foreignId('shift_id')->nullable()->constrained('pos_shifts')->nullOnDelete();
            $table->unsignedBigInteger('contact_id')->nullable();
            // resource_id FK ditambahkan di migration terpisah setelah tabel
            // `resources` ada (2026_09_18_035954) supaya urutan migration
            // portable di MySQL strict FK maupun SQLite (B-01, T-21b).
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->unsignedBigInteger('prescription_id')->nullable();

            $table->string('order_no', 64);
            $table->string('stage', 32)->default('open');
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('dpp', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('grand_total', 18, 2)->default(0);
            $table->string('payment_method', 32)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('source', 32)->default('pos');
            $table->string('external_ref', 128)->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'order_no']);
            $table->unique(['company_id', 'external_ref']);
            $table->index(['company_id', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
