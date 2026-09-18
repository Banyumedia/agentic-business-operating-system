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
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('deal_id')->nullable()->constrained('deals')->nullOnDelete();
            $table->string('type', 32)->default('project');
            $table->string('name', 191);
            $table->string('stage', 32)->default('planned');
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->string('venue', 191)->nullable();
            $table->decimal('budget', 18, 2)->nullable();
            $table->decimal('progress_pct', 5, 2)->default(0);
            $table->unsignedBigInteger('owner_user_id')->nullable();
            $table->json('attributes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'stage']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
