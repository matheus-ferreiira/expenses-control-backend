<?php

namespace Database\Factories;

use App\Domains\Notes\Models\NoteTag;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NoteTagFactory extends Factory
{
    protected $model = NoteTag::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->word(),
            'color' => '#'.$this->faker->hexColor(),
        ];
    }
}
