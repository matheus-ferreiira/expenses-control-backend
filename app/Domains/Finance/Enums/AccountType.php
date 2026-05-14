<?php

namespace App\Domains\Finance\Enums;

enum AccountType: string
{
    case Checking = 'checking';
    case Savings = 'savings';
    case Investment = 'investment';
    case Cash = 'cash';
    case Wallet = 'wallet';

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
