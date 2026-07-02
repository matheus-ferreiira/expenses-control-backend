<?php

namespace Tests\Feature\Prices;

use App\Domains\Prices\Models\PricePurchase;
use App\Domains\Prices\Models\PriceSale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceSaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/prices/sales')->assertUnauthorized();
    }

    public function test_creates_a_sale(): void
    {
        $user = User::factory()->create();
        $purchase = PricePurchase::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/prices/sales', [
                'purchase_id' => $purchase->id,
                'sale_price' => 1650,
                'sold_at' => '2026-04-01',
            ])
            ->assertCreated()
            ->assertJsonPath('data.sale_price', 1650)
            ->assertJsonPath('data.sold_at', '2026-04-01')
            ->assertJsonPath('data.purchase_id', $purchase->id);
    }

    public function test_requires_sale_price_to_create(): void
    {
        $user = User::factory()->create();
        $purchase = PricePurchase::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/prices/sales', [
                'purchase_id' => $purchase->id,
                'sold_at' => '2026-04-01',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sale_price']);
    }

    public function test_rejects_purchase_of_another_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $purchase = PricePurchase::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/prices/sales', [
                'purchase_id' => $purchase->id,
                'sale_price' => 100,
                'sold_at' => '2026-04-01',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['purchase_id']);
    }

    public function test_rejects_second_sale_for_same_purchase(): void
    {
        $user = User::factory()->create();
        $purchase = PricePurchase::factory()->create(['user_id' => $user->id]);
        PriceSale::factory()->create(['user_id' => $user->id, 'purchase_id' => $purchase->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/prices/sales', [
                'purchase_id' => $purchase->id,
                'sale_price' => 900,
                'sold_at' => '2026-05-01',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['purchase_id']);
    }

    public function test_updates_a_sale(): void
    {
        $user = User::factory()->create();
        $sale = PriceSale::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/prices/sales/{$sale->id}", ['sale_price' => 1234.56])
            ->assertOk()
            ->assertJsonPath('data.sale_price', 1234.56);
    }

    public function test_cannot_delete_another_users_sale(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $sale = PriceSale::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/prices/sales/{$sale->id}")
            ->assertForbidden();
    }

    public function test_soft_deletes_a_sale(): void
    {
        $user = User::factory()->create();
        $sale = PriceSale::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/prices/sales/{$sale->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('price_sales', ['id' => $sale->id]);
    }
}
