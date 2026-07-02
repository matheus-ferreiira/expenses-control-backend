<?php

namespace Tests\Feature\Prices;

use App\Domains\Prices\Models\PriceCategory;
use App\Domains\Prices\Models\PriceProduct;
use App\Domains\Prices\Models\PriceRecord;
use App\Domains\Prices\Models\PriceStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/prices/products')->assertUnauthorized();
    }

    public function test_creates_a_product(): void
    {
        $user = User::factory()->create();
        $category = PriceCategory::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/prices/products', [
                'name' => 'RX 9070 XT',
                'category_id' => $category->id,
                'brand' => 'AMD',
                'target_price' => 4000,
                'launch_price' => 5200,
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'RX 9070 XT')
            ->assertJsonPath('data.target_price', 4000)
            ->assertJsonPath('data.status', 'tracking')
            ->assertJsonPath('data.category.id', $category->id);
    }

    public function test_requires_name_to_create(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/prices/products', ['brand' => 'AMD'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_rejects_category_of_another_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $category = PriceCategory::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/prices/products', [
                'name' => 'RTX 5080',
                'category_id' => $category->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category_id']);
    }

    public function test_index_includes_aggregated_stats(): void
    {
        $user = User::factory()->create();
        $product = PriceProduct::factory()->create(['user_id' => $user->id, 'target_price' => 4000]);
        $store = PriceStore::factory()->create(['user_id' => $user->id]);

        PriceRecord::factory()->create([
            'user_id' => $user->id, 'product_id' => $product->id, 'store_id' => $store->id,
            'price' => 4500, 'recorded_at' => '2026-01-10',
        ]);
        PriceRecord::factory()->create([
            'user_id' => $user->id, 'product_id' => $product->id, 'store_id' => $store->id,
            'price' => 3900, 'recorded_at' => '2026-03-01',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/prices/products')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.stats.min_price', 3900)
            ->assertJsonPath('data.0.stats.max_price', 4500)
            ->assertJsonPath('data.0.stats.avg_price', 4200)
            ->assertJsonPath('data.0.stats.last_price', 3900)
            ->assertJsonPath('data.0.stats.last_recorded_at', '2026-03-01')
            ->assertJsonPath('data.0.stats.records_count', 2)
            ->assertJsonPath('data.0.stats.goal_status', 'excellent');
    }

    public function test_filters_by_status(): void
    {
        $user = User::factory()->create();
        PriceProduct::factory()->create(['user_id' => $user->id]);
        PriceProduct::factory()->purchased()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/prices/products?status=purchased')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'purchased');
    }

    public function test_filters_by_search_on_name_brand_and_model(): void
    {
        $user = User::factory()->create();
        PriceProduct::factory()->create(['user_id' => $user->id, 'name' => 'RX 9070 XT', 'brand' => 'AMD', 'model' => 'Nitro+']);
        PriceProduct::factory()->create(['user_id' => $user->id, 'name' => 'Monitor Ultrawide', 'brand' => 'LG', 'model' => '34WP65C']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/prices/products?search=nitro')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'RX 9070 XT');
    }

    public function test_lists_only_own_products(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        PriceProduct::factory()->count(2)->create(['user_id' => $user->id]);
        PriceProduct::factory()->count(3)->create(['user_id' => $other->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/prices/products')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_cannot_view_another_users_product(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $product = PriceProduct::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/prices/products/{$product->id}")
            ->assertForbidden();
    }

    public function test_updates_product_status(): void
    {
        $user = User::factory()->create();
        $product = PriceProduct::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/prices/products/{$product->id}", ['status' => 'purchased'])
            ->assertOk()
            ->assertJsonPath('data.status', 'purchased');
    }

    public function test_soft_deletes_a_product(): void
    {
        $user = User::factory()->create();
        $product = PriceProduct::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/prices/products/{$product->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('price_products', ['id' => $product->id]);
    }
}
