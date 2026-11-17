<?php

namespace App\Domains\Assistant\Models;

use Database\Factories\AssistantConversationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'assistant_conversations';

    protected $fillable = ['user_id', 'title'];

    protected $hidden = ['user_id'];

    protected static function newFactory(): AssistantConversationFactory
    {
        return AssistantConversationFactory::new();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'conversation_id');
    }
}
