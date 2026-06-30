<?php

namespace Tests\Feature\Finance;

use App\Domains\Finance\Models\BankAccount;
use App\Domains\Finance\Models\CreditCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditCardTest extends TestCase
{
    use RefreshDatabase;

    public function test_created_card_is_active_in_the_api_response(): void
    {
        $user = User::factory()->create();
        $account = BankAccount::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/finance/cards', [
                'bank_account_id' => $account->id,
                'name' => 'Nubank',
                'limit_amount' => 5000,
                'closing_day' => 13,
                'due_day' => 20,
                'color' => '#6366f1',
            ]);

        $response->assertCreated();
        // Regression: is_active was missing from the validated payload, so the
        // in-memory model (and this JSON response) reported null instead of true,
        // even though the DB column default correctly persisted 1.
        $response->assertJsonPath('data.is_active', true);
        $this->assertDatabaseHas('credit_cards', [
            'name' => 'Nubank',
            'is_active' => true,
        ]);
    }

    public function test_user_cannot_access_another_users_card(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $card = CreditCard::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/finance/cards/{$card->id}")
            ->assertForbidden();
    }
}
