<?php

namespace Tests\Feature\Purchases;

use App\Domains\Finance\Models\BankAccount;
use App\Domains\Finance\Models\CreditCard;
use App\Domains\Finance\Models\TransactionCategory;
use App\Domains\Purchases\Models\ShoppingItem;
use App\Domains\Purchases\Models\ShoppingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShoppingSessionTest extends TestCase
{
    use RefreshDatabase;

    // ── Authentication ────────────────────────────────────────────────────────

    public function test_requires_authentication_to_list_sessions(): void
    {
        $this->getJson('/api/v1/shopping/sessions')
            ->assertUnauthorized();
    }

    public function test_requires_authentication_to_create_session(): void
    {
        $this->postJson('/api/v1/shopping/sessions', ['title' => 'Mercado'])
            ->assertUnauthorized();
    }

    // ── CRUD básico ───────────────────────────────────────────────────────────

    public function test_lists_only_sessions_belonging_to_authenticated_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        ShoppingSession::factory()->count(2)->create(['user_id' => $user->id]);
        ShoppingSession::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/shopping/sessions')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_creates_a_session_with_status_active(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/shopping/sessions', ['title' => 'Mercado Semanal'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'active');
    }

    public function test_creates_a_session_with_correct_user_id(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/shopping/sessions', ['title' => 'Mercado'])
            ->assertCreated();

        $this->assertDatabaseHas('shopping_sessions', [
            'user_id' => $user->id,
            'title' => 'Mercado',
        ]);
    }

    public function test_returns_session_with_items_on_show(): void
    {
        $user = User::factory()->create();
        $session = ShoppingSession::factory()->create(['user_id' => $user->id]);
        ShoppingItem::factory()->count(3)->create([
            'shopping_session_id' => $session->id,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/shopping/sessions/{$session->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $session->id)
            ->assertJsonCount(3, 'data.items');
    }

    public function test_does_not_return_sessions_of_other_users_on_show(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $session = ShoppingSession::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/shopping/sessions/{$session->id}")
            ->assertForbidden();
    }

    public function test_soft_deletes_an_active_session(): void
    {
        $user = User::factory()->create();
        $session = ShoppingSession::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/shopping/sessions/{$session->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('shopping_sessions', ['id' => $session->id]);
    }

    public function test_cannot_delete_a_finished_session(): void
    {
        $user = User::factory()->create();
        $session = ShoppingSession::factory()->finished()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/shopping/sessions/{$session->id}")
            ->assertStatus(422);
    }

    public function test_cannot_delete_a_session_of_another_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $session = ShoppingSession::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/shopping/sessions/{$session->id}")
            ->assertForbidden();
    }

    // ── Finish ────────────────────────────────────────────────────────────────

    public function test_finishes_a_session_and_sets_status_to_finished(): void
    {
        $user = User::factory()->create();
        $session = ShoppingSession::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/shopping/sessions/{$session->id}/finish", ['total' => 150.00])
            ->assertOk()
            ->assertJsonPath('data.status', 'finished');
    }

    public function test_finishes_a_session_and_saves_total(): void
    {
        $user = User::factory()->create();
        $session = ShoppingSession::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/shopping/sessions/{$session->id}/finish", ['total' => 99.90])
            ->assertOk();

        $this->assertDatabaseHas('shopping_sessions', [
            'id' => $session->id,
            'total' => 99.90,
        ]);
    }

    public function test_finishes_a_session_and_sets_finished_at(): void
    {
        $user = User::factory()->create();
        $session = ShoppingSession::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/shopping/sessions/{$session->id}/finish", ['total' => 50.00])
            ->assertOk();

        $session->refresh();
        $this->assertNotNull($session->finished_at);
    }

    public function test_cannot_finish_an_already_finished_session(): void
    {
        $user = User::factory()->create();
        $session = ShoppingSession::factory()->finished()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/shopping/sessions/{$session->id}/finish", ['total' => 50.00])
            ->assertStatus(422);
    }

    public function test_cannot_finish_a_session_of_another_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $session = ShoppingSession::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/shopping/sessions/{$session->id}/finish", ['total' => 50.00])
            ->assertForbidden();
    }

    public function test_creates_a_transaction_when_bank_account_id_is_provided_on_finish(): void
    {
        $user = User::factory()->create();
        $session = ShoppingSession::factory()->create(['user_id' => $user->id]);
        $account = BankAccount::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/shopping/sessions/{$session->id}/finish", [
                'total' => 200.00,
                'bank_account_id' => $account->id,
            ])
            ->assertOk();

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'account_id' => $account->id,
            'type' => 'expense',
            'amount' => 200.00,
        ]);
    }

    public function test_creates_a_transaction_when_credit_card_id_is_provided_on_finish(): void
    {
        $user = User::factory()->create();
        $session = ShoppingSession::factory()->create(['user_id' => $user->id]);
        $card = CreditCard::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/shopping/sessions/{$session->id}/finish", [
                'total' => 180.50,
                'credit_card_id' => $card->id,
            ])
            ->assertOk();

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'card_id' => $card->id,
            'type' => 'expense',
            'amount' => 180.50,
        ]);
    }

    public function test_creates_transaction_with_correct_amount_and_description_on_finish(): void
    {
        $user = User::factory()->create();
        $session = ShoppingSession::factory()->create([
            'user_id' => $user->id,
            'title' => 'Mercado da Semana',
        ]);
        $account = BankAccount::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/shopping/sessions/{$session->id}/finish", [
                'total' => 123.45,
                'bank_account_id' => $account->id,
            ])
            ->assertOk();

        $this->assertDatabaseHas('transactions', [
            'amount' => 123.45,
            'description' => 'Mercado da Semana',
            'type' => 'expense',
        ]);
    }

    public function test_creates_transaction_with_category_id_when_provided_on_finish(): void
    {
        $user = User::factory()->create();
        $session = ShoppingSession::factory()->create(['user_id' => $user->id]);
        $account = BankAccount::factory()->create(['user_id' => $user->id]);
        $category = TransactionCategory::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/shopping/sessions/{$session->id}/finish", [
                'total' => 75.00,
                'bank_account_id' => $account->id,
                'category_id' => $category->id,
            ])
            ->assertOk();

        $this->assertDatabaseHas('transactions', [
            'category_id' => $category->id,
            'amount' => 75.00,
        ]);
    }

    public function test_saves_transaction_id_on_session_after_creating_transaction(): void
    {
        $user = User::factory()->create();
        $session = ShoppingSession::factory()->create(['user_id' => $user->id]);
        $account = BankAccount::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/shopping/sessions/{$session->id}/finish", [
                'total' => 60.00,
                'bank_account_id' => $account->id,
            ])
            ->assertOk();

        $session->refresh();
        $this->assertNotNull($session->transaction_id);
    }

    public function test_does_not_create_transaction_when_neither_account_nor_card_is_provided(): void
    {
        $user = User::factory()->create();
        $session = ShoppingSession::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/shopping/sessions/{$session->id}/finish", ['total' => 50.00])
            ->assertOk();

        $this->assertDatabaseMissing('transactions', ['user_id' => $user->id]);
    }

    // ── Isolation & ordering ──────────────────────────────────────────────────

    public function test_filters_active_sessions_correctly(): void
    {
        $user = User::factory()->create();
        ShoppingSession::factory()->create(['user_id' => $user->id, 'status' => 'active']);
        ShoppingSession::factory()->finished()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/shopping/sessions')
            ->assertOk();

        $data = collect($response->json('data'));
        $this->assertCount(1, $data->where('status', 'active'));
    }

    public function test_filters_finished_sessions_correctly(): void
    {
        $user = User::factory()->create();
        ShoppingSession::factory()->create(['user_id' => $user->id, 'status' => 'active']);
        ShoppingSession::factory()->finished()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/shopping/sessions')
            ->assertOk();

        $data = collect($response->json('data'));
        $this->assertCount(1, $data->where('status', 'finished'));
    }

    public function test_orders_sessions_by_created_at_desc(): void
    {
        $user = User::factory()->create();
        $old = ShoppingSession::factory()->create([
            'user_id' => $user->id,
            'title' => 'Antiga',
            'created_at' => now()->subDays(3),
        ]);
        $new = ShoppingSession::factory()->create([
            'user_id' => $user->id,
            'title' => 'Nova',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/shopping/sessions')
            ->assertOk();

        $this->assertEquals($new->id, $response->json('data.0.id'));
    }
}
