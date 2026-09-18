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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items');
            $table->foreignId('batch_id')->nullable()->constrained('item_batches');
            $table->string('direction', 8); // in | out
            $table->decimal('qty', 14, 3);
            $table->string('reason', 32);
            $table->string('reference_type', 191)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->timestamp('created_at')->nullable(); // based on DATA_MODEL.md, no updated_at

            $table->index(['company_id', 'item_id'], 'idx_moves_company_item');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
