<?php

namespace App\Domains\Assistant\Models;

use Database\Factories\AssistantMessageFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'assistant_messages';

    protected $fillable = ['conversation_id', 'request_id', 'role', 'content', 'actions', 'status', 'results', 'expires_at'];

    protected $casts = ['actions' => 'array', 'results' => 'array', 'expires_at' => 'datetime'];

    protected static function newFactory(): AssistantMessageFactory
    {
        return AssistantMessageFactory::new();
    }
}
