<?php

namespace App\Domains\Prices\Models;

use App\Models\User;
use Database\Factories\PriceStoreFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PriceStore extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'website_url',
        'notes',
    ];

    protected static function newFactory(): PriceStoreFactory
    {
        return PriceStoreFactory::new();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function priceRecords(): HasMany
    {
        return $this->hasMany(PriceRecord::class, 'store_id');
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(PricePurchase::class, 'store_id');
    }

    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }
}
