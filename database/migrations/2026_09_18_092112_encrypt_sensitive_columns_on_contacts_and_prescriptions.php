<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Enlarge columns that will hold encrypted strings (they can get long)
        Schema::table('contacts', function (Blueprint $table) {
            $table->text('name')->change();
            $table->text('wa_number')->nullable()->change();
            $table->text('email')->nullable()->change();
            $table->text('attributes')->nullable()->change();
        });

        Schema::table('prescriptions', function (Blueprint $table) {
            $table->text('doctor_name')->nullable()->change();
            $table->text('doctor_sip')->nullable()->change();
            $table->text('extracted_lines')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('name', 191)->change();
            $table->string('wa_number', 32)->nullable()->change();
            $table->string('email', 191)->nullable()->change();
            $table->json('attributes')->nullable()->change();
        });

        Schema::table('prescriptions', function (Blueprint $table) {
            $table->string('doctor_name', 191)->nullable()->change();
            $table->string('doctor_sip', 64)->nullable()->change();
            $table->json('extracted_lines')->nullable()->change();
        });
    }
};
