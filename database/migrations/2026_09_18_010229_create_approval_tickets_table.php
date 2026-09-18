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
        Schema::create('approval_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('company_id', 36)->index();
            $table->string('entity_type', 100);
            $table->string('entity_id');
            $table->string('from_stage', 50);
            $table->string('to_stage', 50);
            $table->string('requested_by_user_id')->nullable();
            $table->string('status', 30)->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_tickets');
    }
};
