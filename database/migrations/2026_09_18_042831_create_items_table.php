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
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('sku', 64)->nullable();
            $table->string('name', 191);
            $table->string('type', 32)->default('goods');
            $table->string('unit', 16)->default('pcs');
            $table->decimal('price', 18, 2)->nullable();
            $table->decimal('cost', 18, 2)->nullable();
            $table->decimal('min_stock', 14, 3)->default(0);
            $table->boolean('track_batches')->default(false);
            $table->json('attributes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'sku'], 'uq_items_company_sku');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
