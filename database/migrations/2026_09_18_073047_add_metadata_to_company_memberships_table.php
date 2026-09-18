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
        Schema::table('company_memberships', function (Blueprint $table) {
            $table->json('metadata')->nullable()->after('current_token_balance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_memberships', function (Blueprint $table) {
            $table->dropColumn('metadata');
        });
    }
};
