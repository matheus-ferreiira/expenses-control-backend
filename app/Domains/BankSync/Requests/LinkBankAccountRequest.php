<?php

namespace App\Domains\BankSync\Requests;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class LinkBankAccountRequest extends BaseFormRequest
{
    public function rules(): array
    {
        $user = $this->user()->id;

        return [
            // Bank account (BANK) → ledger account; card (CREDIT) → ledger card. Null unlinks.
            'bank_account_id' => ['nullable', 'uuid', Rule::exists('bank_accounts', 'id')->where('user_id', $user)->whereNull('deleted_at')],
            'credit_card_id' => ['nullable', 'uuid', Rule::exists('credit_cards', 'id')->where('user_id', $user)->whereNull('deleted_at')],
            // First day offered for review; empty = detected (where the app and the bank stop agreeing)
            'import_from' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
