<?php

namespace Tests\Feature\Bookmarks;

use App\Domains\Bookmarks\Models\Bookmark;
use App\Domains\Bookmarks\Models\BookmarkCategory;
use App\Domains\Bookmarks\Models\BookmarkCollection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookmarkCollectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/bookmarks/collections')->assertUnauthorized();
    }

    public function test_lists_only_collections_of_authenticated_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        BookmarkCollection::factory()->count(2)->create(['user_id' => $user->id]);
        BookmarkCollection::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/bookmarks/collections')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_creates_a_collection_with_name(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/bookmarks/collections', ['name' => 'Dev'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Dev');

        $this->assertDatabaseHas('bookmark_collections', ['user_id' => $user->id, 'name' => 'Dev']);
    }

    public function test_creates_a_collection_with_icon_and_color(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/bookmarks/collections', [
                'name' => 'Tech',
                'icon' => 'Code',
                'color' => '#00C896',
            ])
            ->assertCreated()
            ->assertJsonPath('data.icon', 'Code')
            ->assertJsonPath('data.color', '#00C896');
    }

    public function test_updates_collection_name(): void
    {
        $user = User::factory()->create();
        $collection = BookmarkCollection::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/bookmarks/collections/{$collection->id}", ['name' => 'Novo Nome'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Novo Nome');
    }

    public function test_updates_collection_icon_and_color(): void
    {
        $user = User::factory()->create();
        $collection = BookmarkCollection::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/bookmarks/collections/{$collection->id}", [
                'icon' => 'Globe',
                'color' => '#3b82f6',
            ])
            ->assertOk()
            ->assertJsonPath('data.icon', 'Globe')
            ->assertJsonPath('data.color', '#3b82f6');
    }

    public function test_soft_deletes_a_collection(): void
    {
        $user = User::factory()->create();
        $collection = BookmarkCollection::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/bookmarks/collections/{$collection->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('bookmark_collections', ['id' => $collection->id]);
    }

    public function test_cannot_delete_collection_of_another_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $collection = BookmarkCollection::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/bookmarks/collections/{$collection->id}")
            ->assertForbidden();
    }

    public function test_reorders_collections(): void
    {
        $user = User::factory()->create();
        $col1 = BookmarkCollection::factory()->create(['user_id' => $user->id, 'position' => 0]);
        $col2 = BookmarkCollection::factory()->create(['user_id' => $user->id, 'position' => 1]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/bookmarks/collections/reorder', [
                'items' => [
                    ['id' => $col1->id, 'position' => 1],
                    ['id' => $col2->id, 'position' => 0],
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('bookmark_collections', ['id' => $col1->id, 'position' => 1]);
        $this->assertDatabaseHas('bookmark_collections', ['id' => $col2->id, 'position' => 0]);
    }

    public function test_returns_bookmarks_count_in_response(): void
    {
        $user = User::factory()->create();
        $collection = BookmarkCollection::factory()->create(['user_id' => $user->id]);
        $category = BookmarkCategory::factory()->create([
            'bookmark_collection_id' => $collection->id,
            'user_id' => $user->id,
        ]);
        Bookmark::factory()->count(3)->create([
            'bookmark_category_id' => $category->id,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/bookmarks/collections')
            ->assertOk()
            ->assertJsonPath('data.0.bookmarks_count', 3);
    }
}
