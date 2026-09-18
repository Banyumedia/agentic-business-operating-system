<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->date('entry_date');
            $table->string('direction', 8); // in or out
            $table->decimal('amount', 18, 2);
            $table->string('category', 64)->nullable();
            $table->string('description', 255)->nullable();

            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();

            $table->string('source_type', 191)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();

            $table->foreignId('journal_id')->nullable()->constrained('accounting_journals')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('external_reference', 128)->nullable();
            $table->string('notes', 191)->nullable();
            $table->json('custom')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'entry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_entries');
    }
};
