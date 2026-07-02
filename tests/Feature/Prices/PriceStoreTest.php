<?php

namespace Tests\Feature\Prices;

use App\Domains\Prices\Models\PriceStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/prices/stores')->assertUnauthorized();
    }

    public function test_lists_only_own_stores(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        PriceStore::factory()->count(2)->create(['user_id' => $user->id]);
        PriceStore::factory()->count(3)->create(['user_id' => $other->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/prices/stores')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_creates_a_store(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/prices/stores', [
                'name' => 'Kabum',
                'website_url' => 'https://www.kabum.com.br',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Kabum')
            ->assertJsonPath('data.website_url', 'https://www.kabum.com.br');
    }

    public function test_requires_name_to_create(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/prices/stores', ['website_url' => 'https://example.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_rejects_invalid_website_url(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/prices/stores', ['name' => 'Loja', 'website_url' => 'not-a-url'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['website_url']);
    }

    public function test_cannot_view_another_users_store(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $store = PriceStore::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/prices/stores/{$store->id}")
            ->assertForbidden();
    }

    public function test_soft_deletes_a_store(): void
    {
        $user = User::factory()->create();
        $store = PriceStore::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/prices/stores/{$store->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('price_stores', ['id' => $store->id]);
    }
}
