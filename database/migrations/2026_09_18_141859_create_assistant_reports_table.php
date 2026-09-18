<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();

            $table->timestamp('generated_at');
            $table->string('period', 64);
            $table->string('summary', 1000);
            $table->json('highlights');
            $table->json('recommended_actions');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_reports');
    }
};
