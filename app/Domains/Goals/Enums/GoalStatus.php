<?php

namespace App\Domains\Goals\Enums;

enum GoalStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Paused = 'paused';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Completed => 'Completed',
            self::Paused => 'Paused',
            self::Cancelled => 'Cancelled',
        };
    }
}
