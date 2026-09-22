<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TX-01 (D-74): kunci fiskal + benahi cacat tarif 0%.
 *
 * Dua perubahan pada `business_identities`:
 *
 * 1. `fiscal_locked_at` — sumber kebenaran eksplisit untuk penguncian pilihan
 *    pajak setelah pendaftaran. Penguncian tidak boleh disimpulkan dari
 *    ada-tidaknya data lain; ia butuh penanda waktunya sendiri.
 *
 * 2. `tax_rate` kehilangan default `0.00`. Default itu membuat tenant `taxable`
 *    yang tak mengisi tarif memungut 0% diam-diam, sementara jalur JSON tanpa
 *    tarif jatuh ke 11% — dua sumber data menjawab pajak berbeda. Kolom menjadi
 *    nullable murni: ketiadaan tarif terbaca sebagai "belum dikonfigurasi"
 *    (null), lalu ditolak untuk tenant taxable, bukan disamarkan sebagai 0%.
 *
 * Portabel Schema Builder (B-01). `change()` menuntut doctrine/dbal pada
 * beberapa versi; bila lingkungan tidak mendukung, kolom `tax_rate` yang sudah
 * nullable tetap aman — yang berubah hanya default, dan penolakan tarif 0/null
 * ditegakkan di lapisan `BusinessIdentityStore`, bukan bergantung pada default
 * kolom.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_identities', function (Blueprint $table): void {
            $table->timestamp('fiscal_locked_at')->nullable()->after('is_default');
        });

        // Turunkan default 0.00 menjadi tanpa default (null). Baris lama yang
        // sudah bernilai 0.00 TIDAK diubah di sini: mengubahnya massal akan
        // mengganti arti data yang sudah ada. Yang berubah hanya perilaku baris
        // baru; identitas lama tetap seperti adanya (TX-01: "identitas lama
        // tidak berubah arti setelah migration").
        if (Schema::hasColumn('business_identities', 'tax_rate')) {
            try {
                Schema::table('business_identities', function (Blueprint $table): void {
                    $table->decimal('tax_rate', 5, 2)->nullable()->default(null)->change();
                });
            } catch (Throwable) {
                // Lingkungan tanpa dukungan change() (mis. driver tertentu):
                // default kolom tidak bisa diturunkan di sini. Penolakan tarif
                // 0/null untuk taxable tetap ditegakkan di BusinessIdentityStore,
                // jadi keamanan fiskal tidak bergantung pada langkah ini.
            }
        }
    }

    public function down(): void
    {
        Schema::table('business_identities', function (Blueprint $table): void {
            $table->dropColumn('fiscal_locked_at');
        });

        if (Schema::hasColumn('business_identities', 'tax_rate')) {
            try {
                Schema::table('business_identities', function (Blueprint $table): void {
                    $table->decimal('tax_rate', 5, 2)->nullable()->default(0.00)->change();
                });
            } catch (Throwable) {
                // Tidak kritis untuk rollback.
            }
        }
    }
};
