<?php

namespace App\Domains\Finance\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class RecoveryImporter
{
    /** Fields the user may change on imported records without affecting cash (ignored by the rollback check). */
    private const CLASSIFICATION_FIELDS = ['category_id', 'notes', 'classification_status', 'economic_kind', 'economic_income_cents', 'economic_expense_cents', 'updated_at'];

    private function isolated(?string $userId = null, ?string $planHash = null): void
    {
        $connection = config('database.connections.'.config('database.default'));
        $local = ! app()->environment('production') && in_array($connection['host'] ?? '', ['127.0.0.1', 'localhost'], true) && str_starts_with($connection['database'] ?? '', 'vaultos_recovery_');
        $approved = config('finance_recovery.approved_target_enabled') === true
            && is_string(config('finance_recovery.approved_host')) && $connection['host'] === config('finance_recovery.approved_host')
            && is_string(config('finance_recovery.approved_database')) && $connection['database'] === config('finance_recovery.approved_database')
            && $userId !== null && $userId === config('finance_recovery.approved_user')
            && is_string(config('finance_recovery.approved_plan_sha256')) && preg_match('/^[a-f0-9]{64}$/D', config('finance_recovery.approved_plan_sha256'))
            && ($planHash === null || hash_equals(config('finance_recovery.approved_plan_sha256'), $planHash));
        if (($connection['driver'] ?? '') !== 'mysql' || ! empty($connection['url']) || (! $local && ! $approved)) {
            throw new RuntimeException('Recovery requires isolated local MySQL or an explicitly approved host/database/user/plan');
        }
    }

    private function image(string $table, string $id): ?array
    {
        $row = DB::table($table)->where('id', $id)->lockForUpdate()->first();

        return $row ? (array) $row : null;
    }

    private function images(string $table, array $ids): array
    {
        $result = [];
        sort($ids);
        foreach (array_chunk(array_unique($ids), 300) as $chunk) {
            foreach (DB::table($table)->whereIn('id', $chunk)->orderBy('id')->lockForUpdate()->get() as $row) {
                $result[$row->id] = (array) $row;
            }
        }

        return $result;
    }

    private function insertRows(string $table, array $rows): void
    {
        // Different optional columns must retain their database defaults.
        $groups = [];
        foreach ($rows as $row) {
            ksort($row);
            $groups[implode('|', array_keys($row))][] = $row;
        }
        foreach ($groups as $group) {
            foreach (array_chunk($group, 200) as $chunk) {
                DB::table($table)->insert($chunk);
            }
        }
    }

