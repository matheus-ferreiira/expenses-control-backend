<?php

namespace Database\Factories;

use App\Domains\Bookmarks\Models\BookmarkCategory;
use App\Domains\Bookmarks\Models\BookmarkCollection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookmarkCategoryFactory extends Factory
{
    protected $model = BookmarkCategory::class;

    public function definition(): array
    {
        return [
            'bookmark_collection_id' => BookmarkCollection::factory(),
            'user_id' => User::factory(),
            'name' => $this->faker->word(),
            'position' => 0,
        ];
    }
}
