<?php

namespace App\Domains\Goals\Enums;

enum GoalType: string
{
    case Financial = 'financial';
    case Habit = 'habit';
    case Productivity = 'productivity';
    case Health = 'health';
    case Personal = 'personal';
    case Learning = 'learning';

    public function label(): string
    {
        return match ($this) {
            self::Financial => 'Financial',
            self::Habit => 'Habit',
            self::Productivity => 'Productivity',
            self::Health => 'Health',
            self::Personal => 'Personal',
            self::Learning => 'Learning',
        };
    }
}
