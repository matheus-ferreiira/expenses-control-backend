<?php

namespace Tests\Feature\Finance;

use App\Domains\Finance\Actions\ExtendRecurringTransactionsAction;
use App\Domains\Finance\Models\BankAccount;
use App\Domains\Finance\Models\CreditCard;
use App\Domains\Finance\Models\Transaction;
use App\Domains\Finance\Models\TransactionCategory;
use App\Domains\Finance\Services\RecoveryImporter;
use App\Domains\Finance\Services\RecoveryPlan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Regression tests for the defects that hurt daily use (audit probes F-05, F-07, F-10, F-12, F-13, F-14, F-21, F-22, F-23 inverted). */
class DailyUseDefectsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function user(): User
    {
        return User::factory()->create(['timezone' => 'America/Sao_Paulo']);
    }

    // ── D-19: card charges are realized on their date ─────────────────────────

    public function test_card_installments_count_as_spent_once_their_date_arrives(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-14 12:00:00'));
        $user = $this->user();
        $account = BankAccount::factory()->create(['user_id' => $user->id, 'balance' => 1000]);
        $card = CreditCard::factory()->create(['user_id' => $user->id, 'bank_account_id' => $account->id, 'closing_day' => 13, 'due_day' => 20]);
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/finance/transactions', [
            'type' => 'expense', 'amount' => 300, 'description' => 'Monitor', 'transaction_date' => '2026-10-14', 'card_id' => $card->id, 'total_installments' => 3,
        ])->assertCreated();
        $bill = Transaction::factory()->create(['user_id' => $user->id, 'account_id' => $account->id, 'type' => 'expense', 'amount' => '50.00', 'status' => 'pending', 'transaction_date' => '2026-11-10']);

        Carbon::setTestNow(Carbon::parse('2026-11-20 12:00:00'));
        Artisan::call('finance:realize-card-charges');

        $report = $this->actingAs($user, 'sanctum')->getJson('/api/v1/finance/reports/monthly?year=2026&month=11')->assertOk();
        $this->assertEquals(100, $report->json('data.expenses'));
        $this->assertSame('confirmed', Transaction::forUser($user->id)->where('installment_number', 2)->first()->status->value);
        $this->assertSame('pending', Transaction::forUser($user->id)->where('installment_number', 3)->first()->status->value);
        // A bank bill is never confirmed automatically, and card charges never touch the account.
        $this->assertSame('pending', $bill->fresh()->status->value);
        $this->assertSame('1000.00', $account->fresh()->balance);
    }

    // ── D-07: confirmation records what really happened ───────────────────────

    public function test_batch_confirmation_skips_forecasts_that_are_not_due(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-15 12:00:00'));
        $user = $this->user();
        $account = BankAccount::factory()->create(['user_id' => $user->id, 'balance' => 1000]);
        $due = Transaction::factory()->create(['user_id' => $user->id, 'account_id' => $account->id, 'type' => 'expense', 'amount' => '100.00', 'status' => 'pending', 'transaction_date' => '2026-10-10']);
        $future = Transaction::factory()->create(['user_id' => $user->id, 'account_id' => $account->id, 'type' => 'expense', 'amount' => '300.00', 'status' => 'pending', 'transaction_date' => '2026-10-20']);

        $this->actingAs($user, 'sanctum')->patchJson('/api/v1/finance/transactions/confirm-batch', ['ids' => [$due->id, $future->id]])
            ->assertOk()->assertJsonCount(1, 'data');

        $this->assertSame('confirmed', $due->fresh()->status->value);
        $this->assertSame('pending', $future->fresh()->status->value);
        $this->assertSame('900.00', $account->fresh()->balance);
    }

    public function test_confirmation_cannot_be_dated_in_the_future(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-15 12:00:00'));
        $user = $this->user();
        $account = BankAccount::factory()->create(['user_id' => $user->id, 'balance' => 1000]);
        $bill = Transaction::factory()->create(['user_id' => $user->id, 'account_id' => $account->id, 'type' => 'expense', 'amount' => '84.39', 'status' => 'pending', 'transaction_date' => '2026-10-10']);

        $this->actingAs($user, 'sanctum')->patchJson("/api/v1/finance/transactions/{$bill->id}/confirm", ['date' => '2026-10-16'])->assertUnprocessable();
        $this->actingAs($user, 'sanctum')->patchJson("/api/v1/finance/transactions/{$bill->id}/confirm", ['date' => '2026-10-15', 'amount' => 82.80])
            ->assertOk()->assertJsonPath('data.amount', 82.8);

        $this->assertSame('2026-10-15', $bill->fresh()->transaction_date->toDateString());
        $this->assertSame('917.20', $account->fresh()->balance);
    }

    // ── D-18: overdue forecasts stay in the projected balance ─────────────────

    public function test_overdue_forecasts_stay_in_the_projected_balance(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-14 12:00:00'));
        $user = $this->user();
        $account = BankAccount::factory()->create(['user_id' => $user->id, 'balance' => 100]);
        DB::table('account_balance_checkpoints')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'account_id' => $account->id, 'observed_on' => '2026-10-01', 'balance_cents' => 10000, 'kind' => 'opening', 'evidence' => 'test', 'created_at' => now(), 'updated_at' => now()]);
        Transaction::factory()->create(['user_id' => $user->id, 'account_id' => $account->id, 'type' => 'expense', 'amount' => '40.00', 'status' => 'pending', 'transaction_date' => '2026-10-10']);
        Transaction::factory()->create(['user_id' => $user->id, 'account_id' => $account->id, 'type' => 'expense', 'amount' => '25.00', 'status' => 'pending', 'transaction_date' => '2026-10-20']);

        $october = $this->actingAs($user, 'sanctum')->getJson('/api/v1/finance/balance/historical?month=10&year=2026')->assertOk();
        $september = $this->actingAs($user, 'sanctum')->getJson('/api/v1/finance/balance/historical?month=9&year=2026')->assertOk();

        $this->assertEquals(100, $october->json('data.balance'));
        $this->assertEquals(35, $october->json('data.projected_balance'));
        $this->assertEquals($september->json('data.balance'), $september->json('data.projected_balance'));
    }

    // ── D-40: imported records can be classified, never re-valued ─────────────

    private function imported(User $user, BankAccount $account, array $attributes = []): array
    {
        $batch = (string) Str::uuid();
        DB::table('import_batches')->insert(['id' => $batch, 'user_id' => $user->id, 'reference' => 'test', 'plan_hash' => str_repeat('a', 64), 'version' => 4, 'status' => 'applied', 'cutoff' => '2026-10-15']);
        $t = Transaction::factory()->create(['user_id' => $user->id, 'account_id' => $account->id, 'type' => 'expense', 'amount' => '42.00', 'status' => 'confirmed', 'transaction_date' => '2026-10-04', 'description' => 'DEB PIX CHAVE | Fulano', 'category_id' => null, ...$attributes]);
        $t->forceFill(['import_batch_id' => $batch, 'economic_kind' => 'unknown', 'classification_status' => 'pending_category', 'economic_income_cents' => 0, 'economic_expense_cents' => 0])->save();

        return [$t->fresh(), $batch];
    }

    public function test_imported_record_can_be_recategorized_but_not_revalued(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-15 12:00:00'));
        $user = $this->user();
        $account = BankAccount::factory()->create(['user_id' => $user->id]);
        $category = TransactionCategory::factory()->create(['user_id' => $user->id, 'type' => 'expense']);
        [$t] = $this->imported($user, $account);
        $payload = ['type' => 'expense', 'amount' => 42, 'description' => $t->description, 'transaction_date' => '2026-10-04', 'account_id' => $account->id, 'category_id' => $category->id, 'notes' => 'Presente'];

        $this->actingAs($user, 'sanctum')->putJson("/api/v1/finance/transactions/{$t->id}", $payload)->assertOk();
        $this->actingAs($user, 'sanctum')->putJson("/api/v1/finance/transactions/{$t->id}", [...$payload, 'amount' => 50])
            ->assertUnprocessable()->assertJsonValidationErrors(['transaction']);

        $t->refresh();
        $this->assertSame([$category->id, 'Presente', '42.00', 'expense', 4200], [$t->category_id, $t->notes, $t->amount, $t->economic_kind, (int) $t->economic_expense_cents]);
        $report = $this->actingAs($user, 'sanctum')->getJson('/api/v1/finance/reports/monthly?year=2026&month=10')->assertOk();
        $this->assertEquals(42, $report->json('data.expenses'));
    }

    public function test_rollback_keeps_the_user_classification(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-15 12:00:00'));
        $user = $this->user();
        $account = BankAccount::factory()->create(['user_id' => $user->id]);
        $category = TransactionCategory::factory()->create(['user_id' => $user->id, 'type' => 'expense']);
        [$t, $batch] = $this->imported($user, $account);
        // Journal: the batch changed the description; "after" is the row as the batch left it.
        $after = (array) DB::table('transactions')->where('id', $t->id)->first();
        DB::table('import_changes')->insert(['batch_id' => $batch, 'target_table' => 'transactions', 'target_id' => $t->id,
            'before' => json_encode([...$after, 'description' => 'antes']), 'after' => json_encode($after), 'after_hash' => RecoveryPlan::hash($after)]);

        $this->actingAs($user, 'sanctum')->putJson("/api/v1/finance/transactions/{$t->id}", [
            'type' => 'expense', 'amount' => 42, 'description' => $t->description, 'transaction_date' => '2026-10-04', 'account_id' => $account->id, 'category_id' => $category->id,
        ])->assertOk();
        app(RecoveryImporter::class)->rollback($user->id, $batch, false);

        $this->assertSame(['antes', $category->id], [$t->fresh()->description, $t->fresh()->category_id]);
    }

    // ── D-10: budget counts the user's own share ──────────────────────────────

    public function test_budget_spent_uses_the_personal_share(): void
    {
        $user = $this->user();
        $account = BankAccount::factory()->create(['user_id' => $user->id, 'balance' => 1000]);
        $category = TransactionCategory::factory()->create(['user_id' => $user->id, 'type' => 'expense']);
        $base = ['user_id' => $user->id, 'account_id' => $account->id, 'category_id' => $category->id, 'type' => 'expense', 'status' => 'confirmed', 'transaction_date' => today()->toDateString()];
        Transaction::factory()->create([...$base, 'amount' => '83.00'])->forceFill(['economic_kind' => 'shared_expense', 'economic_income_cents' => 0, 'economic_expense_cents' => 2075])->save();
        Transaction::factory()->create([...$base, 'amount' => '100.00'])->forceFill(['reconciliation_status' => 'conflict'])->save();
        Transaction::factory()->create([...$base, 'amount' => '30.00']);

        $budget = $this->actingAs($user, 'sanctum')->postJson('/api/v1/finance/budgets', [
            'month' => today()->month, 'year' => today()->year, 'base_amount' => 5000, 'items' => [['category_id' => $category->id, 'amount' => 300]],
        ])->assertCreated();
        $report = $this->actingAs($user, 'sanctum')->getJson('/api/v1/finance/reports/monthly?year='.today()->year.'&month='.today()->month)->assertOk();

        $this->assertEquals(50.75, $budget->json('data.items.0.spent'));
        $this->assertEquals($report->json('data.expenses'), $budget->json('data.items.0.spent'));
    }

    // ── D-06 / D-04 / D-05: recurrences never rewrite or delete history ──────

    private function series(User $user, BankAccount $account, string $start, array $config, int $amount = 180): array
    {
        $payload = ['type' => 'expense', 'amount' => $amount, 'description' => 'Energia', 'transaction_date' => $start, 'account_id' => $account->id, 'is_recurring' => true, 'recurrence_config' => $config];
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/finance/transactions', $payload)->assertCreated();

        return $payload;
    }

    public function test_scope_all_only_changes_unpaid_occurrences(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-14 12:00:00'));
        $user = $this->user();
        $account = BankAccount::factory()->create(['user_id' => $user->id, 'balance' => 1000]);
        $payload = $this->series($user, $account, '2026-08-18', ['frequency' => 'monthly', 'end_type' => 'never']);
        $this->assertSame('640.00', $account->fresh()->balance);
        $october = Transaction::forUser($user->id)->where('transaction_date', '2026-10-18')->first();

        $this->actingAs($user, 'sanctum')->putJson("/api/v1/finance/transactions/{$october->id}", [...$payload, 'amount' => 210, 'transaction_date' => '2026-10-18', 'scope' => 'all'])->assertOk();

        $this->assertSame('180.00', Transaction::forUser($user->id)->where('transaction_date', '2026-08-18')->first()->amount);
        $this->assertSame('210.00', Transaction::forUser($user->id)->where('transaction_date', '2026-11-18')->first()->amount);
        $this->assertSame('640.00', $account->fresh()->balance);
    }

    public function test_deleting_one_occurrence_keeps_the_series_and_history(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-14 12:00:00'));
        $user = $this->user();
        $account = BankAccount::factory()->create(['user_id' => $user->id, 'balance' => 1000]);
        $this->series($user, $account, '2026-08-18', ['frequency' => 'monthly', 'end_type' => 'count', 'count' => 6], 100);
        $total = Transaction::forUser($user->id)->count();
        $november = Transaction::forUser($user->id)->where('transaction_date', '2026-11-18')->first();

        $this->actingAs($user, 'sanctum')->deleteJson("/api/v1/finance/transactions/{$november->id}")->assertNoContent();
        $this->assertSame($total - 1, Transaction::forUser($user->id)->count());

        $december = Transaction::forUser($user->id)->where('transaction_date', '2026-12-18')->first();
        $this->actingAs($user, 'sanctum')->deleteJson("/api/v1/finance/transactions/{$december->id}?scope=this_and_future")->assertNoContent();
        // August and September (paid) and October (pending, before December) remain.
        $this->assertSame(['2026-08-18', '2026-09-18', '2026-10-18'], Transaction::forUser($user->id)->orderBy('transaction_date')->get()->map(fn ($t) => $t->transaction_date->toDateString())->all());
        $this->assertSame('800.00', $account->fresh()->balance);
    }

    public function test_weekly_series_keeps_its_pattern_on_bulk_edit(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-14 12:00:00'));
        $user = $this->user();
        $account = BankAccount::factory()->create(['user_id' => $user->id, 'balance' => 1000]);
        $payload = $this->series($user, $account, '2026-10-15', ['frequency' => 'weekly', 'end_type' => 'count', 'count' => 8], 40);
        $first = Transaction::forUser($user->id)->orderBy('transaction_date')->first();

        $this->actingAs($user, 'sanctum')->putJson("/api/v1/finance/transactions/{$first->id}", [...$payload, 'amount' => 45, 'transaction_date' => '2026-10-16', 'scope' => 'all'])->assertOk();

        $dates = Transaction::forUser($user->id)->orderBy('transaction_date')->pluck('transaction_date')->map(fn ($d) => $d->toDateString())->all();
        $this->assertSame(['2026-10-16', '2026-10-23', '2026-10-30', '2026-11-06', '2026-11-13', '2026-11-20', '2026-11-27', '2026-12-04'], $dates);
    }

    public function test_extension_follows_the_day_changed_with_this_and_future(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-05 12:00:00'));
        $user = $this->user();
        $account = BankAccount::factory()->create(['user_id' => $user->id, 'balance' => 1000]);
        $payload = $this->series($user, $account, '2026-01-10', ['frequency' => 'monthly', 'end_type' => 'never'], 120);
        $march = Transaction::forUser($user->id)->where('transaction_date', '2026-03-10')->first();
        $this->actingAs($user, 'sanctum')->putJson("/api/v1/finance/transactions/{$march->id}", [...$payload, 'transaction_date' => '2026-03-20', 'scope' => 'this_and_future'])->assertOk();

        Carbon::setTestNow(Carbon::parse('2026-04-05 12:00:00'));
        app(ExtendRecurringTransactionsAction::class)->executeForUser($user->id);

        // Horizon 2027-04-05: the last occurrence stays on the edited day 20 (the old engine went back to day 10).
        $this->assertSame('2027-03-20', Transaction::forUser($user->id)->orderByDesc('transaction_date')->first()->transaction_date->toDateString());
    }

    public function test_extension_keeps_day_31_after_february(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-05 12:00:00'));
        $user = $this->user();
        $account = BankAccount::factory()->create(['user_id' => $user->id, 'balance' => 1000]);
        $group = (string) Str::uuid();
        foreach (['2026-01-31', '2026-02-28'] as $date) {
            Transaction::factory()->create(['user_id' => $user->id, 'account_id' => $account->id, 'status' => 'pending', 'transaction_date' => $date, 'is_recurring' => true, 'recurrence_group_id' => $group, 'recurrence_config' => ['frequency' => 'monthly', 'end_type' => 'count', 'count' => 3]]);
        }

        app(ExtendRecurringTransactionsAction::class)->executeForUser($user->id);

        $this->assertSame('2026-03-31', Transaction::forUser($user->id)->orderByDesc('transaction_date')->first()->transaction_date->toDateString());
    }
}
