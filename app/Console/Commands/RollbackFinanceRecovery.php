<?php

namespace App\Console\Commands;

use App\Domains\Finance\Services\RecoveryImporter;
use Illuminate\Console\Command;

class RollbackFinanceRecovery extends Command
{
    protected $signature = 'finance:rollback-recovery {batch} {--user=} {--apply}';

    protected $description = 'Check or selectively revert an isolated recovery batch';

    public function handle(RecoveryImporter $importer): int
    {
        $this->line(json_encode($importer->rollback((string) $this->option('user'), $this->argument('batch'), ! $this->option('apply')), JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
