<?php

namespace App\Domains\Habits\Models;

use App\Domains\Habits\Enums\FrequencyType;
use App\Models\User;
use Database\Factories\HabitFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Habit extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'category',
        'description',
        'frequency_type',
        'target_frequency',
        'target_days',
        'color',
        'icon',
        'start_date',
        'archived_at',
    ];

    protected $casts = [
        'frequency_type' => FrequencyType::class,
        'target_frequency' => 'integer',
        'target_days' => 'array',
        'start_date' => 'date',
        'archived_at' => 'datetime',
    ];

    protected static function newFactory(): HabitFactory
    {
        return HabitFactory::new();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(HabitLog::class)->orderByDesc('completed_date');
    }

    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->whereNotNull('archived_at');
    }

    public function getIsArchivedAttribute(): bool
    {
        return $this->archived_at !== null;
    }
}
