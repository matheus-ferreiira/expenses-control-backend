<?php

namespace Tests\Feature\Finance;

use App\Domains\Finance\Models\BankAccount;
use App\Domains\Finance\Models\Transaction;
use App\Domains\Finance\Models\TransactionCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionsTest extends TestCase
{
    use RefreshDatabase;

    // ── SQL Injection Prevention ──────────────────────────────────────────────

    public function test_sort_by_injection_attempt_is_rejected_with_validation_error(): void
    {
        $user = User::factory()->create();
        Transaction::factory()->count(3)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/finance/transactions?sort_by=user_id;DROP TABLE transactions--');

        // Validation whitelist rejects invalid sort_by before it reaches the query
        $response->assertUnprocessable();
        $this->assertDatabaseCount('transactions', 3);
    }

    public function test_sort_by_whitelisted_columns_are_accepted(): void
    {
        $user = User::factory()->create();
        Transaction::factory()->count(2)->create(['user_id' => $user->id]);

        foreach (['transaction_date', 'amount', 'description', 'created_at'] as $col) {
            $this->actingAs($user, 'sanctum')
                ->getJson("/api/v1/finance/transactions?sort_by={$col}")
                ->assertOk();
        }
    }

    // ── Account Balance on Single Transaction ─────────────────────────────────

    public function test_creating_expense_transaction_decrements_account_balance(): void
    {
        $user = User::factory()->create();
        $account = BankAccount::factory()->create(['user_id' => $user->id, 'balance' => 1000.00]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/finance/transactions', [
                'account_id' => $account->id,
                'type' => 'expense',
                'amount' => 150.00,
                'description' => 'Test expense',
                'transaction_date' => now()->toDateString(),
            ])
            ->assertCreated();

        $this->assertEqualsWithDelta(850.00, $account->fresh()->balance, 0.01);
    }

    public function test_creating_income_transaction_increments_account_balance(): void
    {
        $user = User::factory()->create();
        $account = BankAccount::factory()->create(['user_id' => $user->id, 'balance' => 500.00]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/finance/transactions', [
                'account_id' => $account->id,
                'type' => 'income',
                'amount' => 200.00,
                'description' => 'Salary',
                'transaction_date' => now()->toDateString(),
            ])
            ->assertCreated();

        $this->assertEqualsWithDelta(700.00, $account->fresh()->balance, 0.01);
    }

    // ── Installment Balance Update ────────────────────────────────────────────

    public function test_installment_purchase_deducts_only_confirmed_installments_from_balance(): void
    {
        $user = User::factory()->create();
        $account = BankAccount::factory()->create(['user_id' => $user->id, 'balance' => 1000.00]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/finance/transactions', [
                'account_id' => $account->id,
                'type' => 'expense',
                'amount' => 300.00,
                'description' => 'TV',
                'transaction_date' => now()->toDateString(),
                'total_installments' => 3,
            ])
            ->assertCreated();

        // 3 installment records created
        $this->assertDatabaseCount('transactions', 3);

        // Only installment 1 (today) is confirmed; installments 2 and 3 are future → Pending
        // Balance reduced by R$100 (first installment only)
        $this->assertEqualsWithDelta(900.00, $account->fresh()->balance, 0.01);

        // Verify status distribution
        $confirmed = \App\Domains\Finance\Models\Transaction::where('status', 'confirmed')->count();
        $pending = \App\Domains\Finance\Models\Transaction::where('status', 'pending')->count();
        $this->assertSame(1, $confirmed);
        $this->assertSame(2, $pending);
    }

    // ── Future Date → Pending + No Balance Impact ────────────────────────────

    public function test_future_dated_transaction_is_pending_and_does_not_affect_balance(): void
    {
        $user = User::factory()->create();
        $account = BankAccount::factory()->create(['user_id' => $user->id, 'balance' => 1000.00]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/finance/transactions', [
                'account_id' => $account->id,
                'type' => 'expense',
                'amount' => 200.00,
                'description' => 'Future expense',
                'transaction_date' => now()->addDays(5)->toDateString(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        // Balance must not change for pending (future) transactions
        $this->assertEqualsWithDelta(1000.00, $account->fresh()->balance, 0.01);
    }

    public function test_future_recurring_transaction_first_occurrence_is_pending(): void
    {
        $user = User::factory()->create();
        $account = BankAccount::factory()->create(['user_id' => $user->id, 'balance' => 500.00]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/finance/transactions', [
                'account_id' => $account->id,
                'type' => 'income',
                'amount' => 3000.00,
                'description' => 'Salário jun',
                'transaction_date' => now()->addMonths(1)->toDateString(),
                'is_recurring' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        // Balance unchanged — all occurrences are in the future
        $this->assertEqualsWithDelta(500.00, $account->fresh()->balance, 0.01);
    }

    // ── Update Transaction Balance Reversal ───────────────────────────────────

    public function test_updating_transaction_amount_correctly_adjusts_account_balance(): void
    {
        $user = User::factory()->create();
        $account = BankAccount::factory()->create(['user_id' => $user->id, 'balance' => 1000.00]);

        // Create initial expense of 100
        $createResponse = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/finance/transactions', [
                'account_id' => $account->id,
                'type' => 'expense',
                'amount' => 100.00,
                'description' => 'Initial',
                'transaction_date' => now()->toDateString(),
            ]);

        $createResponse->assertCreated();
        $this->assertEqualsWithDelta(900.00, $account->fresh()->balance, 0.01);

        $transactionId = $createResponse->json('data.id');

        // Update to 200 — should reverse old (-100) and apply new (-200) = net -300
        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/finance/transactions/{$transactionId}", [
                'account_id' => $account->id,
                'type' => 'expense',
                'amount' => 200.00,
                'description' => 'Updated',
                'transaction_date' => now()->toDateString(),
            ])
            ->assertOk();

        $this->assertEqualsWithDelta(800.00, $account->fresh()->balance, 0.01);
    }

    // ── Resource Fields ───────────────────────────────────────────────────────

    public function test_transaction_resource_exposes_account_id_card_id_category_id(): void
    {
        $user = User::factory()->create();
        $account = BankAccount::factory()->create(['user_id' => $user->id]);
        $category = TransactionCategory::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/finance/transactions', [
                'account_id' => $account->id,
                'category_id' => $category->id,
                'type' => 'expense',
                'amount' => 50.00,
                'description' => 'Test',
                'transaction_date' => now()->toDateString(),
            ])
            ->assertCreated();

        $data = $response->json('data');
        $this->assertArrayHasKey('account_id', $data);
        $this->assertArrayHasKey('category_id', $data);
        $this->assertArrayHasKey('card_id', $data);
        $this->assertSame($account->id, $data['account_id']);
        $this->assertSame($category->id, $data['category_id']);
    }

    // ── Fix (Recurring) Promotion ─────────────────────────────────────────────

    public function test_editing_transaction_to_fix_generates_59_future_pending_occurrences(): void
    {
        $user = User::factory()->create();
        $account = BankAccount::factory()->create(['user_id' => $user->id, 'balance' => 1000.00]);

        // Create a normal (non-recurring) expense
        $createResponse = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/finance/transactions', [
                'account_id' => $account->id,
                'type' => 'expense',
                'amount' => 50.00,
                'description' => 'Netflix',
                'transaction_date' => now()->toDateString(),
            ])
            ->assertCreated();

        $transactionId = $createResponse->json('data.id');
        $this->assertDatabaseCount('transactions', 1);

        // Edit the transaction and mark it as fix (recurring)
        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/finance/transactions/{$transactionId}", [
                'type' => 'expense',
                'amount' => 50.00,
                'description' => 'Netflix',
                'transaction_date' => now()->toDateString(),
                'is_recurring' => true,
            ])
            ->assertOk();

        // Should now have 60 total: 1 confirmed (original) + 59 pending (future)
        $this->assertDatabaseCount('transactions', 60);

        // Original should now be confirmed and have a recurrence_group_id
        $original = Transaction::find($transactionId);
        $this->assertTrue($original->is_recurring);
        $this->assertNotNull($original->recurrence_group_id);
        $this->assertSame('confirmed', $original->status->value);

        // All 59 future occurrences should be pending and share the same group
        $pendingCount = Transaction::where('recurrence_group_id', $original->recurrence_group_id)
            ->where('status', 'pending')
            ->count();
        $this->assertSame(59, $pendingCount);
    }

    public function test_list_filters_by_is_recurring(): void
    {
        $user = User::factory()->create();
        // 3 normal, 2 fix
        Transaction::factory()->count(3)->create(['user_id' => $user->id, 'is_recurring' => false]);
        Transaction::factory()->count(2)->create(['user_id' => $user->id, 'is_recurring' => true]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/finance/transactions?is_recurring=1')
            ->assertOk();

        $this->assertCount(2, $response->json('data'));
    }

    public function test_list_filters_by_status_pending(): void
    {
        $user = User::factory()->create();
        Transaction::factory()->count(4)->create(['user_id' => $user->id, 'status' => 'confirmed']);
        Transaction::factory()->count(2)->create(['user_id' => $user->id, 'status' => 'pending']);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/finance/transactions?status=pending')
            ->assertOk();

        $this->assertCount(2, $response->json('data'));
    }

    // ── Confirm Pending Transaction ───────────────────────────────────────────

    public function test_confirming_pending_transaction_updates_status_and_balance(): void
    {
        $user = User::factory()->create();
        $account = BankAccount::factory()->create(['user_id' => $user->id, 'balance' => 1000.00]);

        $createResponse = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/finance/transactions', [
                'account_id' => $account->id,
                'type' => 'expense',
                'amount' => 300.00,
                'description' => 'Future bill',
                'transaction_date' => now()->addDays(5)->toDateString(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        // Balance unchanged — transaction is pending
        $this->assertEqualsWithDelta(1000.00, $account->fresh()->balance, 0.01);

        $transactionId = $createResponse->json('data.id');

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/finance/transactions/{$transactionId}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        // Balance now decreased after confirm
        $this->assertEqualsWithDelta(700.00, $account->fresh()->balance, 0.01);
    }

    public function test_confirming_already_confirmed_transaction_is_idempotent(): void
    {
        $user = User::factory()->create();
        $account = BankAccount::factory()->create(['user_id' => $user->id, 'balance' => 1000.00]);

        $id = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/finance/transactions', [
                'account_id' => $account->id,
                'type' => 'expense',
                'amount' => 100.00,
                'description' => 'Today expense',
                'transaction_date' => now()->toDateString(),
            ])
            ->assertCreated()
            ->json('data.id');

        $this->assertEqualsWithDelta(900.00, $account->fresh()->balance, 0.01);

        // Confirm again — balance should not change
        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/finance/transactions/{$id}/confirm")
            ->assertOk();

        $this->assertEqualsWithDelta(900.00, $account->fresh()->balance, 0.01);
    }

    // ── User Isolation ────────────────────────────────────────────────────────

    public function test_user_can_only_see_their_own_transactions(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        Transaction::factory()->count(3)->create(['user_id' => $userA->id]);
        Transaction::factory()->count(5)->create(['user_id' => $userB->id]);

        $response = $this->actingAs($userA, 'sanctum')
            ->getJson('/api/v1/finance/transactions');

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    public function test_user_cannot_delete_another_users_transaction(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $transaction = Transaction::factory()->create(['user_id' => $userB->id]);

        $this->actingAs($userA, 'sanctum')
            ->deleteJson("/api/v1/finance/transactions/{$transaction->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('transactions', ['id' => $transaction->id]);
    }
}
