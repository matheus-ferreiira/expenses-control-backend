<?php

namespace App\Domains\Goals\Enums;

enum GoalStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Paused = 'paused';
    case Cancelled = 'cancelled';
}
