<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->foreignId('contact_id')->nullable()->change();
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('contact_id')->nullable()->change();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('contact_id')->nullable()->change();
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('contact_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('contact_id')->nullable(false)->change();
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('contact_id')->nullable(false)->change();
        });
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('contact_id')->nullable(false)->change();
        });
        Schema::table('deals', function (Blueprint $table) {
            $table->foreignId('contact_id')->nullable(false)->change();
        });
    }
};
