<?php

namespace App\Console\Commands;

use App\Domains\Finance\Actions\RealizeDueCardChargesAction;
use Illuminate\Console\Command;

class RealizeDueCardCharges extends Command
{
    protected $signature = 'finance:realize-card-charges';

    protected $description = 'Confirma compras, parcelas e assinaturas do cartão cuja data já chegou (recupera dias perdidos)';

    public function handle(RealizeDueCardChargesAction $action): int
    {
        $this->info('Cobranças de cartão realizadas: '.$action->execute());

        return self::SUCCESS;
    }
}
