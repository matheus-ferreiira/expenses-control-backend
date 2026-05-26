<?php

namespace App\Domains\Auth\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar' => $this->avatar,
            'locale' => $this->locale,
            'timezone' => $this->timezone,
            'settings' => $this->settings ?? [],
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'current_streak' => $this->current_streak ?? 0,
            'last_transaction_date' => $this->last_transaction_date?->toDateString(),
        ];
    }
}
