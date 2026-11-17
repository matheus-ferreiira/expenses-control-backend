<?php

namespace App\Domains\BankSync\Models;

use App\Domains\Finance\Models\Transaction;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A transaction as the bank reports it. `amount_cents` is always positive; `direction` says whether money
 * came in (`in`) or went out (`out`); on a card, `out` is a purchase and `in` a payment or refund.
 * `payload` keeps Pluggy's original record. `review_status` tracks its reconciliation with the ledger.
 */
class BankConnectionTransaction extends Model
{
    use HasUuids;

    /** Account not linked to the ledger yet */
    public const UNLINKED = null;

    /** Before the account's import_from: history, never offered */
    public const BEFORE_START = 'before_start';

    /** Waiting for the user's decision */
    public const NEW = 'new';

    /** Was already in the ledger (entered by hand or imported earlier) */
    public const MATCHED = 'matched';

    /** Created in the ledger from this bank transaction */
    public const IMPORTED = 'imported';

    /** The user chose to leave it out of the ledger */
    public const IGNORED = 'ignored';

    /** Statuses that tie a ledger transaction to the bank */
    public const LINKED = [self::MATCHED, self::IMPORTED];

    protected $fillable = [
        'bank_connection_account_id', 'user_id', 'external_transaction_id', 'date', 'description', 'amount_cents',
        'direction', 'status', 'category', 'installment_number', 'total_installments', 'payload',
        'review_status', 'transaction_id', 'reviewed_at',
    ];

    protected $hidden = ['user_id', 'payload'];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'amount_cents' => 'integer',
        'installment_number' => 'integer',
        'total_installments' => 'integer',
        'payload' => 'array',
        'reviewed_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(BankConnectionAccount::class, 'bank_connection_account_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class)->withTrashed();
    }
}
