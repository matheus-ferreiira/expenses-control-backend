<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('category_id')->nullable()->constrained('price_categories')->nullOnDelete();
            $table->string('name', 150);
            $table->string('brand', 100)->nullable();
            $table->string('model', 150)->nullable();
            $table->text('specs')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('target_price', 15, 2)->nullable();
            $table->decimal('launch_price', 15, 2)->nullable();
            $table->string('status', 20)->default('tracking');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index('category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_products');
    }
};
