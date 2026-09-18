<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('business_identity_id')->nullable()->constrained('business_identities')->nullOnDelete();
            $table->string('type', 32)->default('customer');
            $table->string('name', 191);
            $table->string('wa_number', 32)->nullable();
            $table->string('email', 191)->nullable();
            $table->string('source', 32)->nullable();
            $table->json('tags')->nullable();
            $table->json('attributes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'type']);
            $table->index(['company_id', 'wa_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
