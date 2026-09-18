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
            $table->bigInteger('emergency_balance')->default(0)->after('emergency_token_quota');
        });
    }

    public function down(): void
    {
        Schema::table('company_memberships', function (Blueprint $table) {
            $table->dropColumn('emergency_balance');
        });
    }
};
