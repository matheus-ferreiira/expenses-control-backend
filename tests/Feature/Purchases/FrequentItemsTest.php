<?php

namespace Tests\Feature\Purchases;

use App\Domains\Purchases\Models\ShoppingItem;
use App\Domains\Purchases\Models\ShoppingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FrequentItemsTest extends TestCase
{
    use RefreshDatabase;

    private function addItem(User $user, ShoppingSession $session, string $name): ShoppingItem
    {
        return ShoppingItem::create([
            'shopping_session_id' => $session->id,
            'user_id' => $user->id,
            'name' => $name,
        ]);
    }

    public function test_frequent_items_are_deduped_and_ordered_by_usage(): void
    {
        $user = User::factory()->create();
        $s1 = ShoppingSession::factory()->create(['user_id' => $user->id, 'status' => 'finished', 'finished_at' => now()]);
        $s2 = ShoppingSession::factory()->create(['user_id' => $user->id, 'status' => 'active']);

        // Arroz 3x (com variação de caixa), Alho 1x
        $this->addItem($user, $s1, 'Arroz');
        $this->addItem($user, $s1, 'arroz');
        $this->addItem($user, $s2, 'Arroz');
        $this->addItem($user, $s2, 'Alho');

        $data = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/shopping/items/frequent')
            ->assertOk()
            ->json('data');

        $this->assertSame('Arroz', $data[0]['name']);
        $this->assertSame(3, $data[0]['uses']);
        $this->assertSame('Alho', $data[1]['name']);
    }

    public function test_items_from_deleted_sessions_still_count(): void
    {
        $user = User::factory()->create();
        $session = ShoppingSession::factory()->create(['user_id' => $user->id, 'status' => 'active']);
        $this->addItem($user, $session, 'Frango');
        $session->delete(); // cascade soft-deleta o item

        $data = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/shopping/items/frequent')
            ->assertOk()
            ->json('data');

        $this->assertSame('Frango', $data[0]['name']);
    }

    public function test_frequent_items_are_user_isolated(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $otherSession = ShoppingSession::factory()->create(['user_id' => $other->id, 'status' => 'active']);
        $this->addItem($other, $otherSession, 'Carne alheia');

        $data = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/shopping/items/frequent')
            ->assertOk()
            ->json('data');

        $this->assertSame([], $data);
    }
}
