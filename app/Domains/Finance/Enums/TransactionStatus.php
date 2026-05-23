<?php

namespace App\Domains\Finance\Enums;

enum TransactionStatus: string
{
    case Confirmed = 'confirmed';
    case Pending = 'pending';

    public function label(): string
    {
        return match ($this) {
            self::Confirmed => 'Confirmada',
            self::Pending => 'Agendada',
        };
    }
}
