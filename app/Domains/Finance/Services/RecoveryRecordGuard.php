<?php

namespace App\Domains\Finance\Services;

use App\Domains\Finance\Models\Transaction;
use Illuminate\Validation\ValidationException;

final class RecoveryRecordGuard
{
    public static function editable(Transaction $transaction, string $scope = 'this_only'): void
    {
        $related = Transaction::forUser($transaction->user_id)->where(function ($query) use ($transaction, $scope) {
            $query->whereKey($transaction->id);
            if ($scope !== 'this_only' && $transaction->installment_group_id) {
                $query->orWhere(function ($group) use ($transaction, $scope) {
                    $group->where('installment_group_id', $transaction->installment_group_id);
                    if ($scope === 'this_and_future') {
                        $group->where('installment_number', '>=', $transaction->installment_number);
                    }
                });
            } elseif ($scope !== 'this_only' && $transaction->recurrence_group_id) {
                // Bulk edits of a series only touch unpaid occurrences, so imported history never blocks them.
                $query->orWhere(function ($group) use ($transaction, $scope) {
                    $group->where('recurrence_group_id', $transaction->recurrence_group_id)->where('status', 'pending');
                    if ($scope === 'this_and_future') {
                        $group->where('transaction_date', '>=', $transaction->transaction_date->toDateString());
                    }
                });
            }
        })->whereNotNull('import_batch_id')->exists();
        if ($related) {
            throw ValidationException::withMessages(['transaction' => ['Registro importado do extrato: valor, data, conta e descrição ficam travados para o saldo continuar batendo com o banco. Categoria, tags e notas podem ser alteradas.']]);
        }
    }
}
