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
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191);
            $table->string('slug', 64)->unique();
            $table->foreignId('owner_user_id')->constrained('users');
            $table->string('business_preset', 32)->default('custom');
            $table->string('theme', 8)->default('a');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('business_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('legal_name', 191);
            $table->string('npwp', 32)->nullable();
            $table->text('address')->nullable();
            $table->boolean('price_includes_tax')->default(false);
            $table->enum('tax_mode', ['taxable', 'non_taxable'])->default('non_taxable');
            $table->decimal('tax_rate', 5, 2)->nullable()->default(0.00);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_identities');
        Schema::dropIfExists('companies');
    }
};
