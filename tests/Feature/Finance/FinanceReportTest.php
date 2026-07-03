<?php

namespace Tests\Feature\Finance;

use App\Domains\Finance\Models\BankAccount;
use App\Domains\Finance\Models\FinanceGoal;
use App\Domains\Finance\Models\Transaction;
use App\Domains\Finance\Models\TransactionCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceReportTest extends TestCase
{
    use RefreshDatabase;

    private function seedMonth(User $user): void
    {
        $account = BankAccount::factory()->create(['user_id' => $user->id]);
        $goal = FinanceGoal::factory()->create(['user_id' => $user->id]);
        $food = TransactionCategory::factory()->create(['user_id' => $user->id, 'name' => 'Alimentação', 'type' => 'expense']);

        // Salário
        Transaction::factory()->create([
            'user_id' => $user->id, 'account_id' => $account->id,
            'type' => 'income', 'amount' => 5000, 'status' => 'confirmed',
            'transaction_date' => now()->startOfMonth()->toDateString(),
        ]);
        // Gasto real
        Transaction::factory()->create([
            'user_id' => $user->id, 'account_id' => $account->id, 'category_id' => $food->id,
            'type' => 'expense', 'amount' => 800, 'status' => 'confirmed',
            'transaction_date' => now()->startOfMonth()->toDateString(),
        ]);
        // Aporte (guardado — não é gasto)
        Transaction::factory()->create([
            'user_id' => $user->id, 'account_id' => $account->id, 'goal_id' => $goal->id,
            'type' => 'expense', 'amount' => 1000, 'status' => 'confirmed',
            'description' => 'Aporte Reserva',
            'transaction_date' => now()->startOfMonth()->toDateString(),
        ]);
    }

    public function test_monthly_summary_separates_saved_from_expenses(): void
    {
        $user = User::factory()->create();
        $this->seedMonth($user);

        $data = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/finance/reports/monthly?year='.now()->year.'&month='.now()->month)
            ->assertOk()
            ->json('data');

        $this->assertSame(5000.0, (float) $data['income']);
        $this->assertSame(800.0, (float) $data['expenses']);   // aporte NÃO entra
        $this->assertSame(1000.0, (float) $data['saved']);     // aporte separado
        $this->assertSame(3200.0, (float) $data['balance']);   // 5000 - 800 - 1000
    }

    public function test_category_distribution_excludes_goal_contributions(): void
    {
        $user = User::factory()->create();
        $this->seedMonth($user);

        $categories = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/finance/reports/monthly?year='.now()->year.'&month='.now()->month)
            ->assertOk()
            ->json('data.expenses_by_category');

        $total = array_sum(array_column($categories, 'total'));
        $this->assertSame(800.0, (float) $total);
        // Sem "Sem categoria" de R$1000 vindo do aporte
        foreach ($categories as $cat) {
            $this->assertNotEquals(1000.0, (float) $cat['total']);
        }
    }

    public function test_yearly_summary_reports_saved_per_month(): void
    {
        $user = User::factory()->create();
        $this->seedMonth($user);

        $months = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/finance/reports/yearly?year='.now()->year)
            ->assertOk()
            ->json('data.months');

        $current = collect($months)->firstWhere('month', now()->month);
        $this->assertSame(800.0, (float) $current['expenses']);
        $this->assertSame(1000.0, (float) $current['saved']);
    }
}
