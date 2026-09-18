<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('token_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('company_membership_id')->constrained('company_memberships')->cascadeOnDelete();
            $table->enum('direction', ['credit', 'debit']);
            $table->bigInteger('amount');
            $table->bigInteger('balance_after');
            $table->string('source', 32);
            $table->string('idempotency_key', 128)->unique();
            $table->string('reference_type', 64)->nullable();
            $table->string('reference_id', 128)->nullable();
            $table->string('provider', 64)->nullable();
            $table->string('model', 128)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'created_at'], 'idx_token_ledger_company_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('token_ledger_entries');
    }
};
