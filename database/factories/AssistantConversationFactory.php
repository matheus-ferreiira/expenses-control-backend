<?php

namespace Database\Factories;

use App\Domains\Assistant\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssistantConversationFactory extends Factory
{
    protected $model = Conversation::class;

    public function definition(): array
    {
        return ['user_id' => User::factory(), 'title' => 'Minha rotina'];
    }
}
