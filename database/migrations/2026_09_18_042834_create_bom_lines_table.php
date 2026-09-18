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
        Schema::create('bom_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('product_item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('component_item_id')->constrained('items');
            $table->decimal('qty', 14, 3);
            $table->timestamps();

            $table->unique(['company_id', 'product_item_id', 'component_item_id'], 'uq_bom');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bom_lines');
    }
};
