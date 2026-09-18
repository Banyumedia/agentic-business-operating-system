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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('resource_id');
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->string('type', 32)->default('booking');
            $table->string('stage', 32)->default('draft');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->dateTime('actual_ends_at')->nullable();
            $table->decimal('rate_amount', 18, 2)->nullable();
            $table->decimal('deposit_amount', 18, 2)->default(0);
            $table->decimal('late_fee_per_unit', 18, 2)->default(0);
            $table->decimal('late_fee_total', 18, 2)->default(0);
            $table->unsignedBigInteger('pic_user_id')->nullable();
            $table->json('attributes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'resource_id', 'starts_at', 'ends_at'], 'idx_bookings_company_resource_time');
            $table->index(['company_id', 'stage'], 'idx_bookings_company_stage');
            $table->foreign('company_id', 'fk_bookings_company')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('resource_id', 'fk_bookings_resource')->references('id')->on('resources');
            $table->foreign('contact_id', 'fk_bookings_contact')->references('id')->on('contacts')->onDelete('set null');
            $table->foreign('project_id', 'fk_bookings_project')->references('id')->on('projects')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
