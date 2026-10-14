<?php

namespace App\Console\Commands;

use App\Domains\Finance\Services\RecoveryImporter;
use App\Domains\Finance\Services\RecoveryPlan;
use Illuminate\Console\Command;

class RecoverFinance extends Command
{
    protected $signature = 'finance:recover {plan} {--user=} {--sha256=} {--evidence-root=} {--apply}';

    protected $description = 'Validate or apply a reviewed recovery plan; remote targets require explicit configuration approval';

    public function handle(RecoveryPlan $validator, RecoveryImporter $importer): int
    {
        $plan = $validator->load($this->argument('plan'), (string) $this->option('sha256'), (string) $this->option('user'), (string) $this->option('evidence-root'));
        $this->line(json_encode($importer->apply($plan, ! $this->option('apply')), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
