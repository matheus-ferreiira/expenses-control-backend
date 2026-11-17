<?php

namespace App\Domains\BankSync\Models;

use App\Domains\Finance\Models\BankAccount;
use App\Domains\Finance\Models\CreditCard;
use Database\Factories\BankConnectionAccountFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An account or credit card as the bank reports it through Pluggy (type BANK or CREDIT). Once linked to a
 * ledger account (BANK) or card (CREDIT), its transactions from `import_from` on are reconciled with the ledger.
 */
class BankConnectionAccount extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'bank_connection_id', 'user_id', 'external_account_id', 'type', 'subtype', 'name', 'number', 'bank_code',
        'currency_code', 'balance_cents', 'credit_limit_cents', 'available_credit_limit_cents',
        'balance_close_date', 'balance_due_date', 'transactions_synced_at',
        'bank_account_id', 'credit_card_id', 'import_from',
    ];

    protected $hidden = ['user_id'];

    protected $casts = [
        'balance_cents' => 'integer',
        'credit_limit_cents' => 'integer',
        'available_credit_limit_cents' => 'integer',
        'balance_close_date' => 'date:Y-m-d',
        'balance_due_date' => 'date:Y-m-d',
        'transactions_synced_at' => 'datetime',
        'import_from' => 'date:Y-m-d',
    ];

    protected static function newFactory(): BankConnectionAccountFactory
    {
        return BankConnectionAccountFactory::new();
    }

    public function bankConnection(): BelongsTo
    {
        return $this->belongsTo(BankConnection::class, 'bank_connection_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(BankConnectionTransaction::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function creditCard(): BelongsTo
    {
        return $this->belongsTo(CreditCard::class);
    }

    public function isCredit(): bool
    {
        return $this->type === 'CREDIT';
    }

    public function isLinked(): bool
    {
        return $this->isCredit() ? $this->credit_card_id !== null : $this->bank_account_id !== null;
    }
}
