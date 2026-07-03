<?php

namespace Tests\Feature\Finance;

use App\Domains\Finance\Models\BankAccount;
use App\Domains\Finance\Models\FinanceGoal;
use App\Domains\Finance\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoalContributionTest extends TestCase
{
    use RefreshDatabase;

    private function makeAccount(User $user, float $balance = 5000): BankAccount
    {
        return BankAccount::factory()->create(['user_id' => $user->id, 'balance' => $balance]);
    }

    public function test_transaction_created_with_goal_id_persists_and_returns_goal(): void
    {
        $user = User::factory()->create();
        $account = $this->makeAccount($user);
        $goal = FinanceGoal::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/finance/transactions', [
                'type' => 'expense',
                'amount' => 1000,
                'description' => 'Aporte Reserva',
                'transaction_date' => now()->toDateString(),
                'account_id' => $account->id,
                'goal_id' => $goal->id,
            ])
            ->assertCreated();

        $this->assertSame($goal->id, $response->json('data.goal_id'));
        $this->assertDatabaseHas('transactions', [
            'description' => 'Aporte Reserva',
            'goal_id' => $goal->id,
        ]);
    }

    public function test_recurring_transaction_propagates_goal_id_to_all_occurrences(): void
    {
        $user = User::factory()->create();
        $account = $this->makeAccount($user);
        $goal = FinanceGoal::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/finance/transactions', [
                'type' => 'expense',
                'amount' => 1000,
                'description' => 'Aporte mensal',
                'transaction_date' => now()->toDateString(),
                'account_id' => $account->id,
                'goal_id' => $goal->id,
                'is_recurring' => true,
                'recurrence_config' => ['frequency' => 'monthly', 'end_type' => 'count', 'count' => 6],
            ])
            ->assertCreated();

        $this->assertSame(6, Transaction::where('goal_id', $goal->id)->count());
    }

    public function test_confirming_linked_transaction_increases_goal_progress(): void
    {
        $user = User::factory()->create();
        $account = $this->makeAccount($user);
        $goal = FinanceGoal::factory()->create([
            'user_id' => $user->id,
            'target_amount' => 10000,
            'bank_account_id' => null,
        ]);

        // Future-dated → created as pending, must not count yet
        $txId = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/finance/transactions', [
                'type' => 'expense',
                'amount' => 1000,
                'description' => 'Aporte pendente',
                'transaction_date' => now()->addDays(3)->toDateString(),
                'account_id' => $account->id,
                'goal_id' => $goal->id,
            ])
            ->assertCreated()
            ->json('data.id');

        $before = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/finance/goals')
            ->assertOk()
            ->json('data.0.current_amount');
        $this->assertSame(0.0, (float) $before);

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/finance/transactions/{$txId}/confirm")
            ->assertOk();

        $after = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/finance/goals')
            ->assertOk()
            ->json('data.0.current_amount');
        $this->assertSame(1000.0, (float) $after);
    }

    public function test_goal_id_from_another_user_is_rejected(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $account = $this->makeAccount($user);
        $foreignGoal = FinanceGoal::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/finance/transactions', [
                'type' => 'expense',
                'amount' => 100,
                'description' => 'Aporte inválido',
                'transaction_date' => now()->toDateString(),
                'account_id' => $account->id,
                'goal_id' => $foreignGoal->id,
            ])
            ->assertUnprocessable();
    }

    public function test_account_linked_goal_uses_account_balance_as_progress(): void
    {
        $user = User::factory()->create();
        $reserveAccount = $this->makeAccount($user, 1623.21);
        $goal = FinanceGoal::factory()->create([
            'user_id' => $user->id,
            'target_amount' => 7000,
            'bank_account_id' => $reserveAccount->id,
        ]);

        // A confirmed goal_id transaction must NOT double-count for account-linked goals
        Transaction::factory()->create([
            'user_id' => $user->id,
            'goal_id' => $goal->id,
            'amount' => 999,
            'status' => 'confirmed',
        ]);

        $current = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/finance/goals')
            ->assertOk()
            ->json('data.0.current_amount');

        $this->assertSame(1623.21, (float) $current);
    }
}
