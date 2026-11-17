<?php

namespace App\Console\Commands;

use App\Domains\BankSync\Models\BankConnection;
use App\Domains\BankSync\Services\BankSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class PullBankConnections extends Command
{
    protected $signature = 'bank-sync:pull';

    protected $description = 'Lê de novo na Pluggy as contas e transações de cada banco conectado (MeuPluggy atualiza 1x por dia)';

    public function handle(BankSyncService $sync): int
    {
        $ok = 0;
        $failed = 0;
        foreach (BankConnection::query()->lazyById() as $connection) {
            try {
                $sync->sync($connection);
                $ok++;
            } catch (HttpExceptionInterface $e) {
                // One bank failing does not stop the others; the error stays on the connection
                $failed++;
                Log::warning('bank-sync:pull failed', ['connection' => $connection->id, 'message' => $e->getMessage()]);
            }
        }
        $this->info("Conexões lidas: {$ok}; com erro: {$failed}");

        return self::SUCCESS;
    }
}
