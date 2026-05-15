<?php

namespace App\Domains\Finance\Models;

use App\Domains\Finance\Enums\TransactionType;
use App\Models\User;
use Database\Factories\TransactionCategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransactionCategory extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = ['user_id', 'name', 'type', 'icon', 'color', 'is_default'];

    protected $casts = [
        'type' => TransactionType::class,
        'is_default' => 'boolean',
    ];

    protected static function newFactory(): TransactionCategoryFactory
    {
        return TransactionCategoryFactory::new();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'category_id');
    }

    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('user_id', $userId)->orWhereNull('user_id');
        });
    }
}
