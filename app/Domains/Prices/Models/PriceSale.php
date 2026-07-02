<?php

namespace App\Domains\Prices\Models;

use App\Models\User;
use Database\Factories\PriceSaleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PriceSale extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'purchase_id',
        'sale_price',
        'sold_at',
        'notes',
    ];

    protected $casts = [
        'sale_price' => 'decimal:2',
        'sold_at' => 'date',
    ];

    protected static function newFactory(): PriceSaleFactory
    {
        return PriceSaleFactory::new();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(PricePurchase::class, 'purchase_id');
    }

    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }
}
