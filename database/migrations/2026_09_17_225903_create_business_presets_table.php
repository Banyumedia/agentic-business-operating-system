<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_presets', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('name');
            // Nilai nyata berupa label tier ("starter", "professional", dst),
            // bukan kode 1 huruf - varchar(1) truncate silent di SQLite tapi
            // MySQL strict mode menolaknya (1406). B-01/T-21b.
            $table->string('tier', 32);
            $table->json('definition');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_presets');
    }
};
