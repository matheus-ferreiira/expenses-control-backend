<?php

namespace Tests\Feature\Bookmarks;

use App\Domains\Bookmarks\Models\Bookmark;
use App\Domains\Bookmarks\Models\BookmarkCategory;
use App\Domains\Bookmarks\Models\BookmarkCollection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookmarkCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_authentication(): void
    {
        $user = User::factory()->create();
        $collection = BookmarkCollection::factory()->create(['user_id' => $user->id]);

        $this->getJson("/api/v1/bookmarks/collections/{$collection->id}/categories")
            ->assertUnauthorized();
    }

    public function test_lists_categories_of_a_collection(): void
    {
        $user = User::factory()->create();
        $collection = BookmarkCollection::factory()->create(['user_id' => $user->id]);
        BookmarkCategory::factory()->count(3)->create([
            'bookmark_collection_id' => $collection->id,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/bookmarks/collections/{$collection->id}/categories")
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_cannot_list_categories_of_another_users_collection(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $collection = BookmarkCollection::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/bookmarks/collections/{$collection->id}/categories")
            ->assertForbidden();
    }

    public function test_creates_a_category_in_a_collection(): void
    {
        $user = User::factory()->create();
        $collection = BookmarkCollection::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/bookmarks/collections/{$collection->id}/categories", ['name' => 'Frontend'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Frontend');

        $this->assertDatabaseHas('bookmark_categories', [
            'bookmark_collection_id' => $collection->id,
            'name' => 'Frontend',
        ]);
    }

    public function test_cannot_create_category_in_another_users_collection(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $collection = BookmarkCollection::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/bookmarks/collections/{$collection->id}/categories", ['name' => 'Hack'])
            ->assertForbidden();
    }

    public function test_updates_category_name(): void
    {
        $user = User::factory()->create();
        $collection = BookmarkCollection::factory()->create(['user_id' => $user->id]);
        $category = BookmarkCategory::factory()->create([
            'bookmark_collection_id' => $collection->id,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/bookmarks/categories/{$category->id}", ['name' => 'Backend'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Backend');
    }

    public function test_soft_deletes_a_category(): void
    {
        $user = User::factory()->create();
        $collection = BookmarkCollection::factory()->create(['user_id' => $user->id]);
        $category = BookmarkCategory::factory()->create([
            'bookmark_collection_id' => $collection->id,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/bookmarks/categories/{$category->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('bookmark_categories', ['id' => $category->id]);
    }

    public function test_cannot_delete_category_of_another_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $collection = BookmarkCollection::factory()->create(['user_id' => $other->id]);
        $category = BookmarkCategory::factory()->create([
            'bookmark_collection_id' => $collection->id,
            'user_id' => $other->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/bookmarks/categories/{$category->id}")
            ->assertForbidden();
    }

    public function test_reorders_categories(): void
    {
        $user = User::factory()->create();
        $collection = BookmarkCollection::factory()->create(['user_id' => $user->id]);
        $cat1 = BookmarkCategory::factory()->create([
            'bookmark_collection_id' => $collection->id,
            'user_id' => $user->id,
            'position' => 0,
        ]);
        $cat2 = BookmarkCategory::factory()->create([
            'bookmark_collection_id' => $collection->id,
            'user_id' => $user->id,
            'position' => 1,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/bookmarks/collections/{$collection->id}/categories/reorder", [
                'items' => [
                    ['id' => $cat1->id, 'position' => 1],
                    ['id' => $cat2->id, 'position' => 0],
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('bookmark_categories', ['id' => $cat1->id, 'position' => 1]);
        $this->assertDatabaseHas('bookmark_categories', ['id' => $cat2->id, 'position' => 0]);
    }

    public function test_returns_bookmarks_count_in_response(): void
    {
        $user = User::factory()->create();
        $collection = BookmarkCollection::factory()->create(['user_id' => $user->id]);
        $category = BookmarkCategory::factory()->create([
            'bookmark_collection_id' => $collection->id,
            'user_id' => $user->id,
        ]);
        Bookmark::factory()->count(4)->create([
            'bookmark_category_id' => $category->id,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/bookmarks/collections/{$collection->id}/categories")
            ->assertOk()
            ->assertJsonPath('data.0.bookmarks_count', 4);
    }
}
