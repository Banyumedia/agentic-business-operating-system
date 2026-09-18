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
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('attachable_type', 191);
            $table->unsignedBigInteger('attachable_id');
            $table->string('file_name', 255);
            $table->string('file_type', 50);
            $table->string('drive_file_id', 191);
            $table->text('drive_url');
            $table->timestamps();

            $table->index('company_id', 'idx_attachments_company');
            $table->index(['attachable_type', 'attachable_id'], 'idx_attachments_polymorphic');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
