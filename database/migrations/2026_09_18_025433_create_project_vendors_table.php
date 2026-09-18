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
        Schema::create('project_vendors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('vendor_contact_id')->nullable();
            $table->foreign('vendor_contact_id')->references('id')->on('contacts')->nullOnDelete();
            $table->string('vendor_name', 191);
            $table->string('service_type', 64)->nullable();
            $table->decimal('fee', 18, 2)->nullable();
            $table->string('payment_status', 32)->default('unpaid');
            $table->json('attributes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'project_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_vendors');
    }
};
