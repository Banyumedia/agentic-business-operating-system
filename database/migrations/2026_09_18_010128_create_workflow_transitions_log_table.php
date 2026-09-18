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
        Schema::create('workflow_transitions_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('entity', 64);
            $table->unsignedBigInteger('entity_id');
            $table->string('from_stage', 32)->nullable();
            $table->string('to_stage', 32);
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->unsignedBigInteger('approval_ticket_id')->nullable();
            $table->string('note', 255)->nullable();
            $table->json('effects_run')->nullable();
            $table->string('changed_by_type', 32)->default('user');
            $table->timestamps();

            $table->index(['company_id', 'entity', 'entity_id'], 'idx_wf_log_company_entity');

            // Note: FK to approval_tickets added in approval_tickets migration to avoid circular dependency
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_transitions_log');
    }
};
