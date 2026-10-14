<?php

namespace Tests\Feature\Finance;

use App\Domains\Finance\Actions\ConfirmTransactionAction;
use App\Domains\Finance\Actions\PayCardStatementAction;
use App\Domains\Finance\Models\BankAccount;
use App\Domains\Finance\Models\CreditCard;
use App\Domains\Finance\Models\Transaction;
use App\Domains\Finance\Services\CardStatementSummary;
use App\Domains\Finance\Services\FinanceReportService;
use App\Domains\Finance\Services\FinancialEffects;
use App\Domains\Finance\Services\RecoveryImporter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RecoveryFinancialEffectsTest extends TestCase
{
    use RefreshDatabase;

    public function test_reimbursements_and_unknown_nature_do_not_inflate_income(): void
    {
        $user = User::factory()->create();
        foreach (['reimbursement', 'unknown'] as $kind) {
            $t = Transaction::factory()->create(['user_id' => $user->id, 'type' => 'income', 'amount' => '62.25', 'status' => 'confirmed', 'transaction_date' => today()]);
            $t->forceFill(['economic_kind' => $kind])->save();
        }
        $this->assertSame(0.0, app(FinanceReportService::class)->getMonthlySummary($user, now()->year, now()->month)['income']);
    }

    public function test_shared_expense_and_refund_use_signed_personal_economic_effect(): void
    {
        $expense = new Transaction(['type' => 'expense', 'amount' => '83.00']);
        $expense->forceFill(['economic_kind' => 'shared_expense', 'economic_income_cents' => 0, 'economic_expense_cents' => 2075]);
        $this->assertSame(2075, app(FinancialEffects::class)->economic($expense)['expense']);
        $expense->forceFill(['economic_kind' => 'fee_refund', 'economic_expense_cents' => -40]);
        $this->assertSame(-40, app(FinancialEffects::class)->economic($expense)['expense']);
        $this->assertSame('-0.40', FinancialEffects::decimal(-40));
    }

    public function test_partial_payments_and_exact_replay_debit_once_per_reference(): void
    {
        $user = User::factory()->create();
        $account = BankAccount::factory()->create(['user_id' => $user->id, 'balance' => 1000]);
        $card = CreditCard::factory()->create(['user_id' => $user->id, 'bank_account_id' => $account->id]);
        $pay = app(PayCardStatementAction::class);
        $reference = (string) Str::uuid();
        $a = $pay->execute($user, $card, $account->id, 50, now()->format('Y-m'), null, $reference);
        $b = $pay->execute($user, $card, $account->id, 50, now()->format('Y-m'), null, $reference);
        $pay->execute($user, $card, $account->id, 50, now()->format('Y-m'), null, (string) Str::uuid());
        $this->assertSame($a->id, $b->id);
        $this->assertSame('900.00', $account->fresh()->balance);
        $this->assertSame(2, Transaction::forUser($user->id)->count());
    }

    public function test_stale_confirmation_does_not_debit_twice(): void
    {
        $user = User::factory()->create();
        $account = BankAccount::factory()->create(['user_id' => $user->id, 'balance' => 1000]);
        $transaction = Transaction::factory()->create(['user_id' => $user->id, 'account_id' => $account->id, 'type' => 'expense', 'amount' => 100, 'status' => 'pending']);
        $stale = $transaction->fresh();
        app(ConfirmTransactionAction::class)->execute($transaction);
        app(ConfirmTransactionAction::class)->execute($stale);
        $this->assertSame('900.00', $account->fresh()->balance);
    }

    public function test_balance_observation_creates_no_income_or_expense_and_is_scoped(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $account = BankAccount::factory()->create(['user_id' => $user->id, 'balance' => 85]);
        $url = '/api/v1/finance/accounts/'.$account->id.'/balance-observations';
        $payload = ['date' => today()->toDateString(), 'balance' => '80.00', 'evidence' => 'Manual confirmation'];
        $this->actingAs($other, 'sanctum')->postJson($url, $payload)->assertForbidden();
        $this->actingAs($user, 'sanctum')->postJson($url, $payload)->assertCreated()->assertJsonPath('data.calculated_cents', null);
        $this->assertSame(0, Transaction::forUser($user->id)->count());
        $this->assertSame('85.00', $account->fresh()->balance);
    }

    public function test_statement_cycle_overrides_csv_date_and_partial_payment_leaves_residual(): void
    {
        $user = User::factory()->create();
        $account = BankAccount::factory()->create(['user_id' => $user->id]);
        $card = CreditCard::factory()->create(['user_id' => $user->id, 'bank_account_id' => $account->id, 'closing_day' => 13, 'due_day' => 20]);
        Transaction::factory()->create(['user_id' => $user->id, 'account_id' => null, 'card_id' => $card->id, 'statement_month' => '2026-11', 'transaction_date' => '2026-10-13', 'type' => 'expense', 'amount' => '525.67', 'status' => 'confirmed']);
        app(PayCardStatementAction::class)->execute($user, $card, $account->id, 100, '2026-11', '2026-10-13', (string) Str::uuid());
        $summary = app(CardStatementSummary::class)->get($card, '2026-11');
        $this->assertSame(52567, $summary['charges_cents']);
        $this->assertSame(42567, $summary['remaining_cents']);
        $october = app(CardStatementSummary::class)->get($card, '2026-10');
        $this->assertSame(0, $october['charges_cents']);
        Transaction::factory()->create(['user_id' => $user->id, 'account_id' => null, 'card_id' => $card->id, 'statement_month' => null, 'transaction_date' => '2026-10-01', 'type' => 'transfer', 'amount' => '183.25', 'status' => 'confirmed']);
        $october = app(CardStatementSummary::class)->get($card, '2026-10');
        $this->assertSame(0, $october['paid_cents']);
    }

    public function test_listing_does_not_generate_or_prune_recurrences(): void
    {
        $user = User::factory()->create();
        Transaction::factory()->create(['user_id' => $user->id, 'is_recurring' => true, 'recurrence_group_id' => (string) Str::uuid(), 'recurrence_config' => ['frequency' => 'monthly', 'end_type' => 'never']]);
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/finance/transactions')->assertOk();
        $this->assertSame(1, Transaction::forUser($user->id)->count());
    }

    public function test_importer_rejects_production_or_nonlocal_configuration_before_queries(): void
    {
        config(['database.connections.mysql.host' => 'production.invalid']);
        $this->expectException(\RuntimeException::class);
        app(RecoveryImporter::class)->apply([]);
    }

    public function test_remote_approval_requires_exact_plan_hash(): void
    {
        config(['database.connections.mysql.host' => 'production.invalid',
            'finance_recovery.approved_target_enabled' => true,
            'finance_recovery.approved_host' => 'production.invalid',
            'finance_recovery.approved_database' => config('database.connections.mysql.database'),
            'finance_recovery.approved_user' => 'audited-user',
            'finance_recovery.approved_plan_sha256' => str_repeat('a', 64)]);
        $this->expectException(\RuntimeException::class);
        app(RecoveryImporter::class)->apply(['user_id' => 'audited-user', 'plan_hash' => str_repeat('b', 64)]);
    }

    public function test_future_occurrence_can_be_edited_without_rewriting_imported_history(): void
    {
        $user = User::factory()->create();
        $batch = (string) Str::uuid();
        $group = (string) Str::uuid();
        \Illuminate\Support\Facades\DB::table('import_batches')->insert(['id' => $batch, 'user_id' => $user->id, 'reference' => 'fixture', 'plan_hash' => str_repeat('a', 64), 'version' => 3, 'status' => 'applied', 'cutoff' => today()->toDateString()]);
        $past = Transaction::factory()->create(['user_id' => $user->id, 'recurrence_group_id' => $group, 'transaction_date' => today()->subMonth()]);
        $past->forceFill(['import_batch_id' => $batch])->save();
        $future = Transaction::factory()->create(['user_id' => $user->id, 'recurrence_group_id' => $group, 'transaction_date' => today()->addMonth(), 'status' => 'pending']);
        \App\Domains\Finance\Services\RecoveryRecordGuard::editable($future);
        \App\Domains\Finance\Services\RecoveryRecordGuard::editable($future, 'this_and_future');
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        \App\Domains\Finance\Services\RecoveryRecordGuard::editable($future, 'all');
    }

    public function test_confirming_imported_bank_candidate_cannot_duplicate_source_cash(): void
    {
        $user = User::factory()->create();
        $account = BankAccount::factory()->create(['user_id' => $user->id, 'balance' => '0.66']);
        $batch = (string) Str::uuid();
        \Illuminate\Support\Facades\DB::table('import_batches')->insert(['id' => $batch, 'user_id' => $user->id, 'reference' => 'candidate-fixture', 'plan_hash' => str_repeat('a', 64), 'version' => 3, 'status' => 'applied', 'cutoff' => today()->toDateString()]);
        $candidate = Transaction::factory()->create(['user_id' => $user->id, 'account_id' => $account->id, 'type' => 'expense', 'amount' => '100.00', 'status' => 'pending']);
        $candidate->forceFill(['import_batch_id' => $batch, 'reconciliation_status' => 'conflict'])->save();
        try {
            app(ConfirmTransactionAction::class)->execute($candidate);
            $this->fail('Imported cash was applied twice');
        } catch (\Illuminate\Validation\ValidationException) {
            $this->assertSame('0.66', $account->fresh()->balance);
            $this->assertSame('pending', $candidate->fresh()->status->value);
        }
    }
}
