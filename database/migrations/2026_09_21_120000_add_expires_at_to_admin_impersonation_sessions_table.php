<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_impersonation_sessions', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('session_id');
            $table->index(['expires_at']);
        });
    }

    public function down(): void
    {
        Schema::table('admin_impersonation_sessions', function (Blueprint $table) {
            $table->dropIndex(['expires_at']);
            $table->dropColumn('expires_at');
        });
    }
};
