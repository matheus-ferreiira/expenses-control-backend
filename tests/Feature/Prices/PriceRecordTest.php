<?php

namespace Tests\Feature\Prices;

use App\Domains\Prices\Models\PriceProduct;
use App\Domains\Prices\Models\PriceRecord;
use App\Domains\Prices\Models\PriceStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceRecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/prices/price-records')->assertUnauthorized();
    }

    public function test_creates_a_price_record(): void
    {
        $user = User::factory()->create();
        $product = PriceProduct::factory()->create(['user_id' => $user->id, 'target_price' => 4000]);
        $store = PriceStore::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/prices/price-records', [
                'product_id' => $product->id,
                'store_id' => $store->id,
                'price' => 3899.90,
                'recorded_at' => '2026-06-15',
                'url' => 'https://example.com/offer',
            ])
            ->assertCreated()
            ->assertJsonPath('data.price', 3899.9)
            ->assertJsonPath('data.recorded_at', '2026-06-15')
            ->assertJsonPath('data.goal_status', 'excellent')
            ->assertJsonPath('data.product.id', $product->id)
            ->assertJsonPath('data.store.id', $store->id);
    }

    public function test_accepts_past_dates(): void
    {
        $user = User::factory()->create();
        $product = PriceProduct::factory()->create(['user_id' => $user->id]);
        $store = PriceStore::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/prices/price-records', [
                'product_id' => $product->id,
                'store_id' => $store->id,
                'price' => 100,
                'recorded_at' => '2020-01-01',
            ])
            ->assertCreated()
            ->assertJsonPath('data.recorded_at', '2020-01-01');
    }

    public function test_requires_price_to_create(): void
    {
        $user = User::factory()->create();
        $product = PriceProduct::factory()->create(['user_id' => $user->id]);
        $store = PriceStore::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/prices/price-records', [
                'product_id' => $product->id,
                'store_id' => $store->id,
                'recorded_at' => '2026-06-15',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['price']);
    }

    public function test_rejects_product_of_another_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $product = PriceProduct::factory()->create(['user_id' => $other->id]);
        $store = PriceStore::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/prices/price-records', [
                'product_id' => $product->id,
                'store_id' => $store->id,
                'price' => 100,
                'recorded_at' => '2026-06-15',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['product_id']);
    }

    public function test_index_is_paginated_and_filters_by_product(): void
    {
        $user = User::factory()->create();
        $productA = PriceProduct::factory()->create(['user_id' => $user->id]);
        $productB = PriceProduct::factory()->create(['user_id' => $user->id]);
        $store = PriceStore::factory()->create(['user_id' => $user->id]);

        PriceRecord::factory()->count(3)->create([
            'user_id' => $user->id, 'product_id' => $productA->id, 'store_id' => $store->id,
        ]);
        PriceRecord::factory()->count(2)->create([
            'user_id' => $user->id, 'product_id' => $productB->id, 'store_id' => $store->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/prices/price-records?product_id={$productA->id}")
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.per_page', 50);
    }

    public function test_filters_by_date_range(): void
    {
        $user = User::factory()->create();
        $product = PriceProduct::factory()->create(['user_id' => $user->id]);
        $store = PriceStore::factory()->create(['user_id' => $user->id]);

        PriceRecord::factory()->create([
            'user_id' => $user->id, 'product_id' => $product->id, 'store_id' => $store->id,
            'recorded_at' => '2026-01-15',
        ]);
        PriceRecord::factory()->create([
            'user_id' => $user->id, 'product_id' => $product->id, 'store_id' => $store->id,
            'recorded_at' => '2026-05-20',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/prices/price-records?date_from=2026-05-01&date_to=2026-05-31')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.recorded_at', '2026-05-20');
    }

    public function test_cannot_delete_another_users_record(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $record = PriceRecord::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/prices/price-records/{$record->id}")
            ->assertForbidden();
    }

    public function test_soft_deletes_a_record(): void
    {
        $user = User::factory()->create();
        $record = PriceRecord::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/prices/price-records/{$record->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('price_records', ['id' => $record->id]);
    }
}
