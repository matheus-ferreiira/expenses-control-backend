<?php

namespace App\Domains\Finance\Services;

use App\Models\User;
use Carbon\Carbon;

/**
 * "Today" as the user lives it. The server runs in UTC, so between 21h and midnight in Brasília
 * Carbon::today() is already tomorrow (D-30): status, due dates and checks use this instead.
 */
final class UserClock
{
    public const DEFAULT_TIMEZONE = 'America/Sao_Paulo';

    public static function timezone(User|string|null $user): string
    {
        $user = is_string($user) ? User::find($user, ['id', 'timezone']) : $user;

        return $user?->timezone ?: self::DEFAULT_TIMEZONE;
    }

    /** Today's date (Y-m-d) in the user's timezone. */
    public static function today(User|string|null $user): string
    {
        return Carbon::now(self::timezone($user))->toDateString();
    }
}
