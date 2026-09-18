<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('production_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('component_item_id')->constrained('items')->restrictOnDelete();
            $table->decimal('planned_qty', 14, 3);
            $table->decimal('actual_qty', 14, 3)->nullable();
            $table->decimal('cost_per_unit', 15, 2)->nullable();
            $table->timestamps();

            $table->index(['company_id', 'production_order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_order_lines');
    }
};
