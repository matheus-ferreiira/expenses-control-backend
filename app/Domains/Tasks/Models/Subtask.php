<?php

namespace App\Domains\Tasks\Models;

use Database\Factories\SubtaskFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subtask extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['task_id', 'title', 'completed', 'position'];

    protected $casts = [
        'completed' => 'boolean',
        'position' => 'integer',
    ];

    protected static function newFactory(): SubtaskFactory
    {
        return SubtaskFactory::new();
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
