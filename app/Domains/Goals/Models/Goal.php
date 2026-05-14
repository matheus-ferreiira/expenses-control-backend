<?php

namespace App\Domains\Goals\Models;

use App\Domains\Goals\Enums\GoalStatus;
use App\Domains\Goals\Enums\GoalType;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Goal extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'type',
        'status',
        'title',
        'description',
        'target_amount',
        'current_amount',
        'target_date',
        'completed_at',
    ];

    protected $casts = [
        'type' => GoalType::class,
        'status' => GoalStatus::class,
        'target_amount' => 'decimal:2',
        'current_amount' => 'decimal:2',
        'target_date' => 'date',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', GoalStatus::Active->value);
    }

    public function getProgressPercentageAttribute(): float
    {
        if (! $this->target_amount || $this->target_amount == 0) {
            return 0;
        }

        return min(round(($this->current_amount / $this->target_amount) * 100, 1), 100);
    }
}
