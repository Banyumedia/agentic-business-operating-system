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
        Schema::table('payrolls', function (Blueprint $table) {
            $table->decimal('bpjs_kesehatan', 18, 2)->default(0)->after('net_salary');
            $table->decimal('bpjs_ketenagakerjaan', 18, 2)->default(0)->after('bpjs_kesehatan');
            $table->decimal('pph21', 18, 2)->default(0)->after('bpjs_ketenagakerjaan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn(['bpjs_kesehatan', 'bpjs_ketenagakerjaan', 'pph21']);
        });
    }
};
