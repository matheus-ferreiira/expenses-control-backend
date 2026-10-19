<?php

namespace Tests\Feature\Finance;

use App\Domains\Finance\Models\BankAccount;
use App\Domains\Finance\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** The current month's forecast also counts day-to-day spending, which is never scheduled. */
class DailyVariableSpendingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_average_covers_the_last_60_days_without_fixed_bills_or_installments(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-19 12:00:00'));
        $user = User::factory()->create(['timezone' => 'America/Sao_Paulo']);
        $account = BankAccount::factory()->create(['user_id' => $user->id]);
        $expense = fn (string $date, string $amount, array $extra = []) => Transaction::factory()->create([
            'user_id' => $user->id, 'account_id' => $account->id, 'type' => 'expense', 'amount' => $amount,
            'status' => 'confirmed', 'transaction_date' => $date, ...$extra,
        ]);
        // Window: 2026-08-21 … 2026-10-19 (60 days)
        $expense('2026-08-21', '600.00');
        $expense('2026-10-19', '1800.00');
        $expense('2026-08-20', '9999.00'); // outside the window
        $expense('2026-10-10', '700.00', ['recurrence_group_id' => (string) Str::uuid(), 'is_recurring' => true]); // fixed bill
        $expense('2026-10-12', '300.00', ['installment_group_id' => (string) Str::uuid(), 'installment_number' => 1, 'total_installments' => 3]);
        $expense('2026-10-13', '90.00', ['economic_kind' => 'shared_expense', 'economic_income_cents' => 0, 'economic_expense_cents' => 3000]); // own share only
        $expense('2026-10-15', '50.00', ['status' => 'pending']);

        $october = $this->actingAs($user, 'sanctum')->getJson('/api/v1/finance/reports/monthly?year=2026&month=10')->assertOk()->json('data');
        $september = $this->actingAs($user, 'sanctum')->getJson('/api/v1/finance/reports/monthly?year=2026&month=9')->assertOk()->json('data');

        $this->assertEqualsWithDelta((600 + 1800 + 30) / 60, $october['daily_variable_spending'], 0.01);
        $this->assertNull($september['daily_variable_spending']);
    }

    public function test_a_short_history_is_averaged_over_the_days_it_covers(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-19 12:00:00'));
        $user = User::factory()->create(['timezone' => 'America/Sao_Paulo']);
        $account = BankAccount::factory()->create(['user_id' => $user->id]);
        Transaction::factory()->create(['user_id' => $user->id, 'account_id' => $account->id, 'type' => 'expense', 'amount' => '100.00', 'status' => 'confirmed', 'transaction_date' => '2026-10-10']);

        $october = $this->actingAs($user, 'sanctum')->getJson('/api/v1/finance/reports/monthly?year=2026&month=10')->assertOk()->json('data');

        $this->assertEqualsWithDelta(10, $october['daily_variable_spending'], 0.01);
    }
}
