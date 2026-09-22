<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menyelaraskan penyimpanan kunci idempoten approval antara dua jalur data.
 *
 * `approval_tickets.schema.json` menyatakan `operation_id` sebagai properti
 * tingkat atas dengan `unique` ter-scope company sejak awal, dan jalur JSON
 * memang menyimpannya begitu. Jalur Eloquent menyimpannya **di dalam** kolom
 * `payload`, jadi kolomnya tidak pernah ada dan index unique-nya tidak pernah
 * dibuat. Akibatnya kunci idempoten approval hanya dijaga oleh
 * `Company::lockForUpdate()` di `ApprovalRequest::executeEloquent()` - benar,
 * tapi satu lapis saja, dan tidak sesuai janji schema.
 *
 * Kolomnya nullable **hanya** karena ini kolom yang ditambahkan ke tabel yang
 * sudah ada; baris lama dibackfill dari `payload`. Index unique tetap bekerja
 * karena NULL boleh berulang di SQLite maupun MySQL, jadi baris tanpa kunci
 * tidak saling memblokir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approval_tickets', function (Blueprint $table) {
            $table->string('operation_id', 64)->nullable()->after('company_id');
        });

        foreach (DB::table('approval_tickets')->select('id', 'payload')->get() as $row) {
            $payload = json_decode((string) $row->payload, true);
            $operationId = is_array($payload) ? ($payload['operation_id'] ?? null) : null;

            if (is_string($operationId) && $operationId !== '') {
                DB::table('approval_tickets')->where('id', $row->id)->update(['operation_id' => $operationId]);
            }
        }

        Schema::table('approval_tickets', function (Blueprint $table) {
            $table->unique(['company_id', 'operation_id'], 'uq_approval_company_operation');
        });
    }

    public function down(): void
    {
        Schema::table('approval_tickets', function (Blueprint $table) {
            $table->dropUnique('uq_approval_company_operation');
            $table->dropColumn('operation_id');
        });
    }
};
