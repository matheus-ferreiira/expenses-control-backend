<?php

namespace Tests\Feature\Finance;

use App\Domains\Finance\Models\BankAccount;
use App\Domains\Finance\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** "Dinheiro guardado": investment accounts are reserves, kept apart from the available balance. */
class ReserveAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Nubank R$ 1.019,44 on 01/10, R$ 500 moved to the savings box on 18/10. */
    private function scenario(): array
    {
        Carbon::setTestNow(Carbon::parse('2026-10-19 12:00:00'));
        $user = User::factory()->create(['timezone' => 'America/Sao_Paulo']);
        $nubank = BankAccount::factory()->create(['user_id' => $user->id, 'name' => 'Nubank', 'type' => 'checking', 'balance' => '1019.44']);
        $box = BankAccount::factory()->create(['user_id' => $user->id, 'name' => 'Caixinha Nubank', 'type' => 'investment', 'balance' => '0.00']);
        foreach ([[$nubank, '2026-10-01', 101944], [$box, '2026-10-18', 0]] as [$account, $on, $cents]) {
            DB::table('account_balance_checkpoints')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'account_id' => $account->id, 'observed_on' => $on, 'balance_cents' => $cents, 'kind' => 'opening', 'evidence' => 'test', 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/finance/transactions', [
            'type' => 'transfer', 'amount' => 500, 'description' => 'Guardado na caixinha', 'transaction_date' => '2026-10-18', 'account_id' => $nubank->id, 'destination_account_id' => $box->id,
        ])->assertCreated();

        return [$user, $nubank, $box];
    }

    public function test_money_in_the_savings_box_is_kept_out_of_the_available_balance(): void
    {
        [$user, $nubank, $box] = $this->scenario();
        $this->assertSame('519.44', $nubank->fresh()->balance);
        $this->assertSame('500.00', $box->fresh()->balance);
        // Scheduled saving and a bill still to pay
        Transaction::factory()->create(['user_id' => $user->id, 'account_id' => $nubank->id, 'destination_account_id' => $box->id, 'type' => 'transfer', 'amount' => '200.00', 'status' => 'pending', 'transaction_date' => '2026-10-30']);
        Transaction::factory()->create(['user_id' => $user->id, 'account_id' => $nubank->id, 'type' => 'expense', 'amount' => '100.00', 'status' => 'pending', 'transaction_date' => '2026-10-31']);

        $october = $this->actingAs($user, 'sanctum')->getJson('/api/v1/finance/balance/historical?month=10&year=2026')->assertOk()->json('data');

        $this->assertEqualsWithDelta(519.44, $october['balance'], 0.001);
        $this->assertEqualsWithDelta(519.44 - 200 - 100, $october['projected_balance'], 0.001);
        $this->assertEqualsWithDelta(500, $october['reserve_balance'], 0.001);
        $this->assertEqualsWithDelta(700, $october['projected_reserve_balance'], 0.001);
        $this->assertCount(2, $october['accounts']);
        $this->assertSame([], $october['unverified_accounts']);
    }

    public function test_the_month_shows_what_was_put_away_without_counting_it_as_spending(): void
    {
        [$user, $nubank, $box] = $this->scenario();
        // A withdrawal back to the checking account reduces what was put away
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/finance/transactions', [
            'type' => 'transfer', 'amount' => 50, 'description' => 'Resgate', 'transaction_date' => '2026-10-19', 'account_id' => $box->id, 'destination_account_id' => $nubank->id,
        ])->assertCreated();
        Transaction::factory()->create(['user_id' => $user->id, 'account_id' => $nubank->id, 'destination_account_id' => $box->id, 'type' => 'transfer', 'amount' => '200.00', 'status' => 'pending', 'transaction_date' => '2026-10-30']);

        $summary = $this->actingAs($user, 'sanctum')->getJson('/api/v1/finance/reports/monthly?year=2026&month=10')->assertOk()->json('data');
        $this->assertEquals(0, $summary['expenses']);
        $this->assertEquals(450, $summary['reserved']);
        $this->assertEquals(450, $summary['saved']);
        $this->assertEquals(-450, $summary['balance']);
        $this->assertEquals(200, $summary['pending_reserved']);

        $year = $this->actingAs($user, 'sanctum')->getJson('/api/v1/finance/reports/yearly?year=2026')->assertOk()->json('data.months.9');
        $this->assertEquals(450, $year['reserved']);

        $cash = $this->actingAs($user, 'sanctum')->getJson('/api/v1/finance/reports/cashflow?start_date=2026-10-18&end_date=2026-10-19')->assertOk()->json('data');
        $this->assertEquals(500, $cash['by_date']['2026-10-18']['expenses']);
        $this->assertEquals(0, $cash['by_date']['2026-10-18']['income']);
        $this->assertEquals(50, $cash['by_date']['2026-10-19']['income']);
    }

    public function test_accounts_and_dashboard_flag_the_reserve(): void
    {
        [$user, $nubank, $box] = $this->scenario();

        $accounts = collect($this->actingAs($user, 'sanctum')->getJson('/api/v1/finance/accounts')->assertOk()->json('data'))->keyBy('name');
        $this->assertTrue($accounts['Caixinha Nubank']['is_reserve']);
        $this->assertFalse($accounts['Nubank']['is_reserve']);

        $finance = $this->actingAs($user, 'sanctum')->getJson('/api/v1/dashboard')->assertOk()->json('data.finance');
        $this->assertEqualsWithDelta(519.44, $finance['total_balance'], 0.001);
        $this->assertEqualsWithDelta(500, $finance['reserve_balance'], 0.001);
        $this->assertEqualsWithDelta(500, $finance['month_reserved'], 0.001);
    }
}
