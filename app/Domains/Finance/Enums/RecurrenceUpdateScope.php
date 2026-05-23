<?php

namespace App\Domains\Finance\Enums;

enum RecurrenceUpdateScope: string
{
    case ThisOnly = 'this_only';
    case ThisAndFuture = 'this_and_future';
    case All = 'all';
}
