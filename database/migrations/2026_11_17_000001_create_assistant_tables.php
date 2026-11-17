<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 120)->default('Nova conversa');
            $table->timestamps();
            $table->index(['user_id', 'updated_at']);
        });
        Schema::create('assistant_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('conversation_id')->constrained('assistant_conversations')->cascadeOnDelete();
            $table->uuid('request_id')->nullable();
            $table->string('role', 16);
            $table->text('content');
            $table->json('actions')->nullable();
            $table->string('status', 20)->nullable();
            $table->json('results')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->unique(['conversation_id', 'request_id', 'role']);
            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_messages');
        Schema::dropIfExists('assistant_conversations');
    }
};
