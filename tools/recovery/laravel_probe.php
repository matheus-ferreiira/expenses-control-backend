<?php
/** Isolated financial-schema restore and behavior checks; never loads .env. */
declare(strict_types=1);

if ($argc !== 4 || PDO::getAvailableDrivers() !== ['sqlite']) {
    throw new RuntimeException('Run with php -n and only pdo_sqlite enabled; arguments: snapshot, NEW sqlite path, result JSON');
}
$root = dirname(__DIR__, 2);
$snapshot = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);
$sqlite = $argv[2];
if (file_exists($sqlite) || ! str_ends_with($sqlite, '.sqlite') || str_starts_with($sqlite, '\\\\')) {
    throw new RuntimeException('Refusing existing or nonlocal target');
}
foreach ([
    'APP_ENV' => 'testing', 'APP_KEY' => 'base64:'.base64_encode(str_repeat('x', 32)),
    'APP_CONFIG_CACHE' => $sqlite.'.unused-config.php', 'APP_SERVICES_CACHE' => $sqlite.'.services.php',
    'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $sqlite, 'DB_URL' => '',
    'DB_HOST' => '127.0.0.1', 'DB_USERNAME' => '', 'DB_PASSWORD' => '',
    'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'MAIL_MAILER' => 'array',
    'QUEUE_CONNECTION' => 'sync', 'LOG_CHANNEL' => 'null', 'BCRYPT_ROUNDS' => '4',
] as $key => $value) {
    putenv($key.'='.$value); $_ENV[$key] = $value; $_SERVER[$key] = $value;
}
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->addAbsoluteCachePathPrefix(dirname($sqlite));
$app->loadEnvironmentFrom('.env.recovery-does-not-exist');
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.default' => 'sqlite', 'database.connections' => [
    'sqlite' => ['driver' => 'sqlite', 'database' => $sqlite, 'prefix' => '', 'foreign_key_constraints' => true],
]]);
touch($sqlite);
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use App\Models\User;
use App\Domains\Finance\Models\BankAccount;
use App\Domains\Finance\Models\CreditCard;
use App\Domains\Finance\Models\Transaction;
use App\Domains\Finance\Actions\CreateTransactionAction;
use App\Domains\Finance\Actions\PayCardStatementAction;
use App\Domains\Finance\DTOs\TransactionDTO;
use App\Domains\Finance\Enums\TransactionType;
use App\Domains\Finance\Services\FinanceReportService;
use App\Domains\Reports\Services\DashboardService;
Mail::fake(); Notification::fake(); Queue::fake();
Carbon\Carbon::setTestNow('2026-10-13 15:00:00');
$migrations = [];
foreach (glob($root.'/database/migrations/*.php') as $path) {
    $name = basename($path);
    if ($name === '0001_01_01_000000_create_users_table.php'
        || preg_match('/(?:transaction|bank_accounts|credit_cards|budgets|budget_items|finance_goals|streak_to_users)/', $name)
        || $name === '2026_06_03_000004_add_goal_id_to_transactions_table.php') {
        (require $path)->up(); $migrations[] = $name;
    }
}
$uid = $snapshot['user_id'];
DB::table('users')->insert(['id' => $uid, 'name' => 'Local recovery fixture', 'email' => 'recovery@example.invalid']);
$tables = ['bank_accounts', 'credit_cards', 'transaction_categories', 'transaction_tags', 'budgets', 'finance_goals', 'transactions', 'budget_items', 'transaction_transaction_tag'];
$counts = [];
DB::transaction(function () use ($tables, $snapshot, &$counts) {
    foreach ($tables as $table) {
        foreach ($snapshot[$table] as $row) { DB::table($table)->insert($row); }
        $counts[$table] = DB::table($table)->count();
        if ($counts[$table] !== count($snapshot[$table])) { throw new RuntimeException('Restore count mismatch'); }
    }
});
if (DB::select('PRAGMA foreign_key_check')) { throw new RuntimeException('Foreign key violation'); }
$checks = ['scoped_financial_restore' => true, 'foreign_keys' => true, 'pdo_drivers' => PDO::getAvailableDrivers()];
$user = User::findOrFail($uid);
$summary = app(FinanceReportService::class)->getMonthlySummary($user, 2026, 10);
$reflection = new ReflectionMethod(DashboardService::class, 'getFinanceSummary');
$dashboard = $reflection->invoke(app(DashboardService::class), $user, now(), now()->startOfMonth(), now()->endOfMonth());
$checks['existing_october_report_confirmed'] = ['income' => $summary['income'], 'expenses' => $summary['expenses']];
$checks['existing_october_dashboard'] = $dashboard;
$checks['dashboard_inconsistency_reproduced'] = $summary['income'] !== $dashboard['month_income'] || $summary['expenses'] !== $dashboard['month_expenses'];
DB::beginTransaction();
try {
    $testUser = User::create(['name' => 'Synthetic test', 'email' => 'synthetic@example.invalid']);
    $account = BankAccount::create(['user_id' => $testUser->id, 'name' => 'Test', 'balance' => 1000, 'currency' => 'BRL']);
    $destination = BankAccount::create(['user_id' => $testUser->id, 'name' => 'Destination', 'balance' => 0]);
    $card = CreditCard::create(['user_id' => $testUser->id, 'bank_account_id' => $account->id, 'name' => 'Test card', 'limit_amount' => 3200, 'closing_day' => 13, 'due_day' => 20]);
    $create = app(CreateTransactionAction::class);
    $create->execute($testUser, new TransactionDTO(type: TransactionType::Expense, amount: 83, description: 'Unknown category', transactionDate: '2026-10-13', accountId: $account->id));
    $checks['null_category_accepted_and_cash_correct'] = (float) $account->fresh()->balance === 917.0;
    $create->execute($testUser, new TransactionDTO(type: TransactionType::Expense, amount: 183.25, description: 'Card purchase', transactionDate: '2026-10-13', cardId: $card->id));
    $checks['card_purchase_no_bank_debit'] = (float) $account->fresh()->balance === 917.0;
    $create->execute($testUser, new TransactionDTO(type: TransactionType::Transfer, amount: 100, description: 'Internal', transactionDate: '2026-10-13', accountId: $account->id, destinationAccountId: $destination->id));
    $checks['transfer_two_balanced_legs'] = (float) $account->fresh()->balance === 817.0 && (float) $destination->fresh()->balance === 100.0;
    app(PayCardStatementAction::class)->execute($testUser, $card, $account->id, 50, '2026-10', '2026-10-13');
    $checks['partial_second_payment_currently_rejected'] = false;
    try { app(PayCardStatementAction::class)->execute($testUser, $card, $account->id, 20, '2026-10', '2026-10-13'); }
    catch (Illuminate\Validation\ValidationException $e) { $checks['partial_second_payment_currently_rejected'] = true; }
    $monthly = app(FinanceReportService::class)->getMonthlySummary($testUser, 2026, 10);
    $checks['payment_not_second_expense'] = $monthly['expenses'] === 266.25 && $monthly['income'] === 0.0;
    $checks['payment_debits_once'] = (float) $account->fresh()->balance === 767.0;
    $pending = $create->execute($testUser, new TransactionDTO(type: TransactionType::Expense, amount: 700, description: 'Future', transactionDate: '2026-11-01', accountId: $account->id));
    $checks['future_does_not_debit_cash'] = (float) $account->fresh()->balance === 767.0 && $pending->status->value === 'pending';
} finally { DB::rollBack(); }
foreach ($checks as $key => $value) {
    if (is_bool($value) && ! $value) { throw new RuntimeException('Failed behavioral check: '.$key); }
}
Mail::assertNothingSent(); Notification::assertNothingSent(); Queue::assertNothingPushed();
$checks['external_side_effects'] = 0;
$checks['restored_financial_records'] = $counts;
$checks['financial_migrations_only'] = $migrations;
$checks['limitations'] = 'SQLite financial subset with synthetic user, not a complete MySQL/application restore. Current application behavior tested; staging recovery is separate.';
file_put_contents($argv[3], json_encode($checks, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo json_encode(['checks' => count($checks), 'restored_transactions' => $counts['transactions'], 'passed' => true]), PHP_EOL;
