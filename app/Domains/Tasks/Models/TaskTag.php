<?php

namespace App\Domains\Tasks\Models;

use App\Models\User;
use Database\Factories\TaskTagFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaskTag extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = ['user_id', 'name', 'color'];

    protected static function newFactory(): TaskTagFactory
    {
        return TaskTagFactory::new();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_tag_task', 'tag_id', 'task_id');
    }

    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }
}
