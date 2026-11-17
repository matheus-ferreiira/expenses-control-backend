<?php

namespace App\Domains\BankSync\Requests;

use App\Http\Requests\BaseFormRequest;

class StoreBankConnectionRequest extends BaseFormRequest
{
    public function rules(): array
    {
        // The Pluggy item id the Connect widget returned on success
        return ['item_id' => ['required', 'uuid']];
    }
}
