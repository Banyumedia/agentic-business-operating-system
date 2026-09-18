<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * FK `orders.resource_id -> resources.id` dipisah dari migration
     * `create_orders_table` (yang berjalan sebelum `resources` ada) supaya
     * urutan migration portable di MySQL strict FK maupun SQLite (B-01,
     * dibuktikan gagal di T-21b paritas MySQL: error 1824).
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('resource_id', 'fk_orders_resource')
                ->references('id')->on('resources')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign('fk_orders_resource');
        });
    }
};
