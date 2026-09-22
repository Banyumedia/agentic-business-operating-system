<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T-105: penempatan tenant ke node + port bridge sebagai sumber daya + status `draining`.
 *
 * Dua perubahan skema yang saling melengkapi:
 *
 * (1) **Port bridge dimodelkan, unik per node.** Sampai sekarang `hermes_profiles.api_url`
 *     menyimpan `host:port` sebagai string bebas, jadi dua profil pada satu node bisa
 *     memakai port yang sama dan saling menendang tanpa pesan yang jelas - dan
 *     kegagalannya baru muncul saat pesan pertama. `bridge_port` menjadikan port
 *     sumber daya yang dialokasikan; `unique(['node_id','bridge_port'])` adalah jaring
 *     terakhir yang menolak tabrakan **saat simpan**, bukan saat kirim. Nullable supaya
 *     baris lama (dan profil tanpa node) tetap sah - unique di SQLite/MySQL mengizinkan
 *     banyak NULL, jadi profil yang belum dialokasikan port tidak saling bertabrakan.
 *
 * (2) **Status `draining`.** Hermes punya `POST /api/gateway/drain`: node yang sedang
 *     dikosongkan **tidak menerima penempatan baru** tetapi **tetap melayani** profil
 *     yang sudah ada. Enum lama `['active','maintenance','down']` tidak punya kata untuk
 *     keadaan ini, jadi operator terpaksa memilih antara mematikan tenant lebih awal
 *     (down) atau membiarkan node menerima tenant baru saat hendak dimatikan (active).
 *
 *     Kenapa kolomnya menjadi `string`, bukan enum baru: mengubah anggota enum di SQLite
 *     berarti membangun ulang tabel beserta CHECK constraint-nya - operasi yang rapuh dan
 *     berbeda per driver. `hermes_profiles.status` sudah `string(16)` dengan kosakata
 *     dijaga di lapisan aplikasi (validasi `HermesNodeManager`), jadi menyamakan
 *     `hermes_nodes.status` ke pola yang sama lebih jujur daripada dua mekanisme enum
 *     yang berbeda untuk dua kolom sejenis. Nilai lama tetap valid apa adanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hermes_profiles', function (Blueprint $table) {
            $table->unsignedInteger('bridge_port')->nullable()->after('api_url');
            // Unik **per node**, bukan global: port yang sama sah di host berbeda.
            $table->unique(['node_id', 'bridge_port']);
        });

        // Enum -> string, mempertahankan nilai yang sudah ada. Dilakukan lewat Schema
        // supaya lintas driver (di SQLite ini membangun ulang tabel; di MySQL mengubah
        // definisi kolom) tanpa menyentuh baris.
        Schema::table('hermes_nodes', function (Blueprint $table) {
            $table->string('status', 16)->default('active')->change();
        });
    }

    public function down(): void
    {
        Schema::table('hermes_profiles', function (Blueprint $table) {
            $table->dropUnique(['node_id', 'bridge_port']);
            $table->dropColumn('bridge_port');
        });

        // Kembalikan baris `draining` ke sesuatu yang muat di enum lama sebelum enum
        // dipasang lagi, kalau tidak pemasangan enum akan menolaknya.
        DB::table('hermes_nodes')->where('status', 'draining')->update(['status' => 'down']);

        Schema::table('hermes_nodes', function (Blueprint $table) {
            $table->enum('status', ['active', 'maintenance', 'down'])->default('active')->change();
        });
    }
};
