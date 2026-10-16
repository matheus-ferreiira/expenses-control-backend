<?php

namespace Tests\Feature\Finance;

use App\Domains\Finance\Models\BankAccount;
use App\Domains\Finance\Models\CreditCard;
use App\Domains\Finance\Models\FinanceGoal;
use App\Domains\Finance\Models\Transaction;
use App\Domains\Finance\Models\TransactionCategory;
use App\Domains\Finance\Models\TransactionTag;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/** Regression tests for the audit defects fixed after the daily-use batch (D-08 … D-42). */
class RemainingDefectsTest extends TestCase
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

    // ── D-34: tests never run against a remote database ───────────────────────

    public function test_tests_refuse_a_non_local_database(): void
    {
        config(['database.connections.mysql.host' => 'production.invalid']);
        $this->expectException(RuntimeException::class);
        (fn () => $this->setUpTraits())->call($this);
    }

    // ── D-39: deploy migrations need approval ─────────────────────────────────

    public function test_release_migrate_is_a_no_op_without_pending_migrations(): void
    {
        $this->assertSame(0, Artisan::call('app:release-migrate'));
        $this->assertStringContainsString('Nenhuma migration pendente', Artisan::output());
    }

    // ── D-08 / D-09: another user's records are rejected ──────────────────────

    public function test_foreign_ids_are_rejected_in_goals_budgets_and_cards(): void
    {
        $user = $this->user();
        $other = $this->user();
        $foreignAccount = BankAccount::factory()->create(['user_id' => $other->id]);
        $foreignCategory = TransactionCategory::factory()->create(['user_id' => $other->id, 'type' => 'expense']);
        $card = CreditCard::factory()->create(['user_id' => $user->id, 'bank_account_id' => BankAccount::factory()->create(['user_id' => $user->id])->id]);

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/finance/goals', ['name' => 'Viagem', 'target_amount' => 1000, 'bank_account_id' => $foreignAccount->id])
            ->assertUnprocessable()->assertJsonValidationErrors(['bank_account_id']);
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/finance/budgets', ['month' => 11, 'year' => 2026, 'base_amount' => 5000, 'items' => [['category_id' => $foreignCategory->id, 'amount' => 100]]])
            ->assertUnprocessable()->assertJsonValidationErrors(['items.0.category_id']);
        $this->actingAs($user, 'sanctum')->patchJson("/api/v1/finance/cards/{$card->id}", ['bank_account_id' => $foreignAccount->id])
            ->assertUnprocessable()->assertJsonValidationErrors(['bank_account_id']);
    }

    // ── D-24: one budget per month, with a clear error ────────────────────────

    public function test_second_budget_for_the_same_month_is_a_validation_error(): void
    {
        $user = $this->user();
        $payload = ['month' => 11, 'year' => 2026, 'base_amount' => 5000];
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/finance/budgets', $payload)->assertCreated();
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/finance/budgets', $payload)->assertUnprocessable()->assertJsonValidationErrors(['month']);
        // Another user may have their own November budget.
        $this->actingAs($this->user(), 'sanctum')->postJson('/api/v1/finance/budgets', $payload)->assertCreated();
    }

    // ── D-25: accounts and cards with history are archived, not deleted ───────

    public function test_account_and_card_with_history_cannot_be_deleted(): void
    {
        $user = $this->user();
        $used = BankAccount::factory()->create(['user_id' => $user->id, 'balance' => 300]);
        $unused = BankAccount::factory()->create(['user_id' => $user->id]);
        $card = CreditCard::factory()->create(['user_id' => $user->id, 'bank_account_id' => $used->id]);
        Transaction::factory()->create(['user_id' => $user->id, 'account_id' => $used->id, 'status' => 'confirmed']);
        Transaction::factory()->create(['user_id' => $user->id, 'card_id' => $card->id, 'account_id' => null, 'status' => 'confirmed']);

        $this->actingAs($user, 'sanctum')->deleteJson("/api/v1/finance/accounts/{$used->id}")->assertUnprocessable();
        $this->actingAs($user, 'sanctum')->deleteJson("/api/v1/finance/cards/{$card->id}")->assertUnprocessable();
        $this->actingAs($user, 'sanctum')->deleteJson("/api/v1/finance/accounts/{$unused->id}")->assertNoContent();
        $this->assertNotSoftDeleted($used);
        $this->assertNotSoftDeleted($card);
    }

    // ── D-26: no one-click wipe of everything ─────────────────────────────────

    public function test_reset_all_data_no_longer_exists(): void
    {
        $user = $this->user();
        Transaction::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user, 'sanctum')->deleteJson('/api/v1/auth/reset-data')->assertNotFound();
        $this->assertSame(1, Transaction::forUser($user->id)->count());
    }

    // ── D-23 / D-27: clear validation instead of 500 or mixed types ──────────

    public function test_partial_update_and_wrong_category_type_are_validation_errors(): void
    {
        $user = $this->user();
        $account = BankAccount::factory()->create(['user_id' => $user->id, 'balance' => 100]);
        $salary = TransactionCategory::factory()->create(['user_id' => $user->id, 'type' => 'income']);
        $t = Transaction::factory()->create(['user_id' => $user->id, 'account_id' => $account->id, 'type' => 'expense', 'status' => 'confirmed']);

        $this->actingAs($user, 'sanctum')->putJson("/api/v1/finance/transactions/{$t->id}", ['amount' => 10])
            ->assertUnprocessable()->assertJsonValidationErrors(['type', 'description', 'transaction_date']);
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/finance/transactions', [
            'type' => 'expense', 'amount' => 10, 'description' => 'Almoço', 'transaction_date' => today()->toDateString(), 'account_id' => $account->id, 'category_id' => $salary->id,
        ])->assertUnprocessable()->assertJsonValidationErrors(['category_id']);
    }

    // ── D-15 / D-16: goals measure money put aside ────────────────────────────

    public function test_goal_without_contributions_is_not_on_track(): void
    {
        $user = $this->user();
        $goal = FinanceGoal::factory()->create(['user_id' => $user->id, 'target_amount' => 7000, 'monthly_contribution' => 500]);
        $goal->forceFill(['created_at' => now()->subMonths(3)])->save();

        $data = $this->actingAs($user, 'sanctum')->getJson('/api/v1/finance/goals')->assertOk()->json('data.0');

        $this->assertEquals(0, $data['current_amount']);
        $this->assertFalse($data['on_track']);
    }

    // ── D-17: accounts without a verified opening still count today ───────────

    public function test_projected_balance_includes_unverified_accounts_and_new_accounts_get_an_opening(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-15 12:00:00', 'America/Sao_Paulo'));
        $user = $this->user();
        $bank = BankAccount::factory()->create(['user_id' => $user->id, 'balance' => 100]);
        DB::table('account_balance_checkpoints')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'account_id' => $bank->id, 'observed_on' => '2026-10-01', 'balance_cents' => 10000, 'kind' => 'opening', 'evidence' => 'test', 'created_at' => now(), 'updated_at' => now()]);
        BankAccount::factory()->create(['user_id' => $user->id, 'name' => 'Carteira', 'balance' => 85]);

        $october = $this->actingAs($user, 'sanctum')->getJson('/api/v1/finance/balance/historical?month=10&year=2026')->assertOk()->json('data');
        $this->assertEquals(185, $october['balance']);
        $this->assertEquals(185, $october['projected_balance']);
        $this->assertCount(1, $october['unverified_accounts']);

        $created = $this->actingAs($user, 'sanctum')->postJson('/api/v1/finance/accounts', ['name' => 'Inter', 'type' => 'checking', 'balance' => 50])->assertCreated()->json('data.id');
        $this->assertDatabaseHas('account_balance_checkpoints', ['account_id' => $created, 'kind' => 'opening', 'balance_cents' => 5000, 'observed_on' => '2026-10-15']);
    }

    // ── D-28 / D-29: recurrences keep destination and tags ────────────────────

    public function test_recurring_transfer_keeps_destination_and_tags_follow_every_occurrence(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-15 12:00:00', 'America/Sao_Paulo'));
        $user = $this->user();
        $from = BankAccount::factory()->create(['user_id' => $user->id, 'balance' => 1000]);
        $to = BankAccount::factory()->create(['user_id' => $user->id, 'balance' => 0]);
        $card = CreditCard::factory()->create(['user_id' => $user->id, 'bank_account_id' => $from->id]);
        $tag = TransactionTag::create(['user_id' => $user->id, 'name' => 'Casa', 'color' => '#22c55e']);

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/finance/transactions', [
            'type' => 'transfer', 'amount' => 100, 'description' => 'Reserva', 'transaction_date' => '2026-10-15', 'account_id' => $from->id, 'destination_account_id' => $to->id,
            'is_recurring' => true, 'recurrence_config' => ['frequency' => 'monthly', 'end_type' => 'count', 'count' => 3], 'tag_ids' => [$tag->id],
        ])->assertCreated();
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/finance/transactions', [
            'type' => 'expense', 'amount' => 300, 'description' => 'Monitor', 'transaction_date' => '2026-10-15', 'card_id' => $card->id, 'total_installments' => 3, 'tag_ids' => [$tag->id],
        ])->assertCreated();

        $series = Transaction::forUser($user->id)->where('type', 'transfer')->get();
        $this->assertCount(3, $series);
        $this->assertTrue($series->every(fn ($t) => $t->destination_account_id === $to->id && $t->tags()->count() === 1));
        $this->assertSame(3, DB::table('transaction_transaction_tag')->whereIn('transaction_id', Transaction::forUser($user->id)->whereNotNull('installment_group_id')->pluck('id'))->count());
        $this->assertSame('100.00', $to->fresh()->balance);
    }

    // ── D-30: "today" is the user's day, not the server's (UTC) ──────────────

    public function test_late_evening_entry_for_tomorrow_stays_pending(): void
    {
        // 22:30 in Brasília on 15/10 is already 16/10 01:30 UTC.
        Carbon::setTestNow(Carbon::parse('2026-10-16 01:30:00', 'UTC'));
        $user = $this->user();
        $account = BankAccount::factory()->create(['user_id' => $user->id, 'balance' => 100]);

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/finance/transactions', [
            'type' => 'expense', 'amount' => 30, 'description' => 'Conta de amanhã', 'transaction_date' => '2026-10-16', 'account_id' => $account->id,
        ])->assertCreated()->assertJsonPath('data.status', 'pending');
        $this->assertSame('100.00', $account->fresh()->balance);
    }

    // ── D-31 / D-14: rounded money and the month's largest expense ────────────

    public function test_monthly_report_is_rounded_and_names_the_largest_expense(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-15 12:00:00', 'America/Sao_Paulo'));
        $user = $this->user();
        $account = BankAccount::factory()->create(['user_id' => $user->id]);
        $base = ['user_id' => $user->id, 'account_id' => $account->id, 'status' => 'confirmed', 'transaction_date' => '2026-10-10'];
        Transaction::factory()->create([...$base, 'type' => 'income', 'amount' => '0.30']);
        Transaction::factory()->create([...$base, 'type' => 'expense', 'amount' => '0.10', 'description' => 'Bala']);
        Transaction::factory()->create([...$base, 'type' => 'expense', 'amount' => '98.00', 'description' => 'Almoço'])->forceFill(['economic_kind' => 'shared_expense', 'economic_income_cents' => 0, 'economic_expense_cents' => 2100])->save();
        Transaction::factory()->create([...$base, 'type' => 'expense', 'amount' => '50.00', 'description' => 'Mercado']);

        $report = $this->actingAs($user, 'sanctum')->getJson('/api/v1/finance/reports/monthly?year=2026&month=10')->assertOk()->json('data');

        $this->assertSame(round(0.30 - 71.10, 2), $report['balance']);
        $this->assertSame(['Mercado', 50], [$report['largest_expense']['description'], $report['largest_expense']['amount']]);
    }

    // ── D-11 / D-13: chart and report figures come from the backend ───────────

    public function test_series_and_category_totals_follow_the_economic_policy(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-15 12:00:00', 'America/Sao_Paulo'));
        $user = $this->user();
        $account = BankAccount::factory()->create(['user_id' => $user->id]);
        $food = TransactionCategory::factory()->create(['user_id' => $user->id, 'type' => 'expense', 'name' => 'Alimentação']);
        $base = ['user_id' => $user->id, 'account_id' => $account->id, 'status' => 'confirmed'];
        Transaction::factory()->create([...$base, 'type' => 'income', 'amount' => '2500.00', 'transaction_date' => '2026-10-15']);
        Transaction::factory()->create([...$base, 'type' => 'income', 'amount' => '21.00', 'transaction_date' => '2026-10-15'])->forceFill(['economic_kind' => 'reimbursement', 'economic_income_cents' => 0, 'economic_expense_cents' => 0])->save();
        Transaction::factory()->create([...$base, 'type' => 'expense', 'amount' => '98.00', 'category_id' => $food->id, 'transaction_date' => '2026-10-14'])->forceFill(['economic_kind' => 'shared_expense', 'economic_income_cents' => 0, 'economic_expense_cents' => 2100])->save();
        Transaction::factory()->create([...$base, 'type' => 'expense', 'amount' => '60.00', 'status' => 'pending', 'transaction_date' => '2026-10-14']);

        $days = $this->actingAs($user, 'sanctum')->getJson('/api/v1/finance/reports/series?start_date=2026-10-13&end_date=2026-10-15&group=day')->assertOk()->json('data');
        $months = $this->actingAs($user, 'sanctum')->getJson('/api/v1/finance/reports/series?start_date=2026-09-01&end_date=2026-10-31&group=month')->assertOk()->json('data');
        $categories = $this->actingAs($user, 'sanctum')->getJson('/api/v1/finance/reports/categories?start_date=2026-10-01&end_date=2026-10-31')->assertOk()->json('data');

        $this->assertEquals([['2026-10-13', 0, 0], ['2026-10-14', 0, 21], ['2026-10-15', 2500, 0]], array_map(fn ($d) => [$d['period'], $d['income'], $d['expenses']], $days));
        $this->assertEquals([['2026-09', 0, 0], ['2026-10', 2500, 21]], array_map(fn ($m) => [$m['period'], $m['income'], $m['expenses']], $months));
        $this->assertEquals([['Alimentação', 21]], array_map(fn ($c) => [$c['category'], $c['total']], $categories));
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/finance/reports/series?start_date=2025-01-01&end_date=2026-10-31&group=day')->assertUnprocessable();
    }

    // ── D-36 + budget delete: budget reads the way it should ──────────────────

    public function test_budget_shows_exact_payment_on_target_and_spending_outside_it(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-15 12:00:00', 'America/Sao_Paulo'));
        $user = $this->user();
        $account = BankAccount::factory()->create(['user_id' => $user->id, 'balance' => 1000]);
        $home = TransactionCategory::factory()->create(['user_id' => $user->id, 'type' => 'expense']);
        $fun = TransactionCategory::factory()->create(['user_id' => $user->id, 'type' => 'expense']);
        $base = ['user_id' => $user->id, 'account_id' => $account->id, 'type' => 'expense', 'status' => 'confirmed', 'transaction_date' => '2026-10-10'];
        Transaction::factory()->create([...$base, 'amount' => '700.00', 'category_id' => $home->id]);
        Transaction::factory()->create([...$base, 'amount' => '40.00', 'category_id' => $fun->id]);
        Transaction::factory()->create([...$base, 'amount' => '15.00', 'category_id' => null]);

        $budget = $this->actingAs($user, 'sanctum')->postJson('/api/v1/finance/budgets', [
            'month' => 10, 'year' => 2026, 'base_amount' => 5000, 'items' => [['category_id' => $home->id, 'amount' => 700]],
        ])->assertCreated()->json('data');

        $this->assertSame('on_track', $budget['items'][0]['status']);
        $this->assertEquals([40, 15], [$budget['summary']['unbudgeted_spent'], $budget['summary']['uncategorized_spent']]);
        $this->assertEquals(700, $home->fresh()->monthly_limit);

        $this->actingAs($user, 'sanctum')->deleteJson("/api/v1/finance/budgets/{$budget['id']}")->assertNoContent();
        $this->assertNull($home->fresh()->monthly_limit);
    }

    // ── Reimbursements are not income ─────────────────────────────────────────

    public function test_reimbursement_is_not_counted_as_income_and_can_be_unmarked(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-15 12:00:00', 'America/Sao_Paulo'));
        $user = $this->user();
        $account = BankAccount::factory()->create(['user_id' => $user->id, 'balance' => 0]);
        $payload = ['type' => 'income', 'amount' => 21, 'description' => 'Luyny almoço', 'transaction_date' => '2026-10-15', 'account_id' => $account->id, 'is_reimbursement' => true];
        $id = $this->actingAs($user, 'sanctum')->postJson('/api/v1/finance/transactions', $payload)->assertCreated()->json('data.id');

        $report = fn () => $this->actingAs($user, 'sanctum')->getJson('/api/v1/finance/reports/monthly?year=2026&month=10')->json('data.income');
        $this->assertEquals(0, $report());
        $this->assertSame('21.00', $account->fresh()->balance);

        $this->actingAs($user, 'sanctum')->putJson("/api/v1/finance/transactions/{$id}", [...$payload, 'is_reimbursement' => false])->assertOk();
        $this->assertEquals(21, $report());
    }
}
