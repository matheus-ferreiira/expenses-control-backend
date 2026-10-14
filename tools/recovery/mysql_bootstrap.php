<?php
/** Local-only test entry point. Never reads the application's .env or config cache. */
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$database = getenv('RECOVERY_DATABASE') ?: (defined('PHPUNIT_COMPOSER_INSTALL') ? 'vaultos_recovery_unit' : 'vaultos_recovery_test');
if (! preg_match('/^vaultos_recovery_[a-z0-9_]+$/D', $database)) { throw new RuntimeException('Invalid isolated database'); }
foreach ([
    'APP_ENV' => 'testing', 'APP_KEY' => 'base64:'.base64_encode(str_repeat('r', 32)),
    'APP_CONFIG_CACHE' => 'storage/framework/recovery-unused-config.php',
    'APP_SERVICES_CACHE' => 'storage/framework/recovery-services.php',
    'DB_CONNECTION' => 'mysql', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '3306', 'DB_DATABASE' => $database,
    'DB_USERNAME' => 'root', 'DB_PASSWORD' => getenv('RECOVERY_LOCAL_PASSWORD') ?: 'root', 'DB_URL' => '',
    'DB_SOCKET' => '', 'MYSQL_ATTR_SSL_CA' => '',
    'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'MAIL_MAILER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'LOG_CHANNEL' => 'null', 'BCRYPT_ROUNDS' => '4',
] as $key => $value) { putenv($key.'='.$value); $_ENV[$key] = $_SERVER[$key] = $value; }
require_once $root.'/vendor/autoload.php';
if (defined('PHPUNIT_COMPOSER_INSTALL')) { return; }
$app = require $root.'/bootstrap/app.php';
$app->addAbsoluteCachePathPrefix($root);
$app->loadEnvironmentFrom('.env.recovery-does-not-exist');
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.default' => 'mysql', 'database.connections' => ['mysql' => [
    'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => '3306', 'database' => $database,
    'username' => 'root', 'password' => getenv('DB_PASSWORD'), 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true,
]]]);
Illuminate\Support\Facades\Mail::fake();
Illuminate\Support\Facades\Notification::fake();
Illuminate\Support\Facades\Queue::fake();
set_exception_handler(function (Throwable $error): void {
    fwrite(STDERR, get_class($error).': '.$error->getMessage().PHP_EOL);
    exit(1);
});
return $app;
