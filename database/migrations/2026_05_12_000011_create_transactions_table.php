<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->foreignUuid('card_id')->nullable()->constrained('credit_cards')->nullOnDelete();
            $table->foreignUuid('category_id')->nullable()->constrained('transaction_categories')->nullOnDelete();
            $table->string('type', 20);
            $table->decimal('amount', 15, 2);
            $table->string('description', 500);
            $table->text('notes')->nullable();
            $table->date('transaction_date');
            $table->boolean('is_recurring')->default(false);
            $table->json('recurrence_config')->nullable();
            $table->smallInteger('installment_number')->nullable();
            $table->smallInteger('total_installments')->nullable();
            $table->uuid('installment_group_id')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['user_id', 'transaction_date']);
            $table->index(['user_id', 'type']);
            $table->index(['user_id', 'account_id']);
            $table->index(['user_id', 'card_id']);
            $table->index('installment_group_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