    private function journal(string $batch, string $table, string $id, ?array $before): void
    {
        $after = $this->image($table, $id);
        DB::table('import_changes')->insert([
            'batch_id' => $batch, 'target_table' => $table, 'target_id' => $id,
            'before' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR),
            'after' => json_encode($after, JSON_THROW_ON_ERROR),
            'after_hash' => RecoveryPlan::hash($after ?? []),
        ]);
    }

    public function apply(array $plan, bool $dryRun = true): array
    {
        $this->isolated($plan['user_id'] ?? null, $plan['plan_hash'] ?? null);

        return DB::transaction(function () use ($plan, $dryRun) {
            $uid = $plan['user_id'];
            if (! DB::table('users')->where('id', $uid)->lockForUpdate()->first()) {
                throw new RuntimeException('Unknown user');
            }
            $existing = DB::table('import_batches')->where('user_id', $uid)->where('reference', $plan['batch_id'])->first();
            if ($existing) {
                if ($existing->plan_hash !== $plan['plan_hash'] || $existing->status !== 'applied') {
                    throw new RuntimeException('Batch identity reused or rolled back');
                }

                return ['replay' => true, 'inserted' => 0, 'updated' => 0];
            }
            foreach ($plan['baseline'] as $table => $rows) {
                if (! in_array($table, ['transactions', 'bank_accounts', 'credit_cards', 'transaction_categories'], true)) {
                    throw new RuntimeException('Invalid baseline table');
                }
                $baselineImages = $this->images($table, array_column($rows, 'id'));
                foreach ($rows as $row) {
                    $current = $baselineImages[$row['id']] ?? null;
                    if (! $current || $current['user_id'] !== $uid) {
                        throw new RuntimeException('Missing or foreign baseline');
                    }
                    foreach ($row as $key => $value) {
                        if (! array_key_exists($key, $current) || (string) $current[$key] !== (string) $value) {
                            throw new RuntimeException('Baseline changed: '.$table.'/'.$row['id'].'/'.$key);
                        }
                    }
                }
            }
            // New activity after the snapshot must be reconciled before this historical recovery.
            $expectedIds = array_column($plan['baseline']['transactions'], 'id');
            if (DB::table('transactions')->where('user_id', $uid)->whereNotIn('id', $expectedIds)->exists()) {
                throw new RuntimeException('Additional transactions since snapshot');
            }
            $report = ['replay' => false, 'inserted' => 0, 'updated' => 0, 'sources' => count($plan['sources']), 'conflicts' => count($plan['conflicts']), 'dry_run' => $dryRun];
            $recordImages = $this->images('transactions', array_column($plan['records'], 'id'));
            $relations = [];
            foreach (['bank_accounts', 'credit_cards', 'transaction_categories', 'finance_goals'] as $table) {
                $relations[$table] = DB::table($table)->where('user_id', $uid)->lockForUpdate()->get()->keyBy('id')->all();
            }
            foreach ($plan['records'] as $record) {
                $current = $recordImages[$record['id']] ?? null;
                if (($record['before'] === null) !== ($current === null)) {
                    throw new RuntimeException('Insertion/precondition conflict');
                }
                foreach (['account_id' => 'bank_accounts', 'destination_account_id' => 'bank_accounts', 'card_id' => 'credit_cards', 'category_id' => 'transaction_categories', 'goal_id' => 'finance_goals'] as $key => $table) {
                    $relation = $relations[$table][$record['after'][$key] ?? ''] ?? null;
                    if (! empty($record['after'][$key]) && (! $relation || ($table !== 'transaction_categories' && $relation->deleted_at !== null))) {
                        throw new RuntimeException('Foreign or deleted relation: '.$key);
                    }
                }
                $report[$current ? 'updated' : 'inserted']++;
            }
            if (DB::table('import_sources')->where('user_id', $uid)->whereIn('source_key', array_column($plan['sources'], 'key'))->exists()) {
                throw new RuntimeException('Source already imported');
            }
            foreach ($plan['sources'] as $source) {
                if ($source['cash'] && ! isset($relations['bank_accounts'][$source['cash']['account_id']])) {
                    throw new RuntimeException('Foreign cash account');
                }
            }
            if ($dryRun) {
                return $report;
            }
            $batch = (string) Str::uuid();
            DB::table('import_batches')->insert(['id' => $batch, 'user_id' => $uid, 'reference' => $plan['batch_id'], 'plan_hash' => $plan['plan_hash'], 'version' => 3, 'status' => 'applied', 'cutoff' => $plan['cutoff'], 'report' => json_encode($report), 'created_at' => now(), 'updated_at' => now()]);
            $sourceIds = [];
            $sourceRows = [];
            foreach ($plan['sources'] as $source) {
                $id = $sourceIds[$source['key']] = (string) Str::uuid();
                $sourceRows[] = [
                    'id' => $id, 'batch_id' => $batch, 'user_id' => $uid, 'source_key' => $source['key'], 'kind' => $source['kind'], 'file' => $source['file'], 'sha256' => $source['sha256'],
                    'locator' => json_encode($source['locator']), 'payload' => json_encode($source['payload']), 'account_id' => $source['cash']['account_id'] ?? null,
                    'posted_on' => $source['cash']['date'] ?? null, 'cash_cents' => $source['cash']['cents'] ?? 0, 'reconciliation_status' => $source['ready'] ? 'matched' : 'conflict',
                ];
            }
            $this->insertRows('import_sources', $sourceRows);
            $newRecords = [];
            $links = [];
            foreach ($plan['records'] as $record) {
                $before = $recordImages[$record['id']] ?? null;
                $after = $record['after'] + ['import_batch_id' => $batch];
                if (! $before) {
                    $after += ['created_at' => now(), 'updated_at' => now()];
                }
                foreach (['recurrence_config'] as $json) {
                    if (is_array($after[$json] ?? null)) {
                        $after[$json] = json_encode($after[$json]);
                    }
                }
                if ($before) {
                    DB::table('transactions')->where('id', $record['id'])->update($after);
                } else {
                    $newRecords[] = $after;
                }
                foreach ($record['source_keys'] as $key) {
                    $links[] = ['source_id' => $sourceIds[$key], 'transaction_id' => $record['id'], 'role' => $record['conflict'] ? 'candidate' : 'evidence'];
                }
            }
            $this->insertRows('transactions', $newRecords);
            $afterImages = $this->images('transactions', array_column($plan['records'], 'id'));
            $journal = [];
            foreach ($plan['records'] as $record) {
                $before = $recordImages[$record['id']] ?? null;
                $after = $afterImages[$record['id']];
                $journal[] = ['batch_id' => $batch, 'target_table' => 'transactions', 'target_id' => $record['id'],
                    'before' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR),
                    'after' => json_encode($after, JSON_THROW_ON_ERROR), 'after_hash' => RecoveryPlan::hash($after)];
            }
            $this->insertRows('import_changes', $journal);
            $this->insertRows('transaction_sources', $links);
            $lunch = $plan['manual_lunch_settlement'];
            $lunchTransaction = DB::table('transaction_sources')->where('source_id', $sourceIds[$lunch['expense_source']])->value('transaction_id');
            if (! $lunchTransaction || $lunch['gross_cents'] !== $lunch['personal_cents'] + $lunch['repaid_cents'] + $lunch['receivable_remaining_cents']) {
                throw new RuntimeException('Invalid shared expense');
            }
            DB::table('expense_allocations')->insert(['id' => (string) Str::uuid(), 'user_id' => $uid, 'batch_id' => $batch, 'transaction_id' => $lunchTransaction, 'personal_cents' => $lunch['personal_cents'], 'reimbursable_cents' => $lunch['gross_cents'] - $lunch['personal_cents'], 'repaid_cents' => $lunch['repaid_cents'], 'evidence' => json_encode($lunch), 'created_at' => now(), 'updated_at' => now()]);
            $cardId = collect($plan['sources'])->firstWhere('kind', 'card')['payload']['card_id'];
            foreach ($plan['card_forecast'] as $statement) {
                DB::table('card_statements')->insert(['id' => (string) Str::uuid(), 'user_id' => $uid, 'batch_id' => $batch, 'card_id' => $cardId, 'cycle' => substr($statement['due_date'], 0, 7), 'due_on' => $statement['due_date'], 'status' => substr($statement['due_date'], 0, 7) === substr($plan['cutoff'], 0, 7) ? 'closed' : 'open', 'documentary_cents' => $statement['gross_known_commitments'], 'manual_cents' => $statement['amount_due_verified'], 'evidence' => json_encode($statement), 'created_at' => now(), 'updated_at' => now()]);
            }
            foreach ($plan['openings_cents'] as $accountId => $cents) {
                DB::table('account_balance_checkpoints')->insert(['id' => (string) Str::uuid(), 'user_id' => $uid, 'account_id' => $accountId, 'batch_id' => $batch, 'observed_on' => $plan['opening_date'], 'balance_cents' => $cents, 'kind' => 'opening', 'evidence' => 'Documentary opening before first source movement', 'created_at' => now(), 'updated_at' => now()]);
                $balance = $cents + (int) DB::table('import_sources')->where('batch_id', $batch)->where('account_id', $accountId)->sum('cash_cents');
                if ($balance !== $plan['expected_documentary_balances'][$accountId]) {
                    throw new RuntimeException('Documentary balance mismatch');
                }
                $before = $this->image('bank_accounts', $accountId);
                DB::table('bank_accounts')->where('id', $accountId)->update(['balance' => FinancialEffects::decimal($balance)]);
                $this->journal($batch, 'bank_accounts', $accountId, $before);
            }

            return $report + ['batch_id' => $batch, 'balances_cents' => $plan['expected_documentary_balances']];
        });
    }

    public function rollback(string $userId, string $batchId, bool $dryRun = true): array
    {
        $this->isolated($userId);

        return DB::transaction(function () use ($userId, $batchId, $dryRun) {
            DB::table('users')->where('id', $userId)->lockForUpdate()->firstOrFail();
            $batch = DB::table('import_batches')->where('id', $batchId)->where('user_id', $userId)->first();
            if (! $batch || $batch->status !== 'applied') {
                throw new RuntimeException('Batch not active');
            }
            $this->isolated($userId, $batch->plan_hash);
            $changes = DB::table('import_changes')->where('batch_id', $batchId)->orderByDesc('id')->get();
            $classified = [];
            foreach ($changes as $change) {
                if (! in_array($change->target_table, ['transactions', 'bank_accounts'], true)) {
                    throw new RuntimeException('Rollback conflict: '.$change->target_id);
                }
                $current = $this->image($change->target_table, $change->target_id) ?? [];
                if (RecoveryPlan::hash($current) === $change->after_hash) {
                    continue;
                }
                // The user may classify imported records (category, notes, nature) without touching cash:
                // that is not a conflict, and the classification survives the rollback.
                $after = $change->after === null ? null : json_decode($change->after, true, flags: JSON_THROW_ON_ERROR);
                $classification = $change->target_table === 'transactions' && $after !== null && $current !== []
                    ? array_intersect_key($current, array_flip(self::CLASSIFICATION_FIELDS))
                    : null;
                if ($classification === null || RecoveryPlan::hash(array_merge($current, array_intersect_key($after, $classification))) !== $change->after_hash) {
                    throw new RuntimeException('Rollback conflict: '.$change->target_id);
                }
                $classified[$change->target_id] = array_diff_key($classification, ['updated_at' => true]);
            }
            if ($dryRun) {
                return ['dry_run' => true, 'changes' => $changes->count()];
            }
            $sources = DB::table('import_sources')->where('batch_id', $batchId)->pluck('id');
            DB::table('transaction_sources')->whereIn('source_id', $sources)->delete();
            foreach (['expense_allocations', 'card_statements', 'account_balance_checkpoints'] as $table) {
                DB::table($table)->where('batch_id', $batchId)->delete();
            }
            foreach ($changes as $change) {
                $before = $change->before === null ? null : json_decode($change->before, true, flags: JSON_THROW_ON_ERROR);
                $query = DB::table($change->target_table)->where('id', $change->target_id);
                if ($before === null) {
                    $query->delete();
                } else {
                    $query->update(array_merge($before, $classified[$change->target_id] ?? []));
                }
            }
            // Keep immutable sources and the journal for audit, but deactivate their financial effect through batch state.
            DB::table('import_batches')->where('id', $batchId)->update(['status' => 'rolled_back', 'updated_at' => now()]);

            return ['rolled_back' => true, 'changes' => $changes->count()];
        });
    }
}
