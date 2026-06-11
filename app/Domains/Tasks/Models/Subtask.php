<?php

namespace App\Domains\Tasks\Models;

use Database\Factories\SubtaskFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subtask extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'task_subtasks';

    protected $fillable = ['task_id', 'title', 'is_completed', 'position', 'completed_at'];

    protected $casts = [
        'is_completed' => 'boolean',
        'position' => 'integer',
        'completed_at' => 'datetime',
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
