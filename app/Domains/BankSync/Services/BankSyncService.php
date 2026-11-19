<?php

namespace App\Domains\BankSync\Services;

use App\Domains\BankSync\Exceptions\PluggyException;
use App\Domains\BankSync\Models\BankConnection;
use App\Domains\BankSync\Models\BankConnectionAccount;
use App\Domains\BankSync\Models\BankConnectionTransaction;
use App\Domains\Finance\Services\UserClock;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Mirrors what the bank reports through Pluggy into bank_connection_* tables, then lets BankReconciler
 * match it with the ledger. It never writes to the ledger itself: that only happens on the user's approval.
 */
class BankSyncService
{
    /** Later syncs re-read this many days back: Pluggy may settle or replace recent transactions. */
    public const RESYNC_WINDOW_DAYS = 60;

    /** Item statuses whose data can be read. LOGIN_ERROR etc. may answer empty lists: never mirror those. */
    private const READABLE = ['UPDATED', 'OUTDATED'];

    /** Safety net of the hourly check: a connection is read in full at least this often. */
    public const FULL_READ_HOURS = 24;

    public function __construct(
        private readonly PluggyClient $pluggy,
        private readonly BankReconciler $reconciler,
    ) {}

    public function connectToken(User $user, ?BankConnection $connection = null): string
    {
        return $this->pluggy->createConnectToken($user->id, $connection?->external_item_id);
    }

    /** @return array{connections: Collection<int, BankConnection>, totals: array<string, int>} */
    public function overview(User $user): array
    {
        $connections = BankConnection::forUser($user->id)->orderBy('created_at')->get();
        $connections->each(fn (BankConnection $c) => $this->loadAccounts($c));
        $accounts = $connections->flatMap->accounts;
        $brl = $accounts->where('currency_code', 'BRL');

        return [
            'connections' => $connections,
            'totals' => [
                'bank_balance_cents' => (int) $brl->where('type', 'BANK')->sum('balance_cents'),
                'card_balance_cents' => (int) $brl->where('type', 'CREDIT')->sum('balance_cents'),
                'accounts_count' => $brl->count(),
                'to_review_count' => (int) $accounts->sum('to_review_count'),
            ],
        ];
    }

    public function loadAccounts(BankConnection $connection): BankConnection
    {
        return $connection->load(['accounts' => fn ($q) => $q
            ->with(['bankAccount:id,name', 'creditCard:id,name'])
            ->withCount(['transactions', 'transactions as to_review_count' => fn ($t) => $t->where('review_status', BankConnectionTransaction::NEW)])
            ->withMax('transactions', 'date')
            ->orderBy('type')->orderBy('name')]);
    }

    /** Registers the item the widget just created, once Pluggy confirms it was created for this user. */
    public function register(User $user, string $itemId): BankConnection
    {
        $existing = BankConnection::where('external_item_id', $itemId)->first();
        if ($existing && $existing->user_id !== $user->id) {
            throw ValidationException::withMessages(['item_id' => 'Conexão não encontrada. Conecte o banco de novo.']);
        }

        $item = $this->pluggy->item($itemId);
        if ($item === null || ($item['clientUserId'] ?? null) !== $user->id) {
            throw ValidationException::withMessages(['item_id' => 'Conexão não encontrada. Conecte o banco de novo.']);
        }

        $connection = $existing ?? new BankConnection(['user_id' => $user->id, 'provider' => 'pluggy', 'external_item_id' => $itemId]);
        $this->applyItem($connection, $item);
        try {
            $connection->save();
        } catch (UniqueConstraintViolationException) {
            // The same widget success sent twice at once: the other request registered it
            $connection = BankConnection::where('external_item_id', $itemId)->where('user_id', $user->id)->firstOrFail();
        }

        return $this->sync($connection, $item);
    }

    /** Reads the item, its accounts and their transactions again from Pluggy. */
    /**
     * Scheduled check (hourly). Pluggy only changes an item when it reads the bank again (MeuPluggy: once a
     * day), so one cheap call asks for the item and accounts/transactions are read only when Pluggy read the
     * bank since the last read here. A connection that failed, changed status or was not read in
     * FULL_READ_HOURS is read anyway.
     *
     * @return bool whether accounts and transactions were read
     */
    public function pullIfUpdated(BankConnection $connection): bool
    {
        $item = $this->pluggy->item($connection->external_item_id);
        $updatedAt = $this->timestamp($item['lastUpdatedAt'] ?? null);
        $unchanged = $item !== null
            && $connection->last_sync_error === null
            && $connection->status === ($item['status'] ?? null)
            && $connection->last_synced_at?->gt(now()->subHours(self::FULL_READ_HOURS))
            // Stored to the second; Pluggy sends milliseconds
            && $updatedAt !== null && $connection->provider_updated_at?->timestamp === $updatedAt->timestamp;
        if ($unchanged) {
            return false;
        }
        $this->sync($connection, $item);

        return true;
    }

