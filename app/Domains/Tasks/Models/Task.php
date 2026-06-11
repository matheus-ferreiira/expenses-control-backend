<?php

namespace App\Domains\Tasks\Models;

use App\Domains\Tasks\Enums\RecurrenceType;
use App\Domains\Tasks\Enums\TaskPriority;
use App\Domains\Tasks\Enums\TaskStatus;
use App\Models\User;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'task_list_id',
        'title',
        'description',
        'priority',
        'status',
        'due_date',
        'completed_at',
        'recurrence_type',
        'recurrence_config',
        'next_occurrence_date',
        'position',
        'is_archived',
        'estimated_minutes',
    ];

    protected $casts = [
        'priority' => TaskPriority::class,
        'status' => TaskStatus::class,
        'recurrence_type' => RecurrenceType::class,
        'recurrence_config' => 'array',
        'due_date' => 'datetime',
        'completed_at' => 'datetime',
        'next_occurrence_date' => 'datetime',
        'is_archived' => 'boolean',
        'position' => 'integer',
        'estimated_minutes' => 'integer',
    ];

    protected static function newFactory(): TaskFactory
    {
        return TaskFactory::new();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function taskList(): BelongsTo
    {
        return $this->belongsTo(TaskList::class, 'task_list_id');
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(Subtask::class)->orderBy('position');
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(TaskLabel::class, 'task_label_task', 'task_id', 'task_label_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(TaskTag::class, 'task_tag_task', 'task_id', 'tag_id');
    }

    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', [TaskStatus::Pending->value, TaskStatus::InProgress->value]);
    }

    public function scopeNotArchived(Builder $query): Builder
    {
        return $query->where('is_archived', false);
    }

    public function scopeDueToday(Builder $query): Builder
    {
        return $query->whereDate('due_date', today());
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('due_date', '<', now())->where('status', '!=', TaskStatus::Completed->value);
    }
}
