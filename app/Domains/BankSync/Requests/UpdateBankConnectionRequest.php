<?php

namespace App\Domains\BankSync\Requests;

use App\Http\Requests\BaseFormRequest;

class UpdateBankConnectionRequest extends BaseFormRequest
{
    public function rules(): array
    {
        // Empty or null goes back to the automatic name
        return ['name' => ['present', 'nullable', 'string', 'max:80']];
    }
}
