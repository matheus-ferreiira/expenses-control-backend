<?php

namespace App\Domains\Prices\Models;

use App\Domains\Prices\Enums\ProductStatus;
use App\Models\User;
use Database\Factories\PriceProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class PriceProduct extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'category_id',
        'name',
        'brand',
        'model',
        'specs',
        'notes',
        'target_price',
        'launch_price',
        'status',
    ];

    protected $casts = [
        'status' => ProductStatus::class,
        'target_price' => 'decimal:2',
        'launch_price' => 'decimal:2',
    ];

    protected static function newFactory(): PriceProductFactory
    {
        return PriceProductFactory::new();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PriceCategory::class, 'category_id');
    }

    public function priceRecords(): HasMany
    {
        return $this->hasMany(PriceRecord::class, 'product_id');
    }

    public function latestPriceRecord(): HasOne
    {
        return $this->hasOne(PriceRecord::class, 'product_id')->latestOfMany('recorded_at');
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(PricePurchase::class, 'product_id');
    }

    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeTracking(Builder $query): Builder
    {
        return $query->where('status', ProductStatus::Tracking);
    }
}
