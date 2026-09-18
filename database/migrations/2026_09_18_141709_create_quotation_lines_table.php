<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotation_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('quotation_id')->constrained('quotations')->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('items')->nullOnDelete();

            $table->string('description', 255);
            $table->decimal('quantity', 18, 4)->default(1);
            $table->string('unit', 32)->nullable();
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->decimal('line_total', 18, 2)->default(0);
            $table->integer('sort_order')->default(0);

            $table->timestamps();

            $table->index(['company_id', 'quotation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_lines');
    }
};
