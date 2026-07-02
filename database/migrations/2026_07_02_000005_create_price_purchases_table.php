<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_purchases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('price_products')->cascadeOnDelete();
            $table->foreignUuid('store_id')->nullable()->constrained('price_stores')->nullOnDelete();
            $table->decimal('price_paid', 15, 2);
            $table->date('purchased_at');
            $table->unsignedSmallInteger('warranty_months')->nullable();
            $table->decimal('current_value', 15, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'purchased_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_purchases');
    }
};
