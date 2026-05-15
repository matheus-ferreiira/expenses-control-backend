<?php

namespace App\Domains\Purchases\Models;

use App\Models\User;
use Database\Factories\PurchaseItemFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseItem extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'category',
        'is_bought',
    ];

    protected $casts = [
        'is_bought' => 'boolean',
    ];

    protected static function newFactory(): PurchaseItemFactory
    {
        return PurchaseItemFactory::new();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser($query, int|string $userId)
    {
        return $query->where('user_id', $userId);
    }
}
