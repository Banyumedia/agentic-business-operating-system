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
        Schema::create('booking_incidents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('booking_id');
            $table->string('type', 32);
            $table->text('description')->nullable();
            $table->decimal('charge_amount', 18, 2)->default(0);
            $table->timestamps();

            $table->index(['company_id', 'booking_id'], 'idx_incidents_company_booking');
            $table->foreign('company_id', 'fk_incidents_company')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('booking_id', 'fk_incidents_booking')->references('id')->on('bookings')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_incidents');
    }
};
