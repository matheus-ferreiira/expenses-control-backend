<?php

namespace Tests\Feature\Prices;

use App\Domains\Prices\Models\PriceCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/prices/categories')->assertUnauthorized();
    }

    public function test_lists_only_own_categories(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        PriceCategory::factory()->count(3)->create(['user_id' => $user->id]);
        PriceCategory::factory()->count(2)->create(['user_id' => $other->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/prices/categories')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_creates_a_category(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/prices/categories', [
                'name' => 'Placa de vídeo',
                'icon' => 'cpu',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Placa de vídeo')
            ->assertJsonPath('data.icon', 'cpu');
    }

    public function test_requires_name_to_create(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/prices/categories', ['icon' => 'cpu'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_updates_a_category(): void
    {
        $user = User::factory()->create();
        $category = PriceCategory::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/prices/categories/{$category->id}", ['name' => 'Monitores'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Monitores');
    }

    public function test_cannot_update_another_users_category(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $category = PriceCategory::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/prices/categories/{$category->id}", ['name' => 'Hack'])
            ->assertForbidden();
    }

    public function test_soft_deletes_a_category(): void
    {
        $user = User::factory()->create();
        $category = PriceCategory::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/prices/categories/{$category->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('price_categories', ['id' => $category->id]);
    }
}
