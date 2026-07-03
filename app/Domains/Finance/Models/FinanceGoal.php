<?php

namespace App\Domains\Finance\Models;

use App\Models\User;
use Database\Factories\FinanceGoalFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinanceGoal extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected static function newFactory(): FinanceGoalFactory
    {
        return FinanceGoalFactory::new();
    }

    protected $table = 'finance_goals';

    protected $fillable = [
        'user_id',
        'name',
        'target_amount',
        'monthly_contribution',
        'deadline',
        'color',
        'icon',
        'bank_account_id',
        'status',
    ];

    protected $casts = [
        'target_amount' => 'decimal:2',
        'monthly_contribution' => 'decimal:2',
        'deadline' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'goal_id');
    }

    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
