<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * FK `invoices.company_membership_id -> company_memberships.id`
     * dipisah dari migration `create_invoices_table` (yang berjalan sebelum
     * `company_memberships` ada) supaya urutan migration portable di MySQL
     * strict FK maupun SQLite (B-01, T-21b).
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreign('company_membership_id', 'fk_invoices_company_membership')
                ->references('id')->on('company_memberships')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign('fk_invoices_company_membership');
        });
    }
};
