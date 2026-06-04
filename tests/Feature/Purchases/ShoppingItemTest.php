<?php

namespace Tests\Feature\Purchases;

use App\Domains\Purchases\Models\ShoppingItem;
use App\Domains\Purchases\Models\ShoppingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShoppingItemTest extends TestCase
{
    use RefreshDatabase;

    // ── Authentication ────────────────────────────────────────────────────────

    public function test_requires_authentication_to_create_item(): void
    {
        $user = User::factory()->create();
        $session = ShoppingSession::factory()->create(['user_id' => $user->id]);

        $this->postJson("/api/v1/shopping/sessions/{$session->id}/items", ['name' => 'Arroz'])
            ->assertUnauthorized();
    }

    public function test_requires_authentication_to_update_item(): void
    {
        $user = User::factory()->create();
        $session = ShoppingSession::factory()->create(['user_id' => $user->id]);
        $item = ShoppingItem::factory()->create([
            'shopping_session_id' => $session->id,
            'user_id' => $user->id,
        ]);

        $this->putJson("/api/v1/shopping/items/{$item->id}", ['name' => 'Feijão'])
            ->assertUnauthorized();
    }

    public function test_requires_authentication_to_delete_item(): void
    {
        $user = User::factory()->create();
        $session = ShoppingSession::factory()->create(['user_id' => $user->id]);
        $item = ShoppingItem::factory()->create([
            'shopping_session_id' => $session->id,
            'user_id' => $user->id,
        ]);

        $this->deleteJson("/api/v1/shopping/items/{$item->id}")
            ->assertUnauthorized();
    }

    // ── CRUD ──────────────────────────────────────────────────────────────────

    public function test_creates_item_linked_to_a_session(): void
    {
        $user = User::factory()->create();
        $session = ShoppingSession::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/shopping/sessions/{$session->id}/items", ['name' => 'Leite'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Leite');

        $this->assertDatabaseHas('shopping_items', [
            'shopping_session_id' => $session->id,
            'name' => 'Leite',
        ]);
    }

    public function test_cannot_create_item_on_session_of_another_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $session = ShoppingSession::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/shopping/sessions/{$session->id}/items", ['name' => 'Arroz'])
            ->assertForbidden();
    }

    public function test_cannot_create_item_on_a_finished_session(): void
    {
        $user = User::factory()->create();
        $session = ShoppingSession::factory()->finished()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/shopping/sessions/{$session->id}/items", ['name' => 'Arroz'])
            ->assertStatus(422);
    }

    public function test_updates_item_name(): void
    {
        $user = User::factory()->create();
        $session = ShoppingSession::factory()->create(['user_id' => $user->id]);
        $item = ShoppingItem::factory()->create([
            'shopping_session_id' => $session->id,
            'user_id' => $user->id,
            'name' => 'Arroz',
        ]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/shopping/items/{$item->id}", ['name' => 'Arroz Integral'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Arroz Integral');
    }

    public function test_updates_item_is_bought_to_true(): void
    {
        $user = User::factory()->create();
        $session = ShoppingSession::factory()->create(['user_id' => $user->id]);
        $item = ShoppingItem::factory()->create([
            'shopping_session_id' => $session->id,
            'user_id' => $user->id,
            'is_bought' => false,
        ]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/shopping/items/{$item->id}", ['is_bought' => true])
            ->assertOk()
            ->assertJsonPath('data.is_bought', true);

        $this->assertDatabaseHas('shopping_items', [
            'id' => $item->id,
            'is_bought' => true,
        ]);
    }

    public function test_updates_item_price(): void
    {
        $user = User::factory()->create();
        $session = ShoppingSession::factory()->create(['user_id' => $user->id]);
        $item = ShoppingItem::factory()->create([
            'shopping_session_id' => $session->id,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/shopping/items/{$item->id}", ['price' => 8.99])
            ->assertOk();

        $this->assertDatabaseHas('shopping_items', [
            'id' => $item->id,
            'price' => 8.99,
        ]);
    }

    public function test_cannot_update_item_of_another_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $session = ShoppingSession::factory()->create(['user_id' => $other->id]);
        $item = ShoppingItem::factory()->create([
            'shopping_session_id' => $session->id,
            'user_id' => $other->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/shopping/items/{$item->id}", ['name' => 'Hack'])
            ->assertForbidden();
    }

    public function test_soft_deletes_an_item(): void
    {
        $user = User::factory()->create();
        $session = ShoppingSession::factory()->create(['user_id' => $user->id]);
        $item = ShoppingItem::factory()->create([
            'shopping_session_id' => $session->id,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/shopping/items/{$item->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('shopping_items', ['id' => $item->id]);
    }

    public function test_cannot_delete_item_of_another_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $session = ShoppingSession::factory()->create(['user_id' => $other->id]);
        $item = ShoppingItem::factory()->create([
            'shopping_session_id' => $session->id,
            'user_id' => $other->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/shopping/items/{$item->id}")
            ->assertForbidden();
    }

    // ── Validações ────────────────────────────────────────────────────────────

    public function test_requires_name_to_create_item(): void
    {
        $user = User::factory()->create();
        $session = ShoppingSession::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/shopping/sessions/{$session->id}/items", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_accepts_nullable_category(): void
    {
        $user = User::factory()->create();
        $session = ShoppingSession::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/shopping/sessions/{$session->id}/items", [
                'name' => 'Leite',
                'category' => null,
            ])
            ->assertCreated();
    }

    public function test_accepts_nullable_price(): void
    {
        $user = User::factory()->create();
        $session = ShoppingSession::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/shopping/sessions/{$session->id}/items", [
                'name' => 'Leite',
                'price' => null,
            ])
            ->assertCreated();
    }

    public function test_rejects_negative_price(): void
    {
        $user = User::factory()->create();
        $session = ShoppingSession::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/shopping/sessions/{$session->id}/items", [
                'name' => 'Leite',
                'price' => -5.00,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['price']);
    }
}
