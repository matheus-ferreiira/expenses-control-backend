<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_goals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('target_amount', 15, 2);
            $table->decimal('monthly_contribution', 15, 2)->default(0);
            $table->date('deadline')->nullable();
            $table->string('color', 9)->nullable();
            $table->string('icon', 50)->nullable();
            $table->foreignUuid('bank_account_id')->nullable()
                ->constrained('bank_accounts')
                ->nullOnDelete();
            $table->enum('status', ['active', 'completed', 'paused'])->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_goals');
    }
};
