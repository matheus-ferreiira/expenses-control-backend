<?php

namespace Database\Factories;

use App\Domains\Bookmarks\Models\Bookmark;
use App\Domains\Bookmarks\Models\BookmarkCollection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookmarkFactory extends Factory
{
    protected $model = Bookmark::class;

    public function definition(): array
    {
        return [
            'bookmark_collection_id' => BookmarkCollection::factory(),
            'user_id' => User::factory(),
            'title' => $this->faker->words(3, true),
            'url' => $this->faker->url(),
            'description' => null,
            'is_favorite' => false,
            'position' => 0,
        ];
    }

    public function favorite(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_favorite' => true,
        ]);
    }

    public function withDescription(): static
    {
        return $this->state(fn (array $attributes) => [
            'description' => $this->faker->sentence(),
        ]);
    }
}
