<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('external_ref', 128)->nullable();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->decimal('target_qty', 10, 3);
            $table->string('stage', 32)->default('draft');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'external_ref']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_orders');
    }
};
