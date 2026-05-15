<?php

namespace App\Domains\Finance\Models;

use App\Models\User;
use Database\Factories\CreditCardFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CreditCard extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected static function newFactory(): CreditCardFactory
    {
        return CreditCardFactory::new();
    }

    protected $fillable = [
        'user_id',
        'bank_account_id',
        'name',
        'limit_amount',
        'closing_day',
        'due_day',
        'color',
        'is_active',
    ];

    protected $casts = [
        'limit_amount' => 'decimal:2',
        'closing_day' => 'integer',
        'due_day' => 'integer',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'card_id');
    }

    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }
}
