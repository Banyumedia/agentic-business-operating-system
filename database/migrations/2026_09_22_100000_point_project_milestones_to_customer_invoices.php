<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Termin proyek menunjuk tagihan pelanggan, bukan tagihan langganan platform.
 *
 * Kolomnya lahir sebagai `invoice_id` dengan catatan "No FK yet, invoices table
 * is T-12" - dan T-12 ternyata membangun tabel billing platform (D-23), sasaran
 * yang salah. D-62 menetapkan referensinya di-repoint, dan namanya ikut
 * diperjelas supaya tidak ada lagi yang menebak tagihan mana yang dimaksud.
 *
 * Tidak ada foreign key yang ditambahkan, mengikuti keadaan kolom sebelumnya:
 * menambah constraint pada tabel yang sudah ada menuntut rebuild tabel di
 * SQLite dev/test, dan itu di luar lingkup task ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_milestones', function (Blueprint $table) {
            $table->renameColumn('invoice_id', 'customer_invoice_id');
        });
    }

    public function down(): void
    {
        Schema::table('project_milestones', function (Blueprint $table) {
            $table->renameColumn('customer_invoice_id', 'invoice_id');
        });
    }
};
