<?php

namespace App\Domains\BankSync\Models;

use App\Domains\Finance\Models\BankAccount;
use App\Domains\Finance\Models\TransactionCategory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What the user last chose for a counterpart in one direction (e.g. money out to "SAFE2PAY…" →
 * expense "Lanche" in Alimentação; money in from oneself → transfer from Caixa). Only a suggestion.
 */
class BankReviewRule extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id', 'match_key', 'direction', 'action', 'category_id', 'account_id', 'description', 'is_reimbursement',
        'own_share_ratio', 'uses',
    ];

    protected $casts = [
        'is_reimbursement' => 'boolean',
        'own_share_ratio' => 'float',
        'uses' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(TransactionCategory::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }
}
