<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopping_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('shopping_session_id')->constrained()->cascadeOnDelete();
            $table->uuid('user_id')->index();
            $table->string('name');
            $table->string('category')->nullable();
            $table->boolean('is_bought')->default(false);
            $table->decimal('price', 10, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['shopping_session_id', 'is_bought']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopping_items');
    }
};
