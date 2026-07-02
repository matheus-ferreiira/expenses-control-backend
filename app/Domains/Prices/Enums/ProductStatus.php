<?php

namespace App\Domains\Prices\Enums;

enum ProductStatus: string
{
    case Tracking = 'tracking';
    case Purchased = 'purchased';
    case Discarded = 'discarded';

    public function label(): string
    {
        return match ($this) {
            self::Tracking => 'Acompanhando',
            self::Purchased => 'Comprado',
            self::Discarded => 'Descartado',
        };
    }
}
