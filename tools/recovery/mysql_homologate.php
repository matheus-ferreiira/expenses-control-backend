<?php
declare(strict_types=1);

require __DIR__.'/mysql_bootstrap.php';
Carbon\Carbon::setTestNow('2026-10-13 15:00:00');

use App\Domains\Finance\Services\RecoveryPlan;
use App\Domains\Finance\Services\RecoveryImporter;
use Illuminate\Support\Facades\DB;

$workspace = dirname(__DIR__, 3);
$out = $workspace.'/fase3-recuperacao-2026-10-13';
$snapshot = json_decode(file_get_contents($workspace.'/auditoria-2026-10-13/fontes/vault-snapshot.json'), true, flags: JSON_THROW_ON_ERROR);
$uid = $snapshot['user_id'];
if (! DB::table('users')->where('id', $uid)->exists()) {
    DB::transaction(function () use ($snapshot, $uid) {
        DB::table('users')->insert(['id' => $uid, 'name' => 'Isolated financial fixture', 'email' => 'recovery@example.invalid']);
        foreach (['bank_accounts', 'credit_cards', 'transaction_categories', 'transaction_tags', 'budgets', 'finance_goals', 'transactions', 'budget_items', 'transaction_transaction_tag'] as $table) {
            foreach ($snapshot[$table] as $row) { DB::table($table)->insert($row); }
        }
    });
}
$plan = app(RecoveryPlan::class)->load($out.'/plano-laravel.json', trim(file_get_contents($out.'/plano-laravel.sha256')), $uid, $workspace);
$importer = app(RecoveryImporter::class);
$before = DB::table('transactions')->where('user_id', $uid)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
$results = ['server' => DB::selectOne('SELECT VERSION() AS version')->version, 'database' => getenv('DB_DATABASE'), 'dry_run' => $importer->apply($plan)];
Illuminate\Support\Facades\Artisan::call('finance:recover', ['plan' => $out.'/plano-laravel.json', '--user' => $uid, '--sha256' => $plan['plan_hash'], '--evidence-root' => $workspace]);
$commandReport = json_decode(Illuminate\Support\Facades\Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
if ($commandReport !== $results['dry_run']) { throw new RuntimeException('CLI/service dry run mismatch'); }
$results['cli_dry_run_verified'] = true;
$workers = [];
for ($i = 0; $i < 2; $i++) {
    $output = $out.'/worker-'.$i.'.json';
    $process = proc_open([PHP_BINARY, __DIR__.'/mysql_apply_worker.php'], [0 => ['file', 'NUL', 'r'], 1 => ['file', $output, 'w'], 2 => ['file', $out.'/worker-'.$i.'.stderr', 'w']], $pipes, null, null, ['bypass_shell' => true]);
    $workers[] = [$process, $output];
}
$concurrent = [];
foreach ($workers as [$process, $output]) {
    if (! is_resource($process) || proc_close($process) !== 0) { throw new RuntimeException('Concurrent worker failed'); }
    $concurrent[] = json_decode(file_get_contents($output), true, flags: JSON_THROW_ON_ERROR);
}
if (array_sum(array_column($concurrent, 'inserted')) !== 819 || count(array_filter($concurrent, fn ($r) => $r['replay'])) !== 1) { throw new RuntimeException('Concurrent import duplicated writes'); }
$results['concurrent_import_single_writer'] = true;
$results['apply'] = array_values(array_filter($concurrent, fn ($r) => ! $r['replay']))[0];
$results['second_apply'] = $importer->apply($plan, false);
if ($results['second_apply']['inserted'] !== 0 || $results['second_apply']['updated'] !== 0) { throw new RuntimeException('Replay wrote data'); }
$batch = $results['apply']['batch_id'];
$user = App\Models\User::findOrFail($uid);
$results['dated_balances'] = [];
foreach ($plan['expected_documentary_balances'] as $accountId => $expected) {
    $account = App\Domains\Finance\Models\BankAccount::findOrFail($accountId);
    $history = app(App\Domains\Finance\Services\AccountBalanceHistory::class)->at($account, $plan['cutoff']);
    if ($history['balance_cents'] !== $expected) { throw new RuntimeException('History differs from documentary balance'); }
    $results['dated_balances'][] = $history;
}
$allocation = DB::table('expense_allocations')->where('batch_id', $batch)->first();
$results['lunch'] = ['personal_cents' => $allocation->personal_cents, 'reimbursable_cents' => $allocation->reimbursable_cents, 'repaid_cents' => $allocation->repaid_cents];
if ((int) $allocation->personal_cents !== 2075 || (int) $allocation->repaid_cents !== 6225) { throw new RuntimeException('Lunch allocation mismatch'); }
$card = App\Domains\Finance\Models\CreditCard::findOrFail('019f2bb6-71d1-709c-a233-91799c125906');
foreach ($plan['card_forecast'] as $forecast) {
    $summary = app(App\Domains\Finance\Services\CardStatementSummary::class)->get($card, substr($forecast['due_date'], 0, 7));
    if ($summary['charges_cents'] !== $forecast['gross_known_commitments']) { throw new RuntimeException('Card cycle mismatch: '.$forecast['due_date']); }
    if ($summary['paid_cents'] !== 0) { throw new RuntimeException('Historical payment incorrectly allocated to a future statement'); }
    unset($summary['transactions'], $summary['payments']);
    $results['card_cycles'][] = $summary;
}
$report = app(App\Domains\Finance\Services\FinanceReportService::class)->getMonthlySummary($user, 2026, 10);
$method = new ReflectionMethod(App\Domains\Reports\Services\DashboardService::class, 'getFinanceSummary');
$dashboard = $method->invoke(app(App\Domains\Reports\Services\DashboardService::class), $user, now(), now()->startOfMonth(), now()->endOfMonth());
if ($dashboard['month_income'] !== $report['income'] || $dashboard['month_expenses'] !== $report['expenses'] || $dashboard['month_balance'] !== $report['balance']) { throw new RuntimeException('Dashboard/report mismatch'); }
$results['dashboard_report_consistent'] = true;
$results['deleted_preserved'] = DB::table('transactions')->where('user_id', $uid)->whereNotNull('deleted_at')->count() === 52;
$results['retained_cash_cents'] = (int) DB::table('import_sources')->where('batch_id', $batch)->where('reconciliation_status', 'conflict')->sum('cash_cents');
if ($results['retained_cash_cents'] !== -164432 || ! $results['deleted_preserved']) { throw new RuntimeException('Held sources or deleted legacy changed'); }
foreach ($snapshot['transactions'] as $old) {
    if ($old['transaction_date'] > '2026-10-13' || $old['deleted_at']) {
        $current = (array) DB::table('transactions')->where('id', $old['id'])->first();
        foreach ($old as $field => $value) { if ((string) $current[$field] !== (string) $value) { throw new RuntimeException('Preserved legacy changed'); } }
    }
}
$results['future_and_deleted_original_fields_preserved'] = true;
$changed = DB::table('transactions')->where('import_batch_id', $batch)->first();
$originalNotes = $changed->notes;
DB::table('transactions')->where('id', $changed->id)->update(['notes' => 'Legitimate later edit']);
try { $importer->rollback($uid, $batch, false); throw new LogicException('Unsafe rollback accepted'); }
catch (RuntimeException $error) { $results['later_edit_blocked'] = str_starts_with($error->getMessage(), 'Rollback conflict:'); }
if (! $results['later_edit_blocked']) { throw new RuntimeException('Missing conflict detection'); }
DB::table('transactions')->where('id', $changed->id)->update(['notes' => $originalNotes]);
$later = (string) Illuminate\Support\Str::uuid();
DB::table('transactions')->insert(['id' => $later, 'user_id' => $uid, 'type' => 'expense', 'amount' => '1.00', 'description' => 'Later legitimate pending fixture', 'status' => 'pending', 'transaction_date' => '2027-03-01', 'created_at' => now(), 'updated_at' => now()]);
$results['rollback'] = $importer->rollback($uid, $batch, false);
$results['later_unrelated_record_preserved'] = DB::table('transactions')->where('id', $later)->exists();
if (! $results['later_unrelated_record_preserved']) { throw new RuntimeException('Rollback removed later record'); }
DB::table('transactions')->where('id', $later)->delete();
$after = DB::table('transactions')->where('user_id', $uid)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
$results['restored_exactly'] = RecoveryPlan::hash($before) === RecoveryPlan::hash($after);
if (! $results['restored_exactly']) { throw new RuntimeException('Rollback differs from baseline'); }
file_put_contents($out.'/resultado-mysql.json', json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
