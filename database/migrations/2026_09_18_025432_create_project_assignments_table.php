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
        Schema::create('project_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('employee_id')->nullable(); // No FK yet, employees table is T-13f
            $table->string('assignee_name', 191)->nullable();
            $table->string('role', 64)->nullable();
            $table->dateTime('scheduled_at')->nullable();
            $table->decimal('hourly_cost', 18, 2)->nullable();
            $table->timestamps();

            $table->index(['company_id', 'project_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_assignments');
    }
};
