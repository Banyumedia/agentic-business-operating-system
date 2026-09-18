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
        Schema::create('item_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->string('batch_no', 64);
            $table->date('expires_on')->nullable();
            $table->decimal('qty_on_hand', 14, 3)->default(0);
            $table->timestamps();

            $table->index(['company_id', 'item_id', 'expires_on'], 'idx_batches_company_item_exp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_batches');
    }
};
