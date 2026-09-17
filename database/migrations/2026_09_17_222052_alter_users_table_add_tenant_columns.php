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
        Schema::table('users', function (Blueprint $table) {
            $table->string('wa_number', 32)->nullable();
            $table->boolean('wa_is_verified')->default(false);
            $table->foreignId('current_company_id')->nullable()->constrained('companies')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['current_company_id']);
            $table->dropColumn(['wa_number', 'wa_is_verified', 'current_company_id']);
        });
    }
};
