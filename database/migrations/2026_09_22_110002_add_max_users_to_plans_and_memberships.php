<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kuota pengguna per paket (D-65).
 *
 * Mengikuti bentuk `max_wa_groups` yang sudah terbukti: angkanya ada di paket,
 * disalin ke membership saat berlangganan, dan tier gratis dibaca dari config.
 * Batasan lewat kuota, bukan lewat kapabilitas (D-60/D-61).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_plans', function (Blueprint $table) {
            $table->unsignedInteger('max_users')->default(1);
        });

        Schema::table('company_memberships', function (Blueprint $table) {
            $table->unsignedInteger('max_users')->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('membership_plans', function (Blueprint $table) {
            $table->dropColumn('max_users');
        });

        Schema::table('company_memberships', function (Blueprint $table) {
            $table->dropColumn('max_users');
        });
    }
};
