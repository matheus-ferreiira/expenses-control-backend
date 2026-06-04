<?php

namespace Tests\Feature\Bookmarks;

use App\Domains\Bookmarks\Models\Bookmark;
use App\Domains\Bookmarks\Models\BookmarkCategory;
use App\Domains\Bookmarks\Models\BookmarkCollection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookmarkTest extends TestCase
{
    use RefreshDatabase;

    private function makeCategory(User $user): BookmarkCategory
    {
        $collection = BookmarkCollection::factory()->create(['user_id' => $user->id]);
        return BookmarkCategory::factory()->create([
            'bookmark_collection_id' => $collection->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_requires_authentication(): void
    {
        $user = User::factory()->create();
        $cat = $this->makeCategory($user);

        $this->getJson("/api/v1/bookmarks/categories/{$cat->id}/bookmarks")->assertUnauthorized();
    }

    public function test_lists_bookmarks_of_a_category(): void
    {
        $user = User::factory()->create();
        $cat = $this->makeCategory($user);
        Bookmark::factory()->count(5)->create(['bookmark_category_id' => $cat->id, 'user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/bookmarks/categories/{$cat->id}/bookmarks")
            ->assertOk()
            ->assertJsonCount(5, 'data');
    }

    public function test_cannot_list_bookmarks_of_another_users_category(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $cat = $this->makeCategory($other);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/bookmarks/categories/{$cat->id}/bookmarks")
            ->assertForbidden();
    }

    public function test_filters_bookmarks_by_search_term_in_title(): void
    {
        $user = User::factory()->create();
        $cat = $this->makeCategory($user);
        Bookmark::factory()->create(['bookmark_category_id' => $cat->id, 'user_id' => $user->id, 'title' => 'Tailwind CSS docs']);
        Bookmark::factory()->create(['bookmark_category_id' => $cat->id, 'user_id' => $user->id, 'title' => 'React guide']);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/bookmarks/categories/{$cat->id}/bookmarks?search=tailwind")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Tailwind CSS docs');
    }

    public function test_filters_bookmarks_by_search_term_in_description(): void
    {
        $user = User::factory()->create();
        $cat = $this->makeCategory($user);
        Bookmark::factory()->create([
            'bookmark_category_id' => $cat->id,
            'user_id' => $user->id,
            'title' => 'Generic link',
            'description' => 'This is about utility-first CSS',
        ]);
        Bookmark::factory()->create([
            'bookmark_category_id' => $cat->id,
            'user_id' => $user->id,
            'title' => 'Another link',
            'description' => null,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/bookmarks/categories/{$cat->id}/bookmarks?search=utility")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_filters_only_favorites(): void
    {
        $user = User::factory()->create();
        $cat = $this->makeCategory($user);
        Bookmark::factory()->count(2)->favorite()->create(['bookmark_category_id' => $cat->id, 'user_id' => $user->id]);
        Bookmark::factory()->count(3)->create(['bookmark_category_id' => $cat->id, 'user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/bookmarks/categories/{$cat->id}/bookmarks?favorites=1")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_creates_a_bookmark_with_title_and_url(): void
    {
        $user = User::factory()->create();
        $cat = $this->makeCategory($user);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/bookmarks/categories/{$cat->id}/bookmarks", [
                'title' => 'Tailwind CSS',
                'url' => 'https://tailwindcss.com',
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Tailwind CSS')
            ->assertJsonPath('data.url', 'https://tailwindcss.com');
    }

    public function test_creates_a_bookmark_with_description(): void
    {
        $user = User::factory()->create();
        $cat = $this->makeCategory($user);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/bookmarks/categories/{$cat->id}/bookmarks", [
                'title' => 'Docs',
                'url' => 'https://example.com',
                'description' => 'Useful reference',
            ])
            ->assertCreated()
            ->assertJsonPath('data.description', 'Useful reference');
    }

    public function test_cannot_create_bookmark_in_another_users_category(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $cat = $this->makeCategory($other);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/bookmarks/categories/{$cat->id}/bookmarks", [
                'title' => 'Hack',
                'url' => 'https://example.com',
            ])
            ->assertForbidden();
    }

    public function test_requires_title_to_create_bookmark(): void
    {
        $user = User::factory()->create();
        $cat = $this->makeCategory($user);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/bookmarks/categories/{$cat->id}/bookmarks", [
                'url' => 'https://example.com',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);
    }

    public function test_requires_url_to_create_bookmark(): void
    {
        $user = User::factory()->create();
        $cat = $this->makeCategory($user);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/bookmarks/categories/{$cat->id}/bookmarks", [
                'title' => 'Something',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['url']);
    }

    public function test_updates_bookmark_title(): void
    {
        $user = User::factory()->create();
        $cat = $this->makeCategory($user);
        $bookmark = Bookmark::factory()->create(['bookmark_category_id' => $cat->id, 'user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/bookmarks/bookmarks/{$bookmark->id}", ['title' => 'Novo Título'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Novo Título');
    }

    public function test_updates_bookmark_url(): void
    {
        $user = User::factory()->create();
        $cat = $this->makeCategory($user);
        $bookmark = Bookmark::factory()->create(['bookmark_category_id' => $cat->id, 'user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/bookmarks/bookmarks/{$bookmark->id}", ['url' => 'https://novo.com'])
            ->assertOk()
            ->assertJsonPath('data.url', 'https://novo.com');
    }

    public function test_updates_bookmark_description(): void
    {
        $user = User::factory()->create();
        $cat = $this->makeCategory($user);
        $bookmark = Bookmark::factory()->create(['bookmark_category_id' => $cat->id, 'user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/bookmarks/bookmarks/{$bookmark->id}", ['description' => 'Nova descrição'])
            ->assertOk()
            ->assertJsonPath('data.description', 'Nova descrição');
    }

    public function test_moves_bookmark_to_another_category(): void
    {
        $user = User::factory()->create();
        $collection = BookmarkCollection::factory()->create(['user_id' => $user->id]);
        $cat1 = BookmarkCategory::factory()->create(['bookmark_collection_id' => $collection->id, 'user_id' => $user->id]);
        $cat2 = BookmarkCategory::factory()->create(['bookmark_collection_id' => $collection->id, 'user_id' => $user->id]);
        $bookmark = Bookmark::factory()->create(['bookmark_category_id' => $cat1->id, 'user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/bookmarks/bookmarks/{$bookmark->id}", ['bookmark_category_id' => $cat2->id])
            ->assertOk()
            ->assertJsonPath('data.bookmark_category_id', $cat2->id);
    }

    public function test_toggles_is_favorite_to_true(): void
    {
        $user = User::factory()->create();
        $cat = $this->makeCategory($user);
        $bookmark = Bookmark::factory()->create([
            'bookmark_category_id' => $cat->id,
            'user_id' => $user->id,
            'is_favorite' => false,
        ]);

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/bookmarks/bookmarks/{$bookmark->id}/favorite")
            ->assertOk()
            ->assertJsonPath('data.is_favorite', true);
    }

    public function test_toggles_is_favorite_to_false(): void
    {
        $user = User::factory()->create();
        $cat = $this->makeCategory($user);
        $bookmark = Bookmark::factory()->favorite()->create([
            'bookmark_category_id' => $cat->id,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/bookmarks/bookmarks/{$bookmark->id}/favorite")
            ->assertOk()
            ->assertJsonPath('data.is_favorite', false);
    }

    public function test_soft_deletes_a_bookmark(): void
    {
        $user = User::factory()->create();
        $cat = $this->makeCategory($user);
        $bookmark = Bookmark::factory()->create(['bookmark_category_id' => $cat->id, 'user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/bookmarks/bookmarks/{$bookmark->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('bookmarks', ['id' => $bookmark->id]);
    }

    public function test_cannot_delete_bookmark_of_another_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $cat = $this->makeCategory($other);
        $bookmark = Bookmark::factory()->create(['bookmark_category_id' => $cat->id, 'user_id' => $other->id]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/bookmarks/bookmarks/{$bookmark->id}")
            ->assertForbidden();
    }

    public function test_returns_favicon_url_in_response(): void
    {
        $user = User::factory()->create();
        $cat = $this->makeCategory($user);
        Bookmark::factory()->create([
            'bookmark_category_id' => $cat->id,
            'user_id' => $user->id,
            'url' => 'https://tailwindcss.com/docs',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/bookmarks/categories/{$cat->id}/bookmarks")
            ->assertOk();

        $faviconUrl = $response->json('data.0.favicon_url');
        $this->assertNotNull($faviconUrl);
        $this->assertStringContainsString('tailwindcss.com', $faviconUrl);
        $this->assertStringContainsString('favicons', $faviconUrl);
    }

    public function test_reorders_bookmarks(): void
    {
        $user = User::factory()->create();
        $cat = $this->makeCategory($user);
        $bm1 = Bookmark::factory()->create(['bookmark_category_id' => $cat->id, 'user_id' => $user->id, 'position' => 0]);
        $bm2 = Bookmark::factory()->create(['bookmark_category_id' => $cat->id, 'user_id' => $user->id, 'position' => 1]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/bookmarks/categories/{$cat->id}/bookmarks/reorder", [
                'items' => [
                    ['id' => $bm1->id, 'position' => 1],
                    ['id' => $bm2->id, 'position' => 0],
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('bookmarks', ['id' => $bm1->id, 'position' => 1]);
        $this->assertDatabaseHas('bookmarks', ['id' => $bm2->id, 'position' => 0]);
    }
}
