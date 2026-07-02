<?php

namespace App\Domains\Prices\Models;

use App\Models\User;
use Database\Factories\PricePurchaseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class PricePurchase extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'product_id',
        'store_id',
        'price_paid',
        'purchased_at',
        'warranty_months',
        'current_value',
        'notes',
    ];

    protected $casts = [
        'price_paid' => 'decimal:2',
        'current_value' => 'decimal:2',
        'purchased_at' => 'date',
        'warranty_months' => 'integer',
    ];

    protected static function newFactory(): PricePurchaseFactory
    {
        return PricePurchaseFactory::new();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(PriceProduct::class, 'product_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(PriceStore::class, 'store_id');
    }

    public function sale(): HasOne
    {
        return $this->hasOne(PriceSale::class, 'purchase_id');
    }

    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }
}
