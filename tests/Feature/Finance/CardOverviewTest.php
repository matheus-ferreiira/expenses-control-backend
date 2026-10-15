<?php

namespace Tests\Feature\Finance;

use App\Domains\Finance\Models\BankAccount;
use App\Domains\Finance\Models\CreditCard;
use App\Domains\Finance\Models\Transaction;
use App\Domains\Finance\Services\CardCycle;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CardOverviewTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Card like the user's Nubank: closes on 13, due on 20, limit 3.200. */
    private function scenario(): array
    {
        $user = User::factory()->create(['timezone' => 'America/Sao_Paulo']);
        $account = BankAccount::factory()->create(['user_id' => $user->id, 'balance' => 5000]);
        $card = CreditCard::factory()->create(['user_id' => $user->id, 'bank_account_id' => $account->id, 'closing_day' => 13, 'due_day' => 20, 'limit_amount' => 3200]);
        $charge = fn (string $cycle, string $date, string $amount, string $type = 'expense') => Transaction::factory()->create([
            'user_id' => $user->id, 'account_id' => null, 'card_id' => $card->id, 'statement_month' => $cycle,
            'type' => $type, 'amount' => $amount, 'transaction_date' => $date, 'status' => 'confirmed',
        ]);
        $charge('2026-07', '2026-06-20', '139.11');
        $charge('2026-09', '2026-08-26', '183.25');
        $charge('2026-10', '2026-09-26', '183.25');
        $charge('2026-10', '2026-10-08', '173.40');
        $charge('2026-10', '2026-10-12', '203.50');
        $charge('2026-11', '2026-10-26', '183.25');
        $charge('2026-11', '2026-11-07', '342.42');
        $charge('2026-12', '2026-11-26', '183.25');
        Transaction::factory()->create(['user_id' => $user->id, 'account_id' => $account->id, 'card_id' => $card->id, 'statement_month' => '2026-09', 'type' => 'transfer', 'amount' => '183.25', 'transaction_date' => '2026-09-21', 'status' => 'confirmed']);
        DB::table('import_batches')->insert(['id' => $batch = (string) Str::uuid(), 'user_id' => $user->id, 'reference' => 'fixture', 'plan_hash' => str_repeat('a', 64), 'version' => 4, 'status' => 'applied', 'cutoff' => '2026-10-15']);
        DB::table('card_statements')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'card_id' => $card->id, 'batch_id' => $batch, 'cycle' => '2026-07', 'due_on' => '2026-07-20', 'status' => 'historico_quitado', 'documentary_cents' => 13911, 'evidence' => '{}']);

        return [$user, $account, $card];
    }

    public function test_cycle_rules_follow_closing_and_due_days(): void
    {
        $nubank = new CreditCard(['closing_day' => 13, 'due_day' => 20]);
        $this->assertSame('2026-10', CardCycle::cycleFor($nubank, Carbon::parse('2026-10-13')));
        $this->assertSame('2026-11', CardCycle::cycleFor($nubank, Carbon::parse('2026-10-14')));
        $this->assertSame('2026-09-14', CardCycle::startDate($nubank, '2026-10')->toDateString());

        $late = new CreditCard(['closing_day' => 28, 'due_day' => 5]);
        $this->assertSame('2026-11', CardCycle::cycleFor($late, Carbon::parse('2026-10-28')));
        $this->assertSame('2026-12', CardCycle::cycleFor($late, Carbon::parse('2026-10-29')));
        $this->assertSame('2026-10-28', CardCycle::closeDate($late, '2026-11')->toDateString());

        $endOfMonth = new CreditCard(['closing_day' => 31, 'due_day' => 10]);
        $this->assertSame('2026-03-01', CardCycle::startDate($endOfMonth, '2026-04')->toDateString());
        $this->assertSame('2026-03', CardCycle::cycleFor($endOfMonth, Carbon::parse('2026-02-28')));
    }

    public function test_overview_shows_closed_statement_open_statement_and_real_limit(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-15 12:00:00', 'America/Sao_Paulo'));
        [$user, , $card] = $this->scenario();

        $data = $this->actingAs($user, 'sanctum')->getJson("/api/v1/finance/cards/{$card->id}/overview")->assertOk()->json('data');

        $this->assertSame('2026-11', $data['open_cycle']);
        $this->assertCount(1, $data['to_pay']);
        $this->assertSame(['2026-10', 'closed', 56015, '2026-10-20'], [$data['to_pay'][0]['cycle'], $data['to_pay'][0]['status'], $data['to_pay'][0]['remaining_cents'], $data['to_pay'][0]['due_on']]);
        $this->assertSame(['open', 52567, '2026-11-13'], [$data['open']['status'], $data['open']['charges_cents'], $data['open']['closing_on']]);
        $this->assertSame(['2026-12'], array_column($data['upcoming'], 'cycle'));
        // Historical July and the paid September statement do not consume the limit.
        $this->assertSame(56015 + 52567 + 18325, $data['used_cents']);
        $this->assertSame(320000 - (56015 + 52567 + 18325), $data['available_cents']);
    }

    public function test_statement_status_reflects_payments_due_date_and_history(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-15 12:00:00', 'America/Sao_Paulo'));
        [$user, $account, $card] = $this->scenario();
        $status = fn (string $cycle) => $this->actingAs($user, 'sanctum')->getJson("/api/v1/finance/cards/{$card->id}/statement?cycle={$cycle}")->assertOk()->json('data');

        $this->assertSame('paid', $status('2026-09')['status']);
        $july = $status('2026-07');
        $this->assertSame(['historical', 0, true], [$july['status'], $july['remaining_cents'], $july['historical']]);
        $this->assertSame('future', $status('2026-12')['status']);

        $this->actingAs($user, 'sanctum')->postJson("/api/v1/finance/cards/{$card->id}/pay-statement", [
            'account_id' => $account->id, 'amount' => 200, 'statement_month' => '2026-10', 'payment_date' => '2026-10-15', 'payment_reference' => (string) Str::uuid(),
        ])->assertCreated();
        $october = $status('2026-10');
        $this->assertSame(['partial', 36015], [$october['status'], $october['remaining_cents']]);

        Carbon::setTestNow(Carbon::parse('2026-10-21 12:00:00', 'America/Sao_Paulo'));
        $this->assertSame('overdue', $status('2026-10')['status']);
    }

    public function test_refund_reduces_statement_and_limit_usage(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-15 12:00:00', 'America/Sao_Paulo'));
        [$user, , $card] = $this->scenario();
        Transaction::factory()->create(['user_id' => $user->id, 'account_id' => null, 'card_id' => $card->id, 'statement_month' => '2026-11', 'type' => 'income', 'amount' => '42.42', 'transaction_date' => '2026-11-01', 'status' => 'confirmed']);

        $data = $this->actingAs($user, 'sanctum')->getJson("/api/v1/finance/cards/{$card->id}/overview")->json('data');

        $this->assertSame(52567 - 4242, $data['open']['charges_cents']);
    }

    public function test_purchase_without_explicit_cycle_is_placed_by_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-15 12:00:00', 'America/Sao_Paulo'));
        [$user, , $card] = $this->scenario();
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/finance/transactions', [
            'type' => 'expense', 'amount' => 50, 'description' => 'Jogo', 'transaction_date' => '2026-10-15', 'card_id' => $card->id,
        ])->assertCreated();

        $data = $this->actingAs($user, 'sanctum')->getJson("/api/v1/finance/cards/{$card->id}/overview")->json('data');

        $this->assertSame(52567 + 5000, $data['open']['charges_cents']);
    }

    public function test_projected_balance_subtracts_card_statements_due(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-15 12:00:00', 'America/Sao_Paulo'));
        [$user] = $this->scenario();

        $october = $this->actingAs($user, 'sanctum')->getJson('/api/v1/finance/balance/historical?month=10&year=2026')->assertOk()->json('data');
        $november = $this->actingAs($user, 'sanctum')->getJson('/api/v1/finance/balance/historical?month=11&year=2026')->assertOk()->json('data');
        $september = $this->actingAs($user, 'sanctum')->getJson('/api/v1/finance/balance/historical?month=9&year=2026')->assertOk()->json('data');

        $this->assertEquals(560.15, $october['card_due']);
        $this->assertEqualsWithDelta(5000 - 560.15, $october['projected_balance'], 0.001);
        // Future months accumulate: October statement + November statement.
        $this->assertEqualsWithDelta(5000 - 560.15 - 525.67, $november['projected_balance'], 0.001);
        $this->assertSame(['2026-10', '2026-11'], array_column($november['card_statements'], 'cycle'));
        // Past months are history: nothing to subtract.
        $this->assertEquals(0, $september['card_due']);
    }

    public function test_scheduled_statement_payment_is_not_subtracted_twice(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-15 12:00:00', 'America/Sao_Paulo'));
        [$user, $account, $card] = $this->scenario();
        DB::table('account_balance_checkpoints')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'account_id' => $account->id, 'observed_on' => '2026-10-01', 'balance_cents' => 500000, 'kind' => 'opening', 'evidence' => 'fixture', 'created_at' => now(), 'updated_at' => now()]);
        Transaction::factory()->create(['user_id' => $user->id, 'account_id' => $account->id, 'card_id' => $card->id, 'statement_month' => '2026-10', 'type' => 'transfer', 'amount' => '560.15', 'transaction_date' => '2026-10-18', 'status' => 'pending']);

        $october = $this->actingAs($user, 'sanctum')->getJson('/api/v1/finance/balance/historical?month=10&year=2026')->assertOk()->json('data');

        $this->assertEquals(5000, $october['balance']);
        $this->assertEqualsWithDelta(5000 - 560.15, $october['projected_balance'], 0.001);
    }

    public function test_user_cannot_see_another_users_card_overview(): void
    {
        [, , $card] = $this->scenario();
        $other = User::factory()->create();

        $this->actingAs($other, 'sanctum')->getJson("/api/v1/finance/cards/{$card->id}/overview")->assertForbidden();
    }
}
