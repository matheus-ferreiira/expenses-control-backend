<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_cards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('bank_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 255);
            $table->decimal('limit_amount', 15, 2)->default(0.00);
            $table->smallInteger('closing_day');
            $table->smallInteger('due_day');
            $table->string('color', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_cards');
    }
};
