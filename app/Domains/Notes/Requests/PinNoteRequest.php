<?php

namespace App\Domains\Notes\Requests;

use App\Http\Requests\BaseFormRequest;

class PinNoteRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'pinned' => ['required', 'boolean'],
        ];
    }
}
