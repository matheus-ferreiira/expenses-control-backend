<?php

namespace App\Domains\Finance\Services;

use Illuminate\Support\Facades\Validator;
use RuntimeException;

/** Only reviewed, content-addressed plans are accepted; no SQL or table names are inputs. */
final class RecoveryPlan
{
    public const FIELDS = ['id', 'user_id', 'account_id', 'destination_account_id', 'card_id', 'statement_month', 'category_id', 'goal_id', 'type', 'amount', 'description', 'notes', 'transaction_date', 'is_recurring', 'recurrence_config', 'status', 'recurrence_group_id', 'installment_number', 'total_installments', 'installment_group_id', 'deleted_at', 'created_at', 'updated_at', 'economic_kind', 'classification_status', 'reconciliation_status', 'posted_on', 'original_purchase_on', 'statement_due_on', 'economic_income_cents', 'economic_expense_cents', 'signed_card_cents'];

    public static function hash(array $value): string
    {
        $sort = function (&$item) use (&$sort): void {
            if (! is_array($item)) {
                return;
            }
            if (! array_is_list($item)) {
                ksort($item);
            }
            foreach ($item as &$child) {
                $sort($child);
            }
        };
        $sort($value);

        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function load(string $path, string $expectedHash, string $userId, string $root): array
    {
        if (! preg_match('/^[a-f0-9]{64}$/D', $expectedHash) || ! hash_equals($expectedHash, hash_file('sha256', $path))) {
            throw new RuntimeException('Plan hash mismatch');
        }
        $plan = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        Validator::make($plan, [
            'batch_id' => ['required', 'string', 'max:100'], 'user_id' => ['required', 'uuid'],
            'cutoff' => ['required', 'date_format:Y-m-d'], 'opening_date' => ['required', 'date_format:Y-m-d'],
            'sources' => ['required', 'array'], 'records' => ['required', 'array'], 'baseline' => ['required', 'array'],
            'openings_cents' => ['required', 'array'], 'expected_documentary_balances' => ['required', 'array'],
            'sources.*.key' => ['required', 'string', 'max:191'], 'sources.*.kind' => ['required', 'in:bank,manual_bank,card'],
            'sources.*.sha256' => ['required', 'regex:/^[a-f0-9]{64}$/D'],
            'records.*.id' => ['required', 'uuid'], 'records.*.after.transaction_date' => ['required', 'date_format:Y-m-d'],
            'records.*.after.status' => ['required', 'in:confirmed,pending'],
            'records.*.after.description' => ['required', 'string', 'max:255'],
            'records.*.after.economic_income_cents' => ['required', 'integer', 'min:0'],
            'records.*.after.economic_expense_cents' => ['required', 'integer'],
            'records.*.after.economic_kind' => ['required', 'in:internal_transfer,card_payment,informational,historical_debt,fee,fee_refund,unknown,card_purchase,shared_expense,reimbursement,benefit,income,expense,legacy'],
        ])->validate();
        if (($plan['version'] ?? null) !== 3 || ($plan['mode'] ?? null) !== 'laravel-isolated-recovery' || ($plan['user_id'] ?? null) !== $userId) {
            throw new RuntimeException('Unsupported plan version, mode or user');
        }
        $sources = [];
        $sourceRows = [];
        $fileHashes = [];
        $base = realpath($root);
        if (! $base) {
            throw new RuntimeException('Missing evidence root');
        }
        foreach ($plan['sources'] as $source) {
            if (isset($sources[$source['key']])) {
                throw new RuntimeException('Duplicate source');
            }
            if ($source['key'] !== ($source['kind'] === 'card' ? 'C:' : 'B:').$source['original_id'] || $source['payload']['id'] !== $source['original_id']) {
                throw new RuntimeException('Invalid source identity');
            }
            if (($source['kind'] === 'card') !== ($source['cash'] === null)) {
                throw new RuntimeException('Invalid source cash ownership');
            }
            if ($source['cash'] !== null && ($source['cash']['cents'] !== $source['payload']['amount'] || $source['cash']['date'] !== $source['payload']['date'])) {
                throw new RuntimeException('Cash differs from evidence');
            }
            $file = realpath($base.DIRECTORY_SEPARATOR.$source['file']);
            if (! $file || ! str_starts_with($file, $base.DIRECTORY_SEPARATOR) || ! hash_equals($source['sha256'], $fileHashes[$file] ??= hash_file('sha256', $file))) {
                throw new RuntimeException('Source missing, outside root or modified: '.$source['key']);
            }
            if ($source['cash'] !== null && (! is_int($source['cash']['cents']) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/D', $source['cash']['date']))) {
                throw new RuntimeException('Invalid source cash');
            }
            $sources[$source['key']] = true;
            $sourceRows[$source['key']] = $source;
        }
        $seen = [];
        foreach ($plan['records'] as $record) {
            $after = $record['after'];
            if (in_array($after['economic_kind'], ['internal_transfer', 'card_payment', 'informational', 'historical_debt', 'unknown', 'reimbursement'], true) && ($after['economic_income_cents'] !== 0 || $after['economic_expense_cents'] !== 0)) {
                throw new RuntimeException('Non-economic event cannot create income or consumption');
            }
            if (isset($seen[$record['id']]) || $record['id'] !== $after['id'] || $after['user_id'] !== $userId || array_diff(array_keys($after), self::FIELDS)) {
                throw new RuntimeException('Invalid transaction identity or fields');
            }
            if (! in_array($after['type'], ['income', 'expense', 'transfer'], true) || FinancialEffects::cents($after['amount']) <= 0 || ! empty($after['deleted_at'])) {
                throw new RuntimeException('Invalid transaction type, amount or deletion');
            }
            foreach ($record['source_keys'] as $key) {
                if (! isset($sources[$key])) {
                    throw new RuntimeException('Unknown source link');
                }
                if ($record['before'] === null && (! empty($sourceRows[$key]['payload']['matches']) || ! empty($sourceRows[$key]['payload']['candidates']))) {
                    throw new RuntimeException('Insertion may duplicate legacy');
                }
            }
            if ($record['before'] === null && (! $record['source_keys'] || $record['conflict'])) {
                throw new RuntimeException('Unproven insertion');
            }
            $seen[$record['id']] = true;
        }
        $plan['plan_hash'] = $expectedHash;

        return $plan;
    }
}
