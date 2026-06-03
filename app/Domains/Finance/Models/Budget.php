<?php

namespace App\Domains\Finance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Budget extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'month',
        'year',
        'base_amount',
        'is_template',
    ];

    protected $casts = [
        'month' => 'integer',
        'year' => 'integer',
        'base_amount' => 'decimal:2',
        'is_template' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BudgetItem::class);
    }

    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }
}
