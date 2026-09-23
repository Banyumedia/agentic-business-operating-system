<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('title', 191);
            $table->text('content');

            // T-107(b): siapa yang menulis versi saat ini. `bot` menulis lewat
            // API TenantBot dan hanya boleh menambah; `user` menulis lewat web
            // dan boleh menimpa. `created_by_user_id` selalu terisi untuk
            // author_type=user (siapa di web), dan boleh null untuk
            // author_type=bot (bot bukan baris users).
            $table->string('author_type', 8); // bot atau user
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            // T-107(f): baris bertanda sensitif tidak pernah dijawab pada
            // japri staf maupun dikirim ke AI tanpa opt-in owner.
            $table->boolean('sensitive')->default(false);

            $table->timestamps();

            $table->index(['company_id', 'title']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_notes');
    }
};
