<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Deploy-time migrations only run when the release approves them (D-39): every deploy used to run
 * `migrate --force`, so any new migration reached production with no window and no backup.
 * Approve a migration by listing its name in database/release-approved-migrations.txt, in the same
 * commit, after taking a production backup.
 */
class ReleaseMigrate extends Command
{
    protected $signature = 'app:release-migrate';

    protected $description = 'Roda no deploy apenas as migrations pendentes aprovadas em database/release-approved-migrations.txt';

    public function handle(): int
    {
        $migrator = app('migrator');
        if (! $migrator->repositoryExists()) {
            // Brand-new database: nothing to protect yet.
            return $this->call('migrate', ['--force' => true]);
        }
        $files = array_keys($migrator->getMigrationFiles(database_path('migrations')));
        $pending = array_values(array_diff($files, $migrator->getRepository()->getRan()));
        if ($pending === []) {
            $this->info('Nenhuma migration pendente.');

            return self::SUCCESS;
        }

        $approvedFile = database_path('release-approved-migrations.txt');
        $approved = is_file($approvedFile)
            ? array_filter(array_map('trim', file($approvedFile)), fn ($line) => $line !== '' && ! str_starts_with($line, '#'))
            : [];
        $unapproved = array_values(array_diff($pending, $approved));
        if ($unapproved !== []) {
            // The server still starts; the migration waits for an explicit, backed-up release.
            $this->error('Migrations pendentes sem aprovação (faça o backup e liste-as em database/release-approved-migrations.txt): '.implode(', ', $unapproved));

            return self::SUCCESS;
        }

        return $this->call('migrate', ['--force' => true]);
    }
}
