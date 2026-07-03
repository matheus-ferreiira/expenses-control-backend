<?php

namespace Tests\Feature\Finance;

use App\Domains\Finance\Models\TransactionCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetCategoryLimitSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_current_month_budget_syncs_category_monthly_limits(): void
    {
        $user = User::factory()->create();
        $food = TransactionCategory::factory()->create(['user_id' => $user->id, 'type' => 'expense', 'monthly_limit' => null]);
        $leisure = TransactionCategory::factory()->create(['user_id' => $user->id, 'type' => 'expense', 'monthly_limit' => 999]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/finance/budgets', [
                'month' => now()->month,
                'year' => now()->year,
                'base_amount' => 5000,
                'items' => [
                    ['category_id' => $food->id, 'amount' => 700],
                ],
            ])
            ->assertCreated();

        // Category in the budget gets the item amount as limit
        $this->assertSame(700.0, (float) $food->fresh()->monthly_limit);
        // Category absent from the budget has its limit cleared (budget = source of truth)
        $this->assertNull($leisure->fresh()->monthly_limit);
    }

    public function test_saving_other_month_budget_does_not_touch_limits(): void
    {
        $user = User::factory()->create();
        $food = TransactionCategory::factory()->create(['user_id' => $user->id, 'type' => 'expense', 'monthly_limit' => 500]);

        $next = now()->addMonthNoOverflow();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/finance/budgets', [
                'month' => $next->month,
                'year' => $next->year,
                'base_amount' => 5000,
                'items' => [
                    ['category_id' => $food->id, 'amount' => 900],
                ],
            ])
            ->assertCreated();

        $this->assertSame(500.0, (float) $food->fresh()->monthly_limit);
    }
}