    public function sync(BankConnection $connection, ?array $item = null): BankConnection
    {
        $lock = Cache::lock('bank-sync:'.$connection->id, 300);
        if (! $lock->get()) {
            throw new ConflictHttpException('Esta conexão já está sendo atualizada. Aguarde alguns instantes.');
        }

        try {
            @set_time_limit(300);
            $item ??= $this->pluggy->item($connection->external_item_id);
            if ($item === null) {
                $connection->forceFill([
                    'status' => 'NOT_FOUND', 'execution_status' => null,
                    'last_sync_error' => 'A Pluggy não tem mais esta conexão. Conecte o banco de novo.',
                ])->save();

                return $this->loadAccounts($connection);
            }

            $this->applyItem($connection, $item);
            $connection->save();
            if (in_array($connection->status, self::READABLE, true)) {
                $this->syncAccounts($connection, (bool) ($item['connector']['isSandbox'] ?? false));
                // New bank transactions of linked accounts are matched or queued for review right away
                $connection->accounts()->get()->each(fn (BankConnectionAccount $a) => $this->reconciler->reconcile($a));
                $connection->last_synced_at = now();
            }
            $connection->last_sync_error = null;
            $connection->save();
        } catch (PluggyException $e) {
            $connection->forceFill(['last_sync_error' => Str::limit($e->getMessage(), 250)])->save();
            throw $e;
        } finally {
            $lock->release();
        }

        return $this->loadAccounts($connection);
    }

    public function rename(BankConnection $connection, ?string $name): BankConnection
    {
        $name = $name === null ? null : trim($name);
        $connection->update(['name' => $name === '' ? null : $name]);

        return $this->loadAccounts($connection);
    }

    /** Revokes VaultOS' access in Pluggy, then drops the mirrored data. */
    public function disconnect(BankConnection $connection): void
    {
        $this->pluggy->deleteItem($connection->external_item_id);
        $connection->delete();
    }

    private function applyItem(BankConnection $connection, array $item): void
    {
        $connector = $item['connector'] ?? [];
        $connection->fill([
            'connector_id' => $connector['id'] ?? null,
            'connector_name' => Str::limit((string) ($connector['name'] ?? 'Banco'), 117),
            'connector_image_url' => isset($connector['imageUrl']) ? Str::limit((string) $connector['imageUrl'], 497) : null,
            'connector_color' => isset($connector['primaryColor']) ? Str::limit((string) $connector['primaryColor'], 7, '') : null,
            'status' => (string) ($item['status'] ?? 'UPDATING'),
            'execution_status' => $item['executionStatus'] ?? null,
            'consent_expires_at' => $this->timestamp($item['consentExpiresAt'] ?? null),
            'provider_updated_at' => $this->timestamp($item['lastUpdatedAt'] ?? null),
        ]);
    }

    /**
     * @param  bool  $sandbox  Pluggy's test connectors send cards with the sign inverted (charges negative,
     *                         balance owed negative); only for them the card sign is flipped back.
     */
    private function syncAccounts(BankConnection $connection, bool $sandbox): void
    {
        $timezone = UserClock::timezone($connection->user_id);
        $seen = [];
        foreach ($this->pluggy->accounts($connection->external_item_id) as $data) {
            if (! is_string($data['id'] ?? null)) {
                continue;
            }
            $credit = $data['creditData'] ?? [];
            $sign = $sandbox && ($data['type'] ?? null) === 'CREDIT' ? -1 : 1;
            $account = $connection->accounts()->firstOrNew(['external_account_id' => $data['id']]);
            $account->fill([
                'user_id' => $connection->user_id,
                'type' => ($data['type'] ?? null) === 'CREDIT' ? 'CREDIT' : 'BANK',
                'subtype' => $data['subtype'] ?? null,
                'name' => Str::limit(trim((string) ($data['name'] ?? '')) ?: trim((string) ($data['marketingName'] ?? '')) ?: 'Conta', 157),
                'number' => isset($data['number']) ? Str::limit((string) $data['number'], 37) : null,
                'bank_code' => BankInstitutions::codeFromTransferNumber($data['bankData']['transferNumber'] ?? null),
                'currency_code' => Str::upper(Str::limit((string) ($data['currencyCode'] ?? 'BRL'), 3, '')),
                'balance_cents' => $sign * $this->cents($data['balance'] ?? 0),
                'credit_limit_cents' => isset($credit['creditLimit']) ? $this->cents($credit['creditLimit']) : null,
                'available_credit_limit_cents' => isset($credit['availableCreditLimit']) ? $this->cents($credit['availableCreditLimit']) : null,
                'balance_close_date' => $this->day($credit['balanceCloseDate'] ?? null),
                'balance_due_date' => $this->day($credit['balanceDueDate'] ?? null),
            ])->save();
            $this->syncTransactions($account, $timezone, $sign);
            $seen[] = $account->id;
        }

        // An account the bank no longer reports goes away; an empty answer never wipes the mirror
        if ($seen !== []) {
            $connection->accounts()->whereNotIn('id', $seen)->delete();
        }
    }

