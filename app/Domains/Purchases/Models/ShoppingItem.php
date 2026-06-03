<?php

namespace App\Domains\Purchases\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShoppingItem extends Model
{
    use HasUuids, SoftDeletes;

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
