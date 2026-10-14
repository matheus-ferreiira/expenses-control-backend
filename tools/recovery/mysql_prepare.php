<?php
require __DIR__.'/mysql_bootstrap.php';
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4', 'root', getenv('DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$database = getenv('DB_DATABASE');
$pdo->exec('CREATE DATABASE IF NOT EXISTS `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
echo json_encode(['server' => $pdo->query('SELECT VERSION()')->fetchColumn(), 'host' => '127.0.0.1', 'database' => $database]), PHP_EOL;
if (Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]) !== 0) { throw new RuntimeException(Illuminate\Support\Facades\Artisan::output()); }
echo Illuminate\Support\Facades\Artisan::output();
