<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('price_products')->cascadeOnDelete();
            $table->foreignUuid('store_id')->constrained('price_stores')->cascadeOnDelete();
            $table->decimal('price', 15, 2);
            $table->date('recorded_at');
            $table->string('url', 500)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_id', 'recorded_at']);
            $table->index(['store_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_records');
    }
};
