<?php

namespace App\Domains\BankSync\Requests;

use App\Http\Requests\BaseFormRequest;

class ConnectTokenRequest extends BaseFormRequest
{
    public function rules(): array
    {
        // Set to renew the authorisation of an existing connection (widget in update mode)
        return ['connection_id' => ['nullable', 'uuid']];
    }
}
