<?php

namespace App\Domains\Purchases\Models;

use Database\Factories\ShoppingItemFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShoppingItem extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected static function newFactory(): ShoppingItemFactory
    {
        return ShoppingItemFactory::new();
    }

    protected $fillable = [
        'shopping_session_id',
        'user_id',
        'name',
        'category',
        'is_bought',
        'price',
    ];

    protected $casts = [
        'is_bought' => 'boolean',
        'price' => 'float',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(ShoppingSession::class, 'shopping_session_id');
    }
}
