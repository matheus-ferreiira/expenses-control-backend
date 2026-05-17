<?php

namespace Database\Factories;

use App\Domains\Habits\Enums\FrequencyType;
use App\Domains\Habits\Models\Habit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class HabitFactory extends Factory
{
    protected $model = Habit::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->words(3, true),
            'category' => $this->faker->optional()->randomElement(['Saúde', 'Mente', 'Aprendizado', 'Foco', 'Finanças']),
            'description' => $this->faker->optional()->sentence(),
            'frequency_type' => FrequencyType::Daily,
            'target_frequency' => 1,
            'color' => '#6366f1',
            'icon' => 'star',
            'start_date' => today(),
            'archived_at' => null,
        ];
    }

    public function archived(): static
    {
        return $this->state(['archived_at' => now()]);
    }
}
