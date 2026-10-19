<?php

namespace Tests\Feature\Finance;

use App\Domains\Finance\Models\Transaction;
use App\Domains\Finance\Models\TransactionCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryBreakdownTest extends TestCase
{
    use RefreshDatabase;

    public function test_income_breakdown_and_drill_down_use_the_same_month_and_economic_policy(): void
    {
        $user = User::factory()->create();
        $category = TransactionCategory::factory()->create(['user_id' => $user->id, 'type' => 'income', 'name' => 'Salário']);
        $base = ['user_id' => $user->id, 'category_id' => $category->id, 'type' => 'income', 'status' => 'confirmed', 'transaction_date' => '2026-10-10'];
        $salary = Transaction::factory()->create([...$base, 'amount' => 3000]);
        $extra = Transaction::factory()->create([...$base, 'amount' => 1000, 'category_id' => null]);
        Transaction::factory()->create([...$base, 'amount' => 500, 'economic_kind' => 'reimbursement']);
        Transaction::factory()->create([...$base, 'amount' => 500, 'status' => 'pending']);
        Transaction::factory()->create([...$base, 'amount' => 500, 'transaction_date' => '2026-09-10']);
        Transaction::factory()->create([...$base, 'amount' => 500, 'user_id' => User::factory()->create()->id]);

        $report = $this->actingAs($user, 'sanctum')->getJson('/api/v1/finance/reports/monthly?year=2026&month=10')->assertOk()->json('data');
        $rows = collect($report['income_by_category']);
        $this->assertCount(2, $rows);
        $this->assertEquals(4000, $rows->sum('total'));
        $this->assertEquals(75, $rows->firstWhere('category_id', $category->id)['percentage']);
        $this->assertSame(1, $rows->firstWhere('category_id', $category->id)['count']);

        $this->getJson('/api/v1/finance/reports/category-transactions?year=2026&month=10&type=income&category_id='.$category->id)
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $salary->id);
        $this->getJson('/api/v1/finance/reports/category-transactions?year=2026&month=10&type=income')
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $extra->id);
        $this->getJson('/api/v1/finance/reports/category-transactions?year=2026&month=9&type=income&category_id='.$category->id)
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.transaction_date', '2026-09-10');
    }

    public function test_expense_drill_down_keeps_own_share_and_paginates_after_exclusions(): void
    {
        $user = User::factory()->create();
        $base = ['user_id' => $user->id, 'category_id' => null, 'type' => 'expense', 'status' => 'confirmed', 'transaction_date' => '2026-10-10'];
        $shared = Transaction::factory()->create([...$base, 'amount' => 100, 'economic_income_cents' => 0, 'economic_expense_cents' => 2500]);
        $ordinary = Transaction::factory()->create([...$base, 'amount' => 40, 'transaction_date' => '2026-10-09']);
        Transaction::factory()->create([...$base, 'amount' => 999, 'economic_kind' => 'card_payment']);
        Transaction::factory()->create([...$base, 'amount' => 999, 'reconciliation_status' => 'conflict']);
        Transaction::factory()->create([...$base, 'amount' => 999, 'status' => 'pending']);
        Transaction::factory()->create([...$base, 'amount' => 999, 'type' => 'income']);

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/finance/reports/monthly?year=2026&month=10')
            ->assertOk()->assertJsonPath('data.expenses_by_category.0.count', 2)
            ->assertJsonPath('data.expenses_by_category.0.total', 65);
        $url = '/api/v1/finance/reports/category-transactions?year=2026&month=10&type=expense&per_page=1';
        $this->getJson($url)->assertOk()->assertJsonPath('meta.total', 2)->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('data.0.id', $shared->id)->assertJsonPath('data.0.economic_effect.expense', 2500);
        $this->getJson($url.'&page=2')->assertOk()->assertJsonPath('data.0.id', $ordinary->id);
    }

    public function test_category_transactions_validate_filters_and_isolate_users(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $category = TransactionCategory::factory()->create(['user_id' => $other->id]);
        Transaction::factory()->create(['user_id' => $other->id, 'category_id' => $category->id, 'status' => 'confirmed', 'transaction_date' => '2026-10-10']);
        $url = '/api/v1/finance/reports/category-transactions?year=2026&month=10';
        $this->actingAs($user, 'sanctum')->getJson($url.'&type=expense&category_id='.$category->id)
            ->assertOk()->assertJsonPath('meta.total', 0)->assertJsonCount(0, 'data');
        $this->getJson($url.'&type=transfer&page=0&per_page=101&category_id=invalid')
            ->assertUnprocessable()->assertJsonValidationErrors(['type', 'page', 'per_page', 'category_id']);
    }
}
