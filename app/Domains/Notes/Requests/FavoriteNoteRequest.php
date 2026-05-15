<?php

namespace App\Domains\Notes\Requests;

use App\Http\Requests\BaseFormRequest;

class FavoriteNoteRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'favorited' => ['required', 'boolean'],
        ];
    }
}
