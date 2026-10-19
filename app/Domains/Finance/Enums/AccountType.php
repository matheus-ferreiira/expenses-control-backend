<?php

namespace App\Domains\Finance\Enums;

enum AccountType: string
{
    case Checking = 'checking';
    case Savings = 'savings';
    case Investment = 'investment';
    case Cash = 'cash';
    case Wallet = 'wallet';

    /** Money put away (a savings box / investment): kept out of the available and projected balance. */
    public function isReserve(): bool
    {
        return $this === self::Investment;
    }

    public function label(): string
    {
        return match ($this) {
            self::Checking => 'Checking',
            self::Savings => 'Savings',
            self::Investment => 'Investment',
            self::Cash => 'Cash',
            self::Wallet => 'Wallet',
        };
    }
}
