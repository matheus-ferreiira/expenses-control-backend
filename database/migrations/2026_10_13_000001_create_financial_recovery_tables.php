<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained();
            $table->string('reference', 100);
            $table->char('plan_hash', 64);
            $table->unsignedInteger('version');
            $table->string('status', 20);
            $table->date('cutoff');
            $table->json('report')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'reference']);
        });
        Schema::create('import_sources', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('batch_id')->constrained('import_batches');
            $table->foreignUuid('user_id')->constrained();
            $table->string('source_key', 191);
            $table->string('kind', 30);
            $table->text('file');
            $table->char('sha256', 64);
            $table->json('locator');
            $table->json('payload');
            $table->foreignUuid('account_id')->nullable()->constrained('bank_accounts');
            $table->date('posted_on')->nullable();
            $table->bigInteger('cash_cents')->default(0);
            $table->string('reconciliation_status', 30);
            $table->unique(['user_id', 'source_key']);
            $table->index(['user_id', 'account_id', 'posted_on']);
        });
        Schema::create('transaction_sources', function (Blueprint $table) {
            $table->foreignUuid('source_id')->constrained('import_sources');
            $table->foreignUuid('transaction_id')->constrained('transactions');
            $table->string('role', 30);
            $table->primary(['source_id', 'transaction_id']);
        });
        Schema::create('import_changes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignUuid('batch_id')->constrained('import_batches');
            $table->string('target_table', 60);
            $table->uuid('target_id');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->char('after_hash', 64);
            $table->unique(['batch_id', 'target_table', 'target_id']);
        });
        Schema::create('account_balance_checkpoints', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained();
            $table->foreignUuid('account_id')->constrained('bank_accounts');
            $table->foreignUuid('batch_id')->nullable()->constrained('import_batches');
            $table->date('observed_on');
            $table->bigInteger('balance_cents');
            $table->string('kind', 30);
            $table->text('evidence');
            $table->timestamps();
            $table->index(['user_id', 'account_id', 'observed_on']);
        });
        Schema::create('expense_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained();
            $table->foreignUuid('batch_id')->nullable()->constrained('import_batches');
            $table->foreignUuid('transaction_id')->constrained('transactions');
            $table->bigInteger('personal_cents');
            $table->bigInteger('reimbursable_cents');
            $table->bigInteger('repaid_cents')->default(0);
            $table->json('evidence');
            $table->timestamps();
        });
        Schema::create('card_statements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained();
            $table->foreignUuid('card_id')->constrained('credit_cards');
            $table->foreignUuid('batch_id')->nullable()->constrained('import_batches');
            $table->string('cycle', 7);
            $table->date('due_on')->nullable();
            $table->string('status', 20);
            $table->bigInteger('documentary_cents')->nullable();
            $table->bigInteger('manual_cents')->nullable();
            $table->json('evidence');
            $table->timestamps();
            $table->unique(['user_id', 'card_id', 'cycle']);
        });
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignUuid('import_batch_id')->nullable()->constrained('import_batches');
            $table->string('economic_kind', 30)->nullable();
            $table->string('classification_status', 30)->nullable();
            $table->string('reconciliation_status', 30)->nullable();
            $table->date('posted_on')->nullable();
            $table->date('original_purchase_on')->nullable();
            $table->date('statement_due_on')->nullable();
            $table->bigInteger('economic_income_cents')->nullable();
            $table->bigInteger('economic_expense_cents')->nullable();
            $table->bigInteger('signed_card_cents')->nullable();
            $table->string('payment_reference', 100)->nullable();
            $table->unique(['user_id', 'payment_reference']);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['import_batch_id']);
            $table->dropUnique(['user_id', 'payment_reference']);
            $table->dropColumn(['import_batch_id', 'economic_kind', 'classification_status', 'reconciliation_status', 'posted_on', 'original_purchase_on', 'statement_due_on', 'economic_income_cents', 'economic_expense_cents', 'signed_card_cents', 'payment_reference']);
        });
        foreach (['card_statements', 'expense_allocations', 'account_balance_checkpoints', 'import_changes', 'transaction_sources', 'import_sources', 'import_batches'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
