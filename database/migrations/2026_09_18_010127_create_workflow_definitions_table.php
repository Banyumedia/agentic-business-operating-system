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
        Schema::create('workflow_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('company_id', 36)->index();
            $table->string('entity_type', 100);
            $table->string('from_stage', 50);
            $table->string('to_stage', 50);
            $table->boolean('requires_approval')->default(false);
            $table->string('required_role', 50)->nullable();
            $table->json('effects')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'entity_type', 'from_stage', 'to_stage'], 'idx_workflow_def_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_definitions');
    }
};
