<?php

namespace App\Domains\Tasks\Models;

use App\Models\User;
use Database\Factories\TaskListFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaskList extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = ['user_id', 'name', 'color', 'icon', 'position', 'is_default'];

    protected $casts = [
        'position' => 'integer',
        'is_default' => 'boolean',
    ];

    protected static function newFactory(): TaskListFactory
    {
        return TaskListFactory::new();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'task_list_id');
    }

    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }
}
