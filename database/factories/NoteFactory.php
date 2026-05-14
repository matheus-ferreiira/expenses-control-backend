<?php

namespace Database\Factories;

use App\Domains\Notes\Models\Note;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NoteFactory extends Factory
{
    protected $model = Note::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => $this->faker->sentence(4),
            'content' => $this->faker->paragraph(),
            'is_pinned' => false,
            'is_favorite' => false,
            'archived_at' => null,
            'last_viewed_at' => null,
        ];
    }

    public function archived(): static
    {
        return $this->state(['archived_at' => now()]);
    }

    public function pinned(): static
    {
        return $this->state(['is_pinned' => true]);
    }
}
