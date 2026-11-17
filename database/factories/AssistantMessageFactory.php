<?php

namespace Database\Factories;

use App\Domains\Assistant\Models\Conversation;
use App\Domains\Assistant\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssistantMessageFactory extends Factory
{
    protected $model = Message::class;

    public function definition(): array
    {
        return ['conversation_id' => Conversation::factory(), 'role' => 'assistant', 'content' => 'Vamos organizar seu dia.'];
    }
}
