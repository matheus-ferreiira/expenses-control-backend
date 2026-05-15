<?php

namespace Database\Factories;

use App\Domains\Calendar\Enums\EventSource;
use App\Domains\Calendar\Models\CalendarEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CalendarEventFactory extends Factory
{
    protected $model = CalendarEvent::class;

    public function definition(): array
    {
        $start = now()->addDays(rand(1, 30));

        return [
            'user_id' => User::factory(),
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->optional()->sentence(),
            'location' => $this->faker->optional()->address(),
            'start_date' => $start,
            'end_date' => $start->copy()->addHour(),
            'is_all_day' => false,
            'color' => '#6366f1',
            'source' => EventSource::Manual,
            'external_id' => null,
            'recurrence_rule' => null,
            'metadata' => null,
        ];
    }

    public function allDay(): static
    {
        return $this->state(['is_all_day' => true]);
    }
}
