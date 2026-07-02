<?php

namespace App\Domains\Prices\Policies;

use App\Domains\Prices\Models\PriceRecord;
use App\Models\User;

class PriceRecordPolicy
{
    public function view(User $user, PriceRecord $record): bool
    {
        return $user->id === $record->user_id;
    }

    public function update(User $user, PriceRecord $record): bool
    {
        return $user->id === $record->user_id;
    }

    public function delete(User $user, PriceRecord $record): bool
    {
        return $user->id === $record->user_id;
    }
}