    private function syncTransactions(BankConnectionAccount $account, string $timezone, int $sign = 1): void
    {
        $from = $account->transactions_synced_at
            ? Carbon::now($timezone)->subDays(self::RESYNC_WINDOW_DAYS)->toDateString()
            : null;
        $now = now();
        $rows = [];
        foreach ($this->pluggy->transactions($account->external_account_id, $from) as $t) {
            if (! is_string($t['id'] ?? null) || ! isset($t['amount'], $t['date'])) {
                continue;
            }
            // International purchases: amount is in the original currency; amountInAccountCurrency is in reais
            $cents = $sign * $this->cents($t['amountInAccountCurrency'] ?? $t['amount']);
            $meta = $t['creditCardMetadata'] ?? [];
            $rows[] = [
                'id' => (string) Str::uuid(),
                'bank_connection_account_id' => $account->id,
                'user_id' => $account->user_id,
                'external_transaction_id' => Str::limit($t['id'], 64, ''),
                'date' => $this->transactionDate((string) $t['date'], $timezone),
                'description' => Str::limit(trim((string) ($t['description'] ?? '')) ?: trim((string) ($t['descriptionRaw'] ?? '')) ?: 'Sem descrição', 252),
                'amount_cents' => abs($cents),
                'direction' => $this->direction($cents, $account->isCredit()),
                'status' => ($t['status'] ?? 'POSTED') === 'PENDING' ? 'pending' : 'posted',
                'category' => isset($t['category']) ? Str::limit((string) $t['category'], 117) : null,
                'installment_number' => isset($meta['installmentNumber']) ? (int) $meta['installmentNumber'] : null,
                'total_installments' => isset($meta['totalInstallments']) ? (int) $meta['totalInstallments'] : null,
                'payload' => json_encode($t, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::transaction(function () use ($account, $rows, $from) {
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('bank_connection_transactions')->upsert(
                    $chunk,
                    ['bank_connection_account_id', 'external_transaction_id'],
                    ['date', 'description', 'amount_cents', 'direction', 'status', 'category', 'installment_number', 'total_installments', 'payload', 'updated_at'],
                );
            }
            // Pluggy replaces the id of a transaction it cannot match again (e.g. pending → posted):
            // inside the window just read, what it no longer reports goes away
            if ($from !== null && $rows !== []) {
                $account->transactions()->where('date', '>=', $from)
                    ->whereNotIn('external_transaction_id', array_column($rows, 'external_transaction_id'))
                    ->delete();
            }
            $account->forceFill(['transactions_synced_at' => now()])->save();
        });
    }

    /**
     * The amount's sign decides, as Pluggy documents: on a bank account a negative amount left the
     * account; on a card a positive amount is a charge and a negative one a payment or refund.
     * The `type` field is not trusted (the Pluggy sandbox sends card charges as CREDIT).
     */
    private function direction(int $cents, bool $credit): string
    {
        return ($credit ? $cents > 0 : $cents < 0) ? 'out' : 'in';
    }

    /**
     * Pluggy dates are UTC. Banks that only know the day send midnight UTC: that is the day itself, not
     * 21h of the day before in Brasília. A real time is converted to the user's timezone.
     */
    private function transactionDate(string $iso, string $timezone): string
    {
        $utc = Carbon::parse($iso)->utc();

        return $utc->format('H:i:s') === '00:00:00' ? $utc->toDateString() : $utc->setTimezone($timezone)->toDateString();
    }

    private function cents(mixed $value): int
    {
        return (int) round(((float) $value) * 100);
    }

    private function day(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}/', $value) ? substr($value, 0, 10) : null;
    }

    private function timestamp(mixed $value): ?Carbon
    {
        return is_string($value) && $value !== '' ? Carbon::parse($value)->utc() : null;
    }
}
