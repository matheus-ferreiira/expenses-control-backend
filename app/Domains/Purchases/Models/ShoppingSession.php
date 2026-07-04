<?php

namespace App\Domains\Purchases\Models;

use App\Models\User;
use Database\Factories\ShoppingSessionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShoppingSession extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected static function newFactory(): ShoppingSessionFactory
    {
        return ShoppingSessionFactory::new();
    }

    protected static function booted(): void
    {
        // O cascadeOnDelete do banco só age em hard delete — sem este hook,
        // soft-deletar a sessão deixa os itens órfãos (aconteceu em prod).
        static::deleting(function (ShoppingSession $session) {
            if (! $session->isForceDeleting()) {
                $session->items()->delete();
            }
        });
    }

    protected $fillable = [
        'user_id',
        'title',
        'status',
        'total',
        'finished_at',
        'transaction_id',
    ];

    protected $attributes = [
        'status' => 'active',
    ];

    protected $casts = [
        'total' => 'float',
        'finished_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ShoppingItem::class);
    }

    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeFinished($query)
    {
        return $query->where('status', 'finished');
    }
}
