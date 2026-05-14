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
        Schema::create('notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 500)->default('');
            $table->longText('content')->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_favorite')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->timestamp('last_viewed_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['user_id', 'is_pinned']);
            $table->index(['user_id', 'is_favorite']);
            $table->index(['user_id', 'archived_at']);
            $table->index(['user_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};
