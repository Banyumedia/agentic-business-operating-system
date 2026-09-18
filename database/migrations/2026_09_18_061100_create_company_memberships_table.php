<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('membership_plans')->cascadeOnDelete();
            $table->enum('status', ['trial', 'active', 'past_due', 'ai_suspended', 'read_only', 'frozen', 'cancelled'])->default('trial');
            $table->timestamp('starts_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->integer('max_wa_groups')->default(1);
            $table->bigInteger('monthly_token_quota')->default(500000);
            $table->bigInteger('emergency_token_quota')->default(25000);
            $table->bigInteger('current_token_balance')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_memberships');
    }
};
