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
        Schema::table('hermes_profiles', function (Blueprint $table) {
            $table->boolean('is_platform_provided')->default(false)->after('instance_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hermes_profiles', function (Blueprint $table) {
            $table->dropColumn('is_platform_provided');
        });
    }
};
