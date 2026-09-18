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
        Schema::create('resources', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('type', 32);
            $table->string('name', 191);
            $table->string('category', 64)->nullable();
            $table->string('status', 32)->default('available');
            $table->unsignedInteger('capacity')->nullable();
            $table->decimal('rate_amount', 18, 2)->nullable();
            $table->string('rate_unit', 16)->nullable();
            $table->text('condition_notes')->nullable();
            $table->json('attributes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'type'], 'idx_resources_company_type');
            $table->foreign('company_id', 'fk_resources_company')->references('id')->on('companies')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resources');
    }
};
