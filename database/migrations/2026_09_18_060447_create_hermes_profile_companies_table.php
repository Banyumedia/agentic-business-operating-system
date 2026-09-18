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
        Schema::create('hermes_profile_companies', function (Blueprint $table) {
            $table->foreignId('hermes_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('role', 32)->default('owner');
            $table->boolean('is_default')->default(false);
            $table->timestamp('created_at')->nullable();

            $table->primary(['hermes_profile_id', 'company_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hermes_profile_companies');
    }
};
