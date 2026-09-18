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
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('doctor_name', 191)->nullable();
            $table->string('doctor_sip', 64)->nullable();
            $table->json('extracted_lines')->nullable();
            $table->string('stage', 32)->default('draft');
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('served_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'stage'], 'idx_rx_company_stage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prescriptions');
    }
};
