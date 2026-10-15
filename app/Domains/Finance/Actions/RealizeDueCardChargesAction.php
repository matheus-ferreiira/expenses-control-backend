<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Finance\Enums\TransactionStatus;
use App\Domains\Finance\Enums\TransactionType;
use App\Domains\Finance\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;

/**
 * Card purchases, installments and subscriptions happen on their own date: nobody "pays" them one by
 * one, the statement does. Once the date arrives (in the user's timezone) they become confirmed, so
 * budget, reports and overview count them without a manual step. Card charges never touch an
 * account balance, so this is only a status change. Missed days are caught up on the next run.
 */
final class RealizeDueCardChargesAction
{
    public function execute(?string $userId = null): int
    {
        $userIds = Transaction::query()
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->whereNotNull('card_id')
            ->where('type', '!=', TransactionType::Transfer->value)
            ->where('status', TransactionStatus::Pending->value)
            ->distinct()
            ->pluck('user_id');

        $realized = 0;
        foreach (User::whereIn('id', $userIds)->get(['id', 'timezone']) as $user) {
            $today = Carbon::now($user->timezone ?: 'America/Sao_Paulo')->toDateString();
            $realized += Transaction::query()
                ->where('user_id', $user->id)
                ->whereNotNull('card_id')
                ->where('type', '!=', TransactionType::Transfer->value)
                ->where('status', TransactionStatus::Pending->value)
                ->where('transaction_date', '<=', $today)
                ->update(['status' => TransactionStatus::Confirmed->value, 'updated_at' => now()]);
        }

        return $realized;
    }
}
