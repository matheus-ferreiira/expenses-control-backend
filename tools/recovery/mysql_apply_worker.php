<?php
require __DIR__.'/mysql_bootstrap.php';
$workspace = dirname(__DIR__, 3);
$directory = $workspace.'/fase3-recuperacao-2026-10-13';
$plan = app(App\Domains\Finance\Services\RecoveryPlan::class)->load($directory.'/plano-laravel.json', trim(file_get_contents($directory.'/plano-laravel.sha256')), '019e524b-a79a-7004-a9bc-70ecda553385', $workspace);
echo json_encode(app(App\Domains\Finance\Services\RecoveryImporter::class)->apply($plan, false), JSON_THROW_ON_ERROR);
