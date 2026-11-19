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

    protected $description = 'Pergunta à Pluggy se ela leu cada banco de novo (MeuPluggy lê 1x por dia) e, se leu, relê contas e transações';

    public function handle(BankSyncService $sync): int
    {
        $read = 0;
        $unchanged = 0;
        $failed = 0;
        foreach (BankConnection::query()->lazyById() as $connection) {
            try {
                $sync->pullIfUpdated($connection) ? $read++ : $unchanged++;
            } catch (HttpExceptionInterface $e) {
                // One bank failing does not stop the others; the error stays on the connection
                $failed++;
                Log::warning('bank-sync:pull failed', ['connection' => $connection->id, 'message' => $e->getMessage()]);
            }
        }
        $this->info("Conexões relidas: {$read}; sem novidade na Pluggy: {$unchanged}; com erro: {$failed}");

        return self::SUCCESS;
    }
}
