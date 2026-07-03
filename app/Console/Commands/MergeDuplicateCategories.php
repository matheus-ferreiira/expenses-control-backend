<?php

namespace App\Console\Commands;

use App\Domains\Finance\Models\TransactionCategory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MergeDuplicateCategories extends Command
{
    protected $signature = 'finance:merge-duplicate-categories {--dry-run : Só lista o que seria feito, sem alterar nada}';

    protected $description = 'Funde categorias duplicadas (mesmo usuário, nome e tipo): mantém a mais usada, reaponta transações e itens de orçamento, remove as demais';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $duplicates = TransactionCategory::query()
            ->select('user_id', 'name', 'type', DB::raw('COUNT(*) as qty'))
            ->groupBy('user_id', 'name', 'type')
            ->having('qty', '>', 1)
            ->get();

        if ($duplicates->isEmpty()) {
            $this->info('Nenhuma categoria duplicada encontrada.');

            return self::SUCCESS;
        }

        $merged = 0;

        foreach ($duplicates as $dup) {
            $group = TransactionCategory::query()
                ->where('user_id', $dup->user_id)
                ->where('name', $dup->name)
                ->where('type', $dup->type)
                ->withCount('transactions')
                ->orderByDesc('transactions_count')
                ->orderBy('created_at')
                ->get();

            /** @var TransactionCategory $keeper */
            $keeper = $group->first();
            $losers = $group->slice(1);

            $this->line(sprintf(
                '%s [%s] — mantém %s (%d usos), remove %d duplicata(s)',
                $dup->name,
                $dup->type instanceof \BackedEnum ? $dup->type->value : (string) $dup->type,
                $keeper->id,
                $keeper->transactions_count,
                $losers->count(),
            ));

            if ($dryRun) {
                continue;
            }

            DB::transaction(function () use ($keeper, $losers, &$merged) {
                foreach ($losers as $loser) {
                    DB::table('transactions')
                        ->where('category_id', $loser->id)
                        ->update(['category_id' => $keeper->id]);

                    DB::table('budget_items')
                        ->where('category_id', $loser->id)
                        ->update(['category_id' => $keeper->id]);

                    // Preserve a monthly limit if only the duplicate had one
                    if ($keeper->monthly_limit === null && $loser->monthly_limit !== null) {
                        $keeper->update(['monthly_limit' => $loser->monthly_limit]);
                    }

                    $loser->delete();
                    $merged++;
                }
            });
        }

        $this->info($dryRun
            ? 'Dry-run: nada foi alterado.'
            : "Concluído: {$merged} duplicata(s) fundida(s).");

        return self::SUCCESS;
    }
}
