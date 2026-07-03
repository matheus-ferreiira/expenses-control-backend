<?php

namespace Tests\Feature\Finance;

use App\Domains\Finance\Models\BankAccount;
use App\Domains\Finance\Models\CreditCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CardStatementTest extends TestCase
{
    use RefreshDatabase;

    private function makeCard(User $user): CreditCard
    {
        $account = BankAccount::factory()->create(['user_id' => $user->id]);

        return CreditCard::factory()->create([
            'user_id' => $user->id,
            'bank_account_id' => $account->id,
            'closing_day' => 28,
            'due_day' => 5,
        ]);
    }

    public function test_card_expense_can_be_created_without_account(): void
    {
        $user = User::factory()->create();
        $card = $this->makeCard($user);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/finance/transactions', [
                'type' => 'expense',
                'amount' => 250,
                'description' => 'Mercado no crédito',
                'transaction_date' => now()->toDateString(),
                'card_id' => $card->id,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('transactions', [
            'description' => 'Mercado no crédito',
            'card_id' => $card->id,
            'account_id' => null,
        ]);
    }

    public function test_card_expense_does_not_change_any_account_balance(): void
    {
        $user = User::factory()->create();
        $card = $this->makeCard($user);
        $balanceBefore = (float) $card->bankAccount->balance;

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/finance/transactions', [
                'type' => 'expense',
                'amount' => 250,
                'description' => 'Compra crédito',
                'transaction_date' => now()->toDateString(),
                'card_id' => $card->id,
            ])
            ->assertCreated();

        $this->assertSame($balanceBefore, (float) $card->bankAccount->fresh()->balance);
    }

    public function test_expense_without_account_and_without_card_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/finance/transactions', [
                'type' => 'expense',
                'amount' => 100,
                'description' => 'Sem origem',
                'transaction_date' => now()->toDateString(),
            ])
            ->assertUnprocessable();
    }

    public function test_pay_statement_creates_confirmed_transfer_and_debits_account(): void
    {
        $user = User::factory()->create();
        $card = $this->makeCard($user);
        $payFrom = BankAccount::factory()->create(['user_id' => $user->id, 'balance' => 3000]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/finance/cards/{$card->id}/pay-statement", [
                'account_id' => $payFrom->id,
                'amount' => 1200.50,
                'statement_month' => now()->format('Y-m'),
            ])
            ->assertCreated();

        $this->assertSame('transfer', $response->json('data.type'));
        $this->assertSame('confirmed', $response->json('data.status'));
        $this->assertSame(now()->format('Y-m'), $response->json('data.statement_month'));
        $this->assertSame(3000 - 1200.50, (float) $payFrom->fresh()->balance);
    }

    public function test_paying_same_statement_twice_is_rejected(): void
    {
        $user = User::factory()->create();
        $card = $this->makeCard($user);
        $payFrom = BankAccount::factory()->create(['user_id' => $user->id, 'balance' => 5000]);

        $payload = [
            'account_id' => $payFrom->id,
            'amount' => 500,
            'statement_month' => now()->format('Y-m'),
        ];

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/finance/cards/{$card->id}/pay-statement", $payload)
            ->assertCreated();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/finance/cards/{$card->id}/pay-statement", $payload)
            ->assertUnprocessable();
    }

    public function test_statement_payment_lookup_returns_payment_or_null(): void
    {
        $user = User::factory()->create();
        $card = $this->makeCard($user);
        $payFrom = BankAccount::factory()->create(['user_id' => $user->id, 'balance' => 5000]);
        $month = now()->format('Y-m');

        $empty = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/finance/cards/{$card->id}/statement-payment?statement_month={$month}")
            ->assertOk();
        $this->assertNull($empty->json('data'));

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/finance/cards/{$card->id}/pay-statement", [
                'account_id' => $payFrom->id,
                'amount' => 750,
                'statement_month' => $month,
            ])
            ->assertCreated();

        $paid = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/finance/cards/{$card->id}/statement-payment?statement_month={$month}")
            ->assertOk();
        $this->assertSame(750.0, (float) $paid->json('data.amount'));
    }

    public function test_user_cannot_pay_statement_of_another_users_card(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $foreignCard = $this->makeCard($other);
        $payFrom = BankAccount::factory()->create(['user_id' => $user->id, 'balance' => 5000]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/finance/cards/{$foreignCard->id}/pay-statement", [
                'account_id' => $payFrom->id,
                'amount' => 100,
                'statement_month' => now()->format('Y-m'),
            ])
            ->assertForbidden();
    }

    public function test_regular_transfer_still_requires_destination_account(): void
    {
        $user = User::factory()->create();
        $origin = BankAccount::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/finance/transactions', [
                'type' => 'transfer',
                'amount' => 100,
                'description' => 'Transferência sem destino',
                'transaction_date' => now()->toDateString(),
                'account_id' => $origin->id,
            ])
            ->assertUnprocessable();
    }
}
