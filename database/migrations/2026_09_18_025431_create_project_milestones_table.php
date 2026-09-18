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
        Schema::create('project_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name', 191);
            $table->unsignedInteger('sequence')->default(1);
            $table->string('trigger_type', 32);
            $table->decimal('trigger_value', 8, 2)->nullable();
            $table->decimal('amount', 18, 2);
            $table->decimal('retention_pct', 5, 2)->default(0);
            $table->unsignedBigInteger('invoice_id')->nullable(); // No FK yet, invoices table is T-12
            $table->string('status', 32)->default('pending');
            $table->timestamp('achieved_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'project_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_milestones');
    }
};
