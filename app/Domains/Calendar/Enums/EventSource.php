<?php

namespace App\Domains\Calendar\Enums;

enum EventSource: string
{
    case Manual = 'manual';
    case Google = 'google';
    case Import = 'import';
}
