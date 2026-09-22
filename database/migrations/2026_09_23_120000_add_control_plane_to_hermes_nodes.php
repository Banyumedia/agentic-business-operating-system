<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Control plane Hermes punya alamat dan rahasia **sendiri**, terpisah dari bridge
 * (D-72 butir 4).
 *
 * Yang sudah ada berarti hal lain: `hermes_nodes.api_url` dan
 * `hermes_profiles.api_url` (T-81) adalah alamat **bridge WhatsApp** - loopback,
 * tanpa autentikasi, satu port per nomor. Dashboard API Hermes adalah proses lain,
 * di port lain, dan **butuh token**.
 *
 * Mencampur keduanya punya dua akibat yang sama-sama buruk: token dashboard
 * terkirim ke port bridge yang tidak memintanya (bocor tanpa perlu), dan node yang
 * belum punya control plane tampak siap dipakai untuk provisioning.
 *
 * Nilai rahasianya **tidak** disimpan di sini, hanya namanya - pola yang sama
 * dengan `api_secret_reference`, dipetakan di `config/hermes.php` dari environment.
 * Keduanya nullable: node yang hanya menjalankan bridge tetap sah, dan lajur
 * control plane menolaknya dengan jelas ketika dipakai.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hermes_nodes', function (Blueprint $table) {
            $table->string('control_url', 191)->nullable()->after('api_secret_reference');
            $table->string('control_secret_reference', 191)->nullable()->after('control_url');
        });
    }

    public function down(): void
    {
        Schema::table('hermes_nodes', function (Blueprint $table) {
            $table->dropColumn(['control_url', 'control_secret_reference']);
        });
    }
};
