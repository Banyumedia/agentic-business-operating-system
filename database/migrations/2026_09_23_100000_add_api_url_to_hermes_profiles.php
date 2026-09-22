<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alamat bridge menjadi milik **profil**, bukan node.
 *
 * Satu bridge WhatsApp = satu nomor = satu port, dan sesinya tersimpan di satu
 * direktori. Selama alamat itu hanya ada di `hermes_nodes`, setiap nomor memaksa
 * satu baris node tersendiri — yang membuat `max_capacity` kehilangan arti, karena
 * kolom itu dimaksudkan untuk "berapa profil yang muat pada satu host".
 *
 * Pembagian yang benar: **node adalah host atau klaster**, **profil menyimpan
 * alamat bridge-nya sendiri**. Nullable dan jatuh kembali ke alamat node, supaya
 * profil yang sudah ada tidak perlu diisi ulang dan penyebaran satu-bridge-satu-host
 * tetap sah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hermes_profiles', function (Blueprint $table) {
            $table->string('api_url', 191)->nullable()->after('node_id');
        });
    }

    public function down(): void
    {
        Schema::table('hermes_profiles', function (Blueprint $table) {
            $table->dropColumn('api_url');
        });
    }
};
