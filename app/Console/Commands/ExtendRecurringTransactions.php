<?php

namespace App\Console\Commands;

use App\Domains\Finance\Actions\ExtendRecurringTransactionsAction;
use App\Domains\Finance\Models\Transaction;
use Illuminate\Console\Command;

class ExtendRecurringTransactions extends Command
{
    protected $signature = 'finance:extend-recurrences {--prune : Também remove pendentes além da janela (dados legados)}';

    protected $description = 'Mantém as séries fixas dentro da janela rolante: cria as próximas ocorrências e, com --prune, remove pendentes além do horizonte';

    public function handle(ExtendRecurringTransactionsAction $action): int
    {
        $userIds = Transaction::query()
            ->whereNotNull('recurrence_group_id')
            ->distinct()
            ->pluck('user_id');

        $created = 0;
        $pruned = 0;

        foreach ($userIds as $userId) {
            if ($this->option('prune')) {
                $pruned += $action->pruneForUser($userId);
            }
            $created += $action->executeForUser($userId);
        }

        $this->info("Ocorrências criadas: {$created}".($this->option('prune') ? " · removidas além da janela: {$pruned}" : ''));

        return self::SUCCESS;
    }
}
