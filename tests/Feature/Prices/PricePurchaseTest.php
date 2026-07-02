<?php

namespace Tests\Feature\Prices;

use App\Domains\Prices\Models\PriceProduct;
use App\Domains\Prices\Models\PricePurchase;
use App\Domains\Prices\Models\PriceSale;
use App\Domains\Prices\Models\PriceStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricePurchaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/prices/purchases')->assertUnauthorized();
    }

    public function test_creates_a_purchase(): void
    {
        $user = User::factory()->create();
        $product = PriceProduct::factory()->create(['user_id' => $user->id]);
        $store = PriceStore::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/prices/purchases', [
                'product_id' => $product->id,
                'store_id' => $store->id,
                'price_paid' => 2178,
                'purchased_at' => '2024-05-10',
                'warranty_months' => 36,
            ])
            ->assertCreated()
            ->assertJsonPath('data.price_paid', 2178)
            ->assertJsonPath('data.purchased_at', '2024-05-10')
            ->assertJsonPath('data.warranty_months', 36)
            ->assertJsonPath('data.is_sold', false);
    }

    public function test_requires_price_paid_to_create(): void
    {
        $user = User::factory()->create();
        $product = PriceProduct::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/prices/purchases', [
                'product_id' => $product->id,
                'purchased_at' => '2024-05-10',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['price_paid']);
    }

    public function test_rejects_product_of_another_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $product = PriceProduct::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/prices/purchases', [
                'product_id' => $product->id,
                'price_paid' => 1000,
                'purchased_at' => '2024-05-10',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['product_id']);
    }

    public function test_index_marks_sold_purchases(): void
    {
        $user = User::factory()->create();
        $purchase = PricePurchase::factory()->create(['user_id' => $user->id]);
        PriceSale::factory()->create(['user_id' => $user->id, 'purchase_id' => $purchase->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/prices/purchases')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.is_sold', true)
            ->assertJsonPath('data.0.sale.purchase_id', $purchase->id);
    }

    public function test_cannot_update_another_users_purchase(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $purchase = PricePurchase::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/prices/purchases/{$purchase->id}", ['price_paid' => 1])
            ->assertForbidden();
    }

    public function test_updates_current_value(): void
    {
        $user = User::factory()->create();
        $purchase = PricePurchase::factory()->create(['user_id' => $user->id, 'current_value' => null]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/prices/purchases/{$purchase->id}", ['current_value' => 850.50])
            ->assertOk()
            ->assertJsonPath('data.current_value', 850.5);
    }

    public function test_soft_deletes_a_purchase(): void
    {
        $user = User::factory()->create();
        $purchase = PricePurchase::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/prices/purchases/{$purchase->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('price_purchases', ['id' => $purchase->id]);
    }
}
