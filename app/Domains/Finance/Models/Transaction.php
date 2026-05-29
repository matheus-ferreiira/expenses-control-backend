<?php

namespace App\Domains\Finance\Models;

use App\Domains\Finance\Enums\TransactionStatus;
use App\Domains\Finance\Enums\TransactionType;
use App\Models\User;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'account_id',
        'destination_account_id',
        'card_id',
        'category_id',
        'type',
        'amount',
        'description',
        'notes',
        'transaction_date',
        'is_recurring',
        'recurrence_config',
        'status',
        'recurrence_group_id',
        'installment_number',
        'total_installments',
        'installment_group_id',
    ];

    protected $casts = [
        'type' => TransactionType::class,
        'status' => TransactionStatus::class,
        'amount' => 'decimal:2',
        'transaction_date' => 'date',
        'is_recurring' => 'boolean',
        'recurrence_config' => 'array',
        'installment_number' => 'integer',
        'total_installments' => 'integer',
    ];

    protected static function newFactory(): TransactionFactory
    {
        return TransactionFactory::new();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'account_id');
    }

    /** Destination account for transfer-type transactions (the account being credited). */
    public function destinationAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'destination_account_id');
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(CreditCard::class, 'card_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TransactionCategory::class, 'category_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(TransactionTag::class, 'transaction_transaction_tag', 'transaction_id', 'transaction_tag_id');
    }

    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeIncome(Builder $query): Builder
    {
        return $query->where('type', TransactionType::Income->value);
    }

    public function scopeExpenses(Builder $query): Builder
    {
        return $query->where('type', TransactionType::Expense->value);
    }

    public function scopeInPeriod(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->whereBetween('transaction_date', [$startDate, $endDate]);
    }

    public function scopeInMonth(Builder $query, int $year, int $month): Builder
    {
        return $query->whereYear('transaction_date', $year)
            ->whereMonth('transaction_date', $month);
    }

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', TransactionStatus::Confirmed->value);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', TransactionStatus::Pending->value);
    }

    public function isConfirmed(): bool
    {
        return $this->status === TransactionStatus::Confirmed;
    }

    protected function status(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                // Se não tem status no banco, calcula baseado na data
                if (is_null($value)) {
                    $today = now()->startOfDay();
                    $txDate = $this->transaction_date?->startOfDay();

                    return $txDate && $txDate <= $today ? TransactionStatus::Confirmed : TransactionStatus::Pending;
                }

                return TransactionStatus::from($value);
            },
        );
    }
}
