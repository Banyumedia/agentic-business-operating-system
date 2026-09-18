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
        Schema::create('hermes_nodes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191);
            $table->string('api_url', 191);
            $table->string('api_secret_reference', 191);
            $table->integer('max_capacity')->default(100);
            $table->integer('active_profiles')->default(0);
            $table->enum('status', ['active', 'maintenance', 'down'])->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hermes_nodes');
    }
};
