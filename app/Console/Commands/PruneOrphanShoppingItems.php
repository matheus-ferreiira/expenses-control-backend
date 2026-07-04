<?php

namespace App\Console\Commands;

use App\Domains\Purchases\Models\ShoppingItem;
use App\Domains\Purchases\Models\ShoppingSession;
use Illuminate\Console\Command;

class PruneOrphanShoppingItems extends Command
{
    protected $signature = 'purchases:prune-orphan-items {--dry-run : Só lista, sem alterar}';

    protected $description = 'Soft-deleta itens de compra cuja sessão foi deletada (órfãos criados antes do cascade de soft-delete)';

    public function handle(): int
    {
        $orphans = ShoppingItem::query()
            ->whereNotIn('shopping_session_id', ShoppingSession::query()->select('id'))
            ->get();

        if ($orphans->isEmpty()) {
            $this->info('Nenhum item órfão encontrado.');

            return self::SUCCESS;
        }

        foreach ($orphans as $item) {
            $this->line("órfão: {$item->name} ({$item->created_at->toDateString()})");
        }

        if ($this->option('dry-run')) {
            $this->info("Dry-run: {$orphans->count()} item(ns) seriam removidos.");

            return self::SUCCESS;
        }

        $orphans->each->delete();
        $this->info("Removidos: {$orphans->count()} item(ns) órfão(s).");

        return self::SUCCESS;
    }
}
