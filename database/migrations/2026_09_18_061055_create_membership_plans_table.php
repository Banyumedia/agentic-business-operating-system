<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64);
            $table->string('slug', 64)->unique();
            $table->decimal('monthly_price', 18, 2)->default(0);
            $table->decimal('annual_price', 18, 2)->default(0);
            $table->integer('max_wa_groups')->default(1);
            $table->bigInteger('monthly_token_quota')->default(500000);
            $table->bigInteger('emergency_token_quota')->default(25000);
            $table->bigInteger('trial_token_quota')->default(50000);
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_plans');
    }
};
