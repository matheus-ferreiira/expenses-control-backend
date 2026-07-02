<?php

namespace App\Domains\Prices\Enums;

enum GoalStatus: string
{
    case Excellent = 'excellent';
    case Near = 'near';
    case Above = 'above';

    /**
     * Derive the goal status of a price against a target price.
     * Single source of truth for the goal indicator logic.
     */
    public static function fromPrice(?float $target, float $price): ?self
    {
        if ($target === null || $target <= 0) {
            return null;
        }

        if ($price <= $target) {
            return self::Excellent;
        }

        if ($price <= round($target * 1.10, 2)) {
            return self::Near;
        }

        return self::Above;
    }

    public function label(): string
    {
        return match ($this) {
            self::Excellent => 'Na meta',
            self::Near => 'Perto da meta',
            self::Above => 'Acima da meta',
        };
    }
}
