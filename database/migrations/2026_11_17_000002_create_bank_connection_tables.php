<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bank data read from Pluggy (MeuPluggy / Open Finance): a mirror of what the bank reports, plus its
 * reconciliation with the ledger. Ledger records are only created when the user approves a review.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_connections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 20)->default('pluggy');
            $table->string('external_item_id', 64)->unique();
            // Name given by the user; MeuPluggy names every connection "MeuPluggy"
            $table->string('name', 80)->nullable();
            $table->unsignedInteger('connector_id')->nullable();
            $table->string('connector_name', 120);
            $table->string('connector_image_url', 500)->nullable();
            $table->string('connector_color', 10)->nullable();
            $table->string('status', 30);
            $table->string('execution_status', 40)->nullable();
            $table->timestamp('consent_expires_at')->nullable();
            $table->timestamp('provider_updated_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_sync_error', 255)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('bank_connection_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('bank_connection_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('external_account_id', 64);
            $table->string('type', 10);
            $table->string('subtype', 30)->nullable();
            $table->string('name', 160);
            $table->string('number', 40)->nullable();
            // COMPE code from bankData.transferNumber (e.g. 260 = Nubank): names the bank behind MeuPluggy
            $table->string('bank_code', 4)->nullable();
            $table->char('currency_code', 3)->default('BRL');
            $table->bigInteger('balance_cents')->default(0);
            $table->bigInteger('credit_limit_cents')->nullable();
            $table->bigInteger('available_credit_limit_cents')->nullable();
            $table->date('balance_close_date')->nullable();
            $table->date('balance_due_date')->nullable();
            $table->timestamp('transactions_synced_at')->nullable();
            // Ledger account (BANK) or card (CREDIT) this bank account is reconciled with
            $table->foreignUuid('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->foreignUuid('credit_card_id')->nullable()->constrained('credit_cards')->nullOnDelete();
            // Bank transactions before this day are history: never offered for review
            $table->date('import_from')->nullable();
            $table->timestamps();
            $table->unique(['bank_connection_id', 'external_account_id'], 'bank_conn_accounts_external_unique');
            $table->index('user_id');
        });

        Schema::create('bank_connection_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('bank_connection_account_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('external_transaction_id', 64);
            $table->date('date');
            $table->string('description', 255);
            $table->unsignedBigInteger('amount_cents');
            $table->string('direction', 3);
            $table->string('status', 10);
            $table->string('category', 120)->nullable();
            $table->unsignedSmallInteger('installment_number')->nullable();
            $table->unsignedSmallInteger('total_installments')->nullable();
            $table->json('payload');
            // Reconciliation with the ledger: null (account not linked), before_start, new (to review),
            // matched (was already in the ledger), imported (created from it), ignored
            $table->string('review_status', 20)->nullable();
            $table->foreignUuid('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['bank_connection_account_id', 'external_transaction_id'], 'bank_conn_tx_external_unique');
            $table->index(['bank_connection_account_id', 'date'], 'bank_conn_tx_account_date_index');
            $table->index(['user_id', 'date']);
            $table->index(['user_id', 'review_status']);
        });

        // What the user chose for a counterpart ("SAFE2PAY…" → Lanche/Alimentação): suggested next time
        Schema::create('bank_review_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('match_key', 60);
            $table->string('direction', 3);
            $table->string('action', 20);
            $table->foreignUuid('category_id')->nullable()->constrained('transaction_categories')->nullOnDelete();
            $table->foreignUuid('account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->string('description', 255)->nullable();
            $table->boolean('is_reimbursement')->default(false);
            // Shared expense (lunch split with friends): the user's part as a share of the amount
            $table->decimal('own_share_ratio', 5, 4)->nullable();
            $table->unsignedInteger('uses')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'match_key', 'direction']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_review_rules');
        Schema::dropIfExists('bank_connection_transactions');
        Schema::dropIfExists('bank_connection_accounts');
        Schema::dropIfExists('bank_connections');
    }
};
