<?php

namespace App\Domains\Tasks\Enums;

enum RecurrenceType: string
{
    case None = 'none';
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Yearly = 'yearly';
    case Weekdays = 'weekdays';
    case Custom = 'custom';
}
