<?php

namespace App\Domains\Tasks\Enums;

enum TaskStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Done = 'done';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::Pending => 'Pending',
            self::InProgress => 'In Progress',
            self::Done => 'Done',
            self::Cancelled => 'Cancelled',
        };
    }

    public function isCompleted(): bool
    {
        return $this === self::Done;
    }
}
