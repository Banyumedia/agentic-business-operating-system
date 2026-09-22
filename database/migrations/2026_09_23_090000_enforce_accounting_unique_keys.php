<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menegakkan deklarasi `unique` yang sudah lama ada di katalog schema tapi tidak
 * pernah dibuat di basis data.
 *
 * `chart_of_accounts.schema.json` dan `accounting_journals.schema.json`
 * menyatakan `unique` dengan `company_scoped: true` sejak T-54, tetapi
 * migration-nya hanya membuat index biasa pada `company_id`. Validator schema
 * tidak menutup celah ini karena ia memeriksa bentuk satu baris, bukan
 * keberadaan baris lain. Hasilnya dua akun berkode sama atau dua jurnal bernomor
 * sama bisa hidup berdampingan dalam satu usaha - laporan keuangan akan
 * menggandakan angka tanpa ada yang salah secara teknis.
 *
 * Kuncinya ter-scope `company_id` (D-26): bagan akun standar yang sama boleh
 * dipakai banyak usaha, yang dilarang hanya duplikat di dalam satu usaha.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->unique(['company_id', 'account_code'], 'uq_coa_company_account_code');
        });

        Schema::table('accounting_journals', function (Blueprint $table) {
            $table->unique(['company_id', 'journal_number'], 'uq_journal_company_number');
        });
    }

    public function down(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->dropUnique('uq_coa_company_account_code');
        });

        Schema::table('accounting_journals', function (Blueprint $table) {
            $table->dropUnique('uq_journal_company_number');
        });
    }
};
