<?php

namespace Tests\Feature\Purchases;

use App\Domains\Purchases\Models\ShoppingItem;
use App\Domains\Purchases\Models\ShoppingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShoppingSessionIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_cannot_create_second_active_session(): void
    {
        $user = User::factory()->create();
        ShoppingSession::factory()->create(['user_id' => $user->id, 'status' => 'active']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/shopping/sessions', ['title' => 'Segunda lista'])
            ->assertUnprocessable();

        $this->assertSame(1, ShoppingSession::forUser($user->id)->active()->count());
    }

    public function test_can_create_session_when_previous_is_finished(): void
    {
        $user = User::factory()->create();
        ShoppingSession::factory()->create([
            'user_id' => $user->id,
            'status' => 'finished',
            'finished_at' => now(),
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/shopping/sessions', ['title' => 'Nova lista'])
            ->assertCreated();
    }

    public function test_active_session_of_another_user_does_not_block(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        ShoppingSession::factory()->create(['user_id' => $other->id, 'status' => 'active']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/shopping/sessions', ['title' => 'Minha lista'])
            ->assertCreated();
    }

    public function test_soft_deleting_session_soft_deletes_its_items(): void
    {
        $user = User::factory()->create();
        $session = ShoppingSession::factory()->create(['user_id' => $user->id, 'status' => 'active']);
        $item = ShoppingItem::create([
            'shopping_session_id' => $session->id,
            'user_id' => $user->id,
            'name' => 'Sabão em pó',
        ]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/shopping/sessions/{$session->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('shopping_sessions', ['id' => $session->id]);
        $this->assertSoftDeleted('shopping_items', ['id' => $item->id]);
    }

    public function test_prune_command_removes_orphan_items(): void
    {
        $user = User::factory()->create();
        $session = ShoppingSession::factory()->create(['user_id' => $user->id]);
        $orphan = ShoppingItem::create([
            'shopping_session_id' => $session->id,
            'user_id' => $user->id,
            'name' => 'Fósforo órfão',
        ]);
        // Simula o estado legado: sessão some sem cascatear (delete direto no banco)
        ShoppingSession::withoutEvents(fn () => $session->delete());

        $this->artisan('purchases:prune-orphan-items')->assertSuccessful();

        $this->assertSoftDeleted('shopping_items', ['id' => $orphan->id]);
    }
}
