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
        Schema::create('ai_model_pricings', function (Blueprint $table) {
            $table->id();
            $table->string('model_name', 64)->unique();
            $table->decimal('input_multiplier', 8, 2)->default(1.00);
            $table->decimal('output_multiplier', 8, 2)->default(3.00);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_model_pricings');
    }
};
