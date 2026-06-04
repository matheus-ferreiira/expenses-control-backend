<?php

namespace Database\Factories;

use App\Domains\Bookmarks\Models\BookmarkCollection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookmarkCollectionFactory extends Factory
{
    protected $model = BookmarkCollection::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->words(2, true),
            'icon' => null,
            'color' => null,
            'position' => 0,
        ];
    }

    public function withIcon(): static
    {
        return $this->state(fn (array $attributes) => [
            'icon' => 'Code',
            'color' => '#00C896',
        ]);
    }
}
