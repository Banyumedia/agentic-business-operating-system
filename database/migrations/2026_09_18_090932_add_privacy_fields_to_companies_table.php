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
        Schema::table('companies', function (Blueprint $table) {
            $table->timestamp('privacy_accepted_at')->nullable();
            $table->foreignId('privacy_accepted_by_user_id')->nullable()->constrained('users');
            $table->string('privacy_policy_version', 32)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropForeign(['privacy_accepted_by_user_id']);
            $table->dropColumn(['privacy_accepted_at', 'privacy_accepted_by_user_id', 'privacy_policy_version']);
        });
    }
};
