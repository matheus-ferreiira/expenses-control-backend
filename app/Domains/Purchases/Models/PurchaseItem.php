<?php

namespace App\Domains\Purchases\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'name',
        'category',
        'is_bought',
    ];

    protected $casts = [
        'is_bought' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser($query, int|string $userId)
    {
        return $query->where('user_id', $userId);
    }
}
