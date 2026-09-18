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
        Schema::create('loyalty_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            // Mode A: per-nominal transaksi (tiap Rp X = 1 poin)
            $table->unsignedInteger('nominal_per_point')->nullable()->comment('Null means nominal mode is disabled');

            // Mode B: per-item/kategori didukung lewat tabel terpisah atau JSON
            // Untuk kesederhanaan, simpan di JSON configuration
            $table->json('item_point_rates')->nullable()->comment('JSON mapping of item/category to points');

            // Expiry policy
            $table->unsignedInteger('expiry_months')->nullable()->comment('Null means points never expire');

            // Is active
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // Ensure one rule per company
            $table->unique('company_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loyalty_rules');
    }
};
