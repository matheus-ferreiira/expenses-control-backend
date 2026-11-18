<?php
declare(strict_types=1);
require __DIR__.'/mysql_bootstrap.php';

use App\Domains\Finance\Services\RecoveryPlan;
use Illuminate\Support\Facades\DB;

$database = getenv('DB_DATABASE');
$restore = $database.'_restored_'.date('His');
$directory = dirname(__DIR__, 3).'/fase3-recuperacao-2026-10-13/backup';
if (! is_dir($directory)) { mkdir($directory, 0700, true); }
$file = $directory.'/'.$database.'-'.date('Ymd-His').'.sql';
$defaults = tempnam(sys_get_temp_dir(), 'vaultos-mysql-');
$errorFile = $file.'.stderr';
file_put_contents($defaults, "[client]\nhost=127.0.0.1\nport=3306\nuser=root\npassword=".getenv('DB_PASSWORD')."\nprotocol=tcp\n");
$bin = 'C:/Program Files/MySQL/MySQL Server 8.3/bin/';
function runMysql(array $command, array $descriptors): void {
    $process = proc_open($command, $descriptors, $pipes, null, null, ['bypass_shell' => true]);
    if (! is_resource($process) || proc_close($process) !== 0) { throw new RuntimeException('MySQL backup/restore command failed; inspect local stderr artifact'); }
}
try {
    runMysql([$bin.'mysqldump.exe', '--defaults-extra-file='.$defaults, '--single-transaction', '--routines', '--events', '--triggers', '--hex-blob', '--no-tablespaces', '--set-gtid-purged=OFF', $database], [0 => ['file', 'NUL', 'r'], 1 => ['file', $file, 'w'], 2 => ['file', $errorFile, 'w']]);
    DB::statement('CREATE DATABASE `'.$restore.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    runMysql([$bin.'mysql.exe', '--defaults-extra-file='.$defaults, $restore], [0 => ['file', $file, 'r'], 1 => ['file', 'NUL', 'w'], 2 => ['file', $errorFile, 'a']]);
    $tables = DB::select('SHOW TABLES');
    $verified = [];
    foreach ($tables as $tableRow) {
        $table = array_values((array) $tableRow)[0];
        $hashRows = function (string $schema) use ($table): array {
            $rows = DB::select('SELECT * FROM `'.$schema.'`.`'.$table.'`');
            $hashes = array_map(fn ($row) => RecoveryPlan::hash((array) $row), $rows);
            sort($hashes);
            return $hashes;
        };
        $before = $hashRows($database);
        if ($before !== $hashRows($restore)) { throw new RuntimeException('Restore mismatch: '.$table); }
        $verified[$table] = count($before);
    }
    $result = ['source_database' => $database, 'restored_database' => $restore, 'sha256' => hash_file('sha256', $file), 'dump' => $file, 'tables_verified' => $verified, 'all_rows_identical' => true, 'scope' => 'Complete isolated application database; financial snapshot plus synthetic user, not a production backup'];
    file_put_contents(dirname($directory).'/resultado-backup-restore.json', json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo json_encode(['tables' => count($verified), 'all_rows_identical' => true, 'restore' => $restore]), PHP_EOL;
} finally { unlink($defaults); }
