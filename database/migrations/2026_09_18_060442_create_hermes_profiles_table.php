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
        Schema::create('hermes_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('node_id')->nullable()->constrained('hermes_nodes')->restrictOnDelete();
            $table->string('type', 16)->default('primary');
            $table->string('label', 64)->nullable();
            $table->unsignedBigInteger('billing_addon_id')->nullable();
            $table->string('instance_id', 128)->unique();
            $table->string('webhook_secret_reference', 191);
            $table->string('status', 16)->default('unpaired');
            $table->timestamp('last_ping_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hermes_profiles');
    }
};
