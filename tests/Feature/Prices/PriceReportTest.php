<?php

namespace Tests\Feature\Prices;

use App\Domains\Prices\Models\PriceProduct;
use App\Domains\Prices\Models\PricePurchase;
use App\Domains\Prices\Models\PriceRecord;
use App\Domains\Prices\Models\PriceSale;
use App\Domains\Prices\Models\PriceStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_requires_authentication(): void
    {
        $this->getJson('/api/v1/prices/dashboard')->assertUnauthorized();
    }

    public function test_dashboard_returns_counts_and_goal_status(): void
    {
        $user = User::factory()->create();
        $store = PriceStore::factory()->create(['user_id' => $user->id]);
        $product = PriceProduct::factory()->create([
            'user_id' => $user->id,
            'name' => 'RX 9070 XT',
            'target_price' => 4000,
            'launch_price' => 5200,
        ]);

        PriceRecord::factory()->create([
            'user_id' => $user->id, 'product_id' => $product->id, 'store_id' => $store->id,
            'price' => 4500, 'recorded_at' => '2026-01-10',
        ]);
        PriceRecord::factory()->create([
            'user_id' => $user->id, 'product_id' => $product->id, 'store_id' => $store->id,
            'price' => 4100, 'recorded_at' => '2026-02-10',
        ]);
        PriceRecord::factory()->create([
            'user_id' => $user->id, 'product_id' => $product->id, 'store_id' => $store->id,
            'price' => 3900, 'recorded_at' => '2026-03-10',
        ]);

        // Product without target price — should be sorted after the excellent one
        $noTarget = PriceProduct::factory()->create([
            'user_id' => $user->id, 'name' => 'Monitor', 'target_price' => null, 'launch_price' => null,
        ]);
        PriceRecord::factory()->create([
            'user_id' => $user->id, 'product_id' => $noTarget->id, 'store_id' => $store->id,
            'price' => 1500, 'recorded_at' => '2026-03-01',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/prices/dashboard')
            ->assertOk()
            ->assertJsonPath('data.counts.products_total', 2)
            ->assertJsonPath('data.counts.products_tracking', 2)
            ->assertJsonPath('data.counts.products_purchased', 0)
            ->assertJsonPath('data.counts.records_total', 4)
            ->assertJsonPath('data.counts.stores_total', 1)
            ->assertJsonPath('data.tracked_products.0.id', $product->id)
            ->assertJsonPath('data.tracked_products.0.last_price', 3900)
            ->assertJsonPath('data.tracked_products.0.min_price', 3900)
            ->assertJsonPath('data.tracked_products.0.goal_status', 'excellent')
            ->assertJsonPath('data.tracked_products.0.savings_vs_launch', 1300)
            ->assertJsonPath('data.tracked_products.1.id', $noTarget->id)
            ->assertJsonPath('data.tracked_products.1.goal_status', null)
            ->assertJsonCount(4, 'data.recent_records');
    }

    public function test_dashboard_goal_status_near_and_above(): void
    {
        $user = User::factory()->create();
        $store = PriceStore::factory()->create(['user_id' => $user->id]);

        $near = PriceProduct::factory()->create(['user_id' => $user->id, 'name' => 'Near', 'target_price' => 4000]);
        PriceRecord::factory()->create([
            'user_id' => $user->id, 'product_id' => $near->id, 'store_id' => $store->id,
            'price' => 4300, 'recorded_at' => '2026-06-01',
        ]);

        $above = PriceProduct::factory()->create(['user_id' => $user->id, 'name' => 'Above', 'target_price' => 4000]);
        PriceRecord::factory()->create([
            'user_id' => $user->id, 'product_id' => $above->id, 'store_id' => $store->id,
            'price' => 4500, 'recorded_at' => '2026-06-01',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/prices/dashboard')
            ->assertOk()
            ->assertJsonPath('data.tracked_products.0.goal_status', 'near')
            ->assertJsonPath('data.tracked_products.1.goal_status', 'above');
    }

    public function test_price_history_groups_by_store(): void
    {
        $user = User::factory()->create();
        $product = PriceProduct::factory()->create([
            'user_id' => $user->id, 'target_price' => 4000, 'launch_price' => 5200,
        ]);
        $storeA = PriceStore::factory()->create(['user_id' => $user->id, 'name' => 'Kabum']);
        $storeB = PriceStore::factory()->create(['user_id' => $user->id, 'name' => 'Pichau']);

        PriceRecord::factory()->create([
            'user_id' => $user->id, 'product_id' => $product->id, 'store_id' => $storeA->id,
            'price' => 4500, 'recorded_at' => '2026-01-10',
        ]);
        PriceRecord::factory()->create([
            'user_id' => $user->id, 'product_id' => $product->id, 'store_id' => $storeA->id,
            'price' => 3900, 'recorded_at' => '2026-03-10',
        ]);
        PriceRecord::factory()->create([
            'user_id' => $user->id, 'product_id' => $product->id, 'store_id' => $storeB->id,
            'price' => 4200, 'recorded_at' => '2026-02-01',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/prices/products/{$product->id}/price-history")
            ->assertOk()
            ->assertJsonPath('data.product.id', $product->id)
            ->assertJsonPath('data.stats.min', 3900)
            ->assertJsonPath('data.stats.max', 4500)
            ->assertJsonPath('data.stats.avg', 4200)
            ->assertJsonPath('data.stats.last', 3900)
            ->assertJsonPath('data.stats.records_count', 3)
            ->assertJsonPath('data.stats.savings_vs_launch', 1300)
            ->assertJsonCount(2, 'data.series');

        // Points within each series are sorted ascending by date
        $seriesA = collect($response->json('data.series'))->firstWhere('store.name', 'Kabum');
        $this->assertSame(['2026-01-10', '2026-03-10'], array_column($seriesA['points'], 'date'));
    }

    public function test_price_history_forbidden_for_another_users_product(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $product = PriceProduct::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/prices/products/{$product->id}/price-history")
            ->assertForbidden();
    }

    public function test_compare_returns_stats_for_each_product(): void
    {
        $user = User::factory()->create();
        $store = PriceStore::factory()->create(['user_id' => $user->id]);
        $productA = PriceProduct::factory()->create(['user_id' => $user->id, 'target_price' => 4000]);
        $productB = PriceProduct::factory()->create(['user_id' => $user->id, 'target_price' => null]);

        PriceRecord::factory()->create([
            'user_id' => $user->id, 'product_id' => $productA->id, 'store_id' => $store->id,
            'price' => 3900, 'recorded_at' => '2026-03-10',
        ]);
        PriceRecord::factory()->create([
            'user_id' => $user->id, 'product_id' => $productB->id, 'store_id' => $store->id,
            'price' => 2100, 'recorded_at' => '2026-03-11',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/prices/compare?product_ids[]={$productA->id}&product_ids[]={$productB->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data.products')
            ->assertJsonPath('data.products.0.id', $productA->id)
            ->assertJsonPath('data.products.0.stats.last', 3900)
            ->assertJsonPath('data.products.0.stats.goal_status', 'excellent')
            ->assertJsonPath('data.products.1.id', $productB->id)
            ->assertJsonPath('data.products.1.stats.goal_status', null)
            ->assertJsonPath('data.products.1.series.0.date', '2026-03-11');
    }

    public function test_compare_requires_at_least_two_products(): void
    {
        $user = User::factory()->create();
        $product = PriceProduct::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/prices/compare?product_ids[]={$product->id}")
            ->assertUnprocessable();
    }

    public function test_compare_rejects_products_of_another_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $own = PriceProduct::factory()->create(['user_id' => $user->id]);
        $foreign = PriceProduct::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/prices/compare?product_ids[]={$own->id}&product_ids[]={$foreign->id}")
            ->assertUnprocessable();
    }

    public function test_patrimony_calculates_totals(): void
    {
        $user = User::factory()->create();
        $productA = PriceProduct::factory()->create(['user_id' => $user->id, 'name' => 'GPU antiga']);
        $productB = PriceProduct::factory()->create(['user_id' => $user->id, 'name' => 'SSD']);

        // Sold purchase: paid 2178, sold for 1650
        $sold = PricePurchase::factory()->create([
            'user_id' => $user->id, 'product_id' => $productA->id,
            'price_paid' => 2178, 'purchased_at' => '2024-05-10', 'current_value' => null,
        ]);
        PriceSale::factory()->create([
            'user_id' => $user->id, 'purchase_id' => $sold->id,
            'sale_price' => 1650, 'sold_at' => '2026-04-01',
        ]);

        // Owned purchase with depreciation: paid 1000, worth 600 now
        PricePurchase::factory()->create([
            'user_id' => $user->id, 'product_id' => $productB->id,
            'price_paid' => 1000, 'purchased_at' => '2023-03-15', 'current_value' => 600,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/prices/patrimony')
            ->assertOk()
            ->assertJsonPath('data.totals.invested', 3178)
            ->assertJsonPath('data.totals.recovered', 1650)
            ->assertJsonPath('data.totals.current_worth', 600)
            ->assertJsonPath('data.totals.depreciation', 400)
            ->assertJsonPath('data.totals.net_cost', 1528)
            ->assertJsonCount(2, 'data.by_year')
            ->assertJsonPath('data.by_year.0.year', 2024)
            ->assertJsonPath('data.by_year.0.invested', 2178)
            ->assertJsonPath('data.by_year.0.items.0.is_sold', true)
            ->assertJsonPath('data.by_year.0.items.0.sale_price', 1650)
            ->assertJsonPath('data.by_year.0.items.0.sold_at', '2026-04-01')
            ->assertJsonPath('data.by_year.1.year', 2023)
            ->assertJsonPath('data.by_year.1.items.0.is_sold', false)
            ->assertJsonPath('data.by_year.1.items.0.current_value', 600);
    }

    public function test_patrimony_only_includes_own_data(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        PricePurchase::factory()->create(['user_id' => $other->id, 'price_paid' => 9999]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/prices/patrimony')
            ->assertOk()
            ->assertJsonPath('data.totals.invested', 0)
            ->assertJsonCount(0, 'data.by_year');
    }
}
