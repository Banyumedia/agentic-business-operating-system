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
        Schema::create('hermes_conversation_contexts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hermes_profile_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 16);
            $table->string('chat_id', 128);
            $table->foreignId('active_company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->timestamp('updated_at')->nullable();

            $table->unique(['hermes_profile_id', 'channel', 'chat_id'], 'idx_hermes_conv_context_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hermes_conversation_contexts');
    }
};
