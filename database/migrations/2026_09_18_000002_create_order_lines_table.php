<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->unsignedBigInteger('item_id')->nullable();
            $table->string('description', 191);
            $table->decimal('qty', 14, 3);
            $table->decimal('unit_price', 18, 2);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('line_total', 18, 2);
            $table->timestamp('fired_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['company_id', 'order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_lines');
    }
};
