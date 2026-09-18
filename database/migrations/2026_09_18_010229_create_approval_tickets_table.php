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
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 8);
            $table->string('action_type', 64);
            $table->string('subject_type', 191)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('payload');
            $table->decimal('amount', 18, 2)->nullable();
            $table->unsignedBigInteger('requested_by_user_id')->nullable();
            $table->unsignedBigInteger('approver_user_id')->nullable();
            $table->string('status', 16)->default('pending');
            $table->string('channel', 16)->default('whatsapp');
            $table->timestamp('expires_at');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'code', 'status']);
            $table->index(['company_id', 'status'], 'idx_approval_company_status');
        });

        Schema::table('workflow_transitions_log', function (Blueprint $table) {
            $table->foreign('approval_ticket_id', 'fk_wf_log_ticket')
                ->references('id')
                ->on('approval_tickets')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workflow_transitions_log', function (Blueprint $table) {
            $table->dropForeign(['approval_ticket_id']);
        });
        Schema::dropIfExists('approval_tickets');
    }
};
