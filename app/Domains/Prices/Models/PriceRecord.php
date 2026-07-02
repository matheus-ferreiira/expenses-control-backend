<?php

namespace App\Domains\Prices\Models;

use App\Models\User;
use Database\Factories\PriceRecordFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PriceRecord extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'product_id',
        'store_id',
        'price',
        'recorded_at',
        'url',
        'notes',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'recorded_at' => 'date',
    ];

    protected static function newFactory(): PriceRecordFactory
    {
        return PriceRecordFactory::new();
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

    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }
}
