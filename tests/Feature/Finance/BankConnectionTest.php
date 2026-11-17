<?php

namespace Tests\Feature\Finance;

use App\Domains\BankSync\Models\BankConnection;
use App\Domains\BankSync\Models\BankConnectionAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Bank connection through Pluggy (MeuPluggy). Pluggy is simulated; nothing leaves the machine. */
class BankConnectionTest extends TestCase
{
    use RefreshDatabase;

    private const ITEM = '0b6e0f8e-8d2a-4c5e-9a43-7d1f3e2c9a10';

    private const CHECKING = '9a1c2b3d-0000-4000-8000-000000000001';

    private const CARD = '9a1c2b3d-0000-4000-8000-000000000002';

    private User $user;

    private ?array $item = null;

    private array $accounts = [];

    /** @var array<string, list<array>> */
    private array $tx = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.pluggy.client_id' => 'client-id', 'services.pluggy.client_secret' => 'client-secret', 'services.pluggy.base_url' => 'https://api.pluggy.ai']);
        Cache::flush();
        $this->travelTo('2026-11-17 12:00:00');
        Http::preventStrayRequests();
        $this->user = User::factory()->create(['timezone' => 'America/Sao_Paulo']);
        $this->item = $this->itemPayload($this->user->id);
        $this->accounts = [
            ['id' => self::CHECKING, 'type' => 'BANK', 'subtype' => 'CHECKING_ACCOUNT', 'name' => 'Conta corrente', 'number' => '1234567-8',
                'balance' => 1523.47, 'currencyCode' => 'BRL', 'bankData' => ['transferNumber' => '260/0001/1234567-8']],
            ['id' => self::CARD, 'type' => 'CREDIT', 'subtype' => 'CREDIT_CARD', 'name' => 'Mastercard', 'number' => '4321',
                'balance' => 820.10, 'currencyCode' => 'BRL', 'creditData' => [
                    'creditLimit' => 5000, 'availableCreditLimit' => 4179.9, 'balanceCloseDate' => '2026-11-20', 'balanceDueDate' => '2026-11-27',
                ]],
        ];
        $this->tx = [
            self::CHECKING => [
                $this->transaction('t-1', -50.25, 'DEBIT', '2026-11-10T00:00:00.000Z', 'Mercado'),
                $this->transaction('t-2', 3000, 'CREDIT', '2026-11-05T00:00:00.000Z', 'Salário'),
                $this->transaction('t-3', -19.9, 'DEBIT', '2026-11-12T15:00:00.000Z', 'Padaria', 'PENDING'),
            ],
            self::CARD => [
                // The sign decides, not `type` (the Pluggy sandbox sends card charges as CREDIT)
                // International purchase: amount in dollars, amountInAccountCurrency in reais
                ['amount' => 22.0, 'amountInAccountCurrency' => 120.5] + $this->transaction('c-1', 120.5, 'CREDIT', '2026-11-12T01:30:00.000Z', 'Loja', 'POSTED', ['installmentNumber' => 2, 'totalInstallments' => 5]),
                $this->transaction('c-2', -500, 'CREDIT', '2026-11-03T00:00:00.000Z', 'Pagamento recebido'),
            ],
        ];
        $this->fakePluggy();
    }

    private function itemPayload(?string $clientUserId, string $status = 'UPDATED'): array
    {
        return [
            'id' => self::ITEM, 'status' => $status, 'executionStatus' => 'SUCCESS', 'clientUserId' => $clientUserId,
            'connector' => ['id' => 200, 'name' => 'MeuPluggy', 'primaryColor' => 'ef294b', 'imageUrl' => 'https://cdn.pluggy.ai/assets/connector-icons/sandbox.svg'],
            'lastUpdatedAt' => '2026-11-17T09:00:00.000Z', 'consentExpiresAt' => '2027-11-17T09:00:00.000Z',
        ];
    }

    private function transaction(string $id, float $amount, string $type, string $date, string $description, string $status = 'POSTED', ?array $card = null): array
    {
        return compact('id', 'amount', 'type', 'date', 'description', 'status') + [
            'currencyCode' => 'BRL', 'category' => 'Shopping', 'creditCardMetadata' => $card,
        ];
    }

    private function fakePluggy(): void
    {
        Http::fake(function (Request $request) {
            $url = parse_url($request->url());
            parse_str($url['query'] ?? '', $query);
            $path = $url['path'];

            return match (true) {
                $path === '/auth' => Http::response(['apiKey' => 'api-key']),
                $path === '/connect_token' => Http::response(['accessToken' => 'connect-token']),
                $path === '/items/'.self::ITEM && $request->method() === 'DELETE' => Http::response([], 204),
                $path === '/items/'.self::ITEM => $this->item ? Http::response($this->item) : Http::response(['code' => 404], 404),
                str_starts_with($path, '/items/') => Http::response(['code' => 404], 404),
                $path === '/accounts' => Http::response(['results' => $this->accounts]),
                $path === '/v2/transactions' => $this->transactionsPage($query),
                default => Http::response(['message' => 'unexpected'], 500),
            };
        });
    }

    /** Two per page, so the cursor is exercised */
    private function transactionsPage(array $query)
    {
        $all = $this->tx[$query['accountId']] ?? [];
        if (($query['after'] ?? null) === 'page-2') {
            return Http::response(['results' => array_slice($all, 2), 'next' => null]);
        }

        return Http::response([
            'results' => array_slice($all, 0, 2),
            'next' => count($all) > 2 ? '?accountId='.$query['accountId'].'&after=page-2' : null,
        ]);
    }

    private function connect()
    {
        return $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/finance/bank-connections', ['item_id' => self::ITEM]);
    }

    public function test_status_says_whether_the_server_has_credentials(): void
    {
        $this->actingAs($this->user, 'sanctum')->getJson('/api/v1/finance/bank-connections/status')
            ->assertOk()->assertJsonPath('data.configured', true);

        config(['services.pluggy.client_secret' => null]);
        $this->getJson('/api/v1/finance/bank-connections/status')->assertOk()->assertJsonPath('data.configured', false);
        $this->postJson('/api/v1/finance/bank-connections/connect-token')->assertStatus(503);
    }

    public function test_connect_token_is_issued_for_this_user_and_the_secret_stays_on_the_server(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/finance/bank-connections/connect-token')
            ->assertOk()->assertJsonPath('data.access_token', 'connect-token')->assertJsonPath('data.update_item_id', null);

        $this->assertStringNotContainsString('client-secret', $response->getContent());
        Http::assertSent(fn (Request $r) => $r->url() === 'https://api.pluggy.ai/connect_token'
            && $r['options']['clientUserId'] === $this->user->id
            // avoidDuplicates makes Pluggy refuse every MeuPluggy bank after the first (no credentials to tell them apart)
            && ! array_key_exists('avoidDuplicates', $r['options'])
            && $r->hasHeader('X-API-KEY', 'api-key'));
        // The API key is cached: a second token does not authenticate again
        $this->postJson('/api/v1/finance/bank-connections/connect-token')->assertOk();
        Http::assertSentCount(3);
    }

    public function test_connecting_mirrors_accounts_and_transactions_without_touching_the_ledger(): void
    {
        $this->connect()->assertCreated()
            ->assertJsonPath('data.name', 'Nubank')
            ->assertJsonPath('data.status', 'UPDATED')
            ->assertJsonPath('data.needs_action', false)
            ->assertJsonPath('data.connector.color', '#ef294b')
            ->assertJsonCount(2, 'data.accounts');

        $connection = BankConnection::sole();
        $this->assertSame($this->user->id, $connection->user_id);
        $this->assertNotNull($connection->last_synced_at);

        $checking = BankConnectionAccount::where('external_account_id', self::CHECKING)->sole();
        $this->assertSame(['BANK', '260', 152347], [$checking->type, $checking->bank_code, $checking->balance_cents]);
        $card = BankConnectionAccount::where('external_account_id', self::CARD)->sole();
        $this->assertSame([82010, 500000, 417990, '2026-11-27'], [$card->balance_cents, $card->credit_limit_cents, $card->available_credit_limit_cents, $card->balance_due_date->toDateString()]);

        // Third checking transaction came on the second cursor page
        $this->assertDatabaseCount('bank_connection_transactions', 5);
        $this->assertDatabaseHas('bank_connection_transactions', ['external_transaction_id' => 't-1', 'amount_cents' => 5025, 'direction' => 'out', 'date' => '2026-11-10', 'status' => 'posted']);
        $this->assertDatabaseHas('bank_connection_transactions', ['external_transaction_id' => 't-2', 'amount_cents' => 300000, 'direction' => 'in']);
        $this->assertDatabaseHas('bank_connection_transactions', ['external_transaction_id' => 't-3', 'status' => 'pending', 'date' => '2026-11-12']);
        // Card purchase at 01:30 UTC is 22:30 of the day before in Brasília; a card payment is money in
        $this->assertDatabaseHas('bank_connection_transactions', ['external_transaction_id' => 'c-1', 'amount_cents' => 12050, 'direction' => 'out', 'date' => '2026-11-11', 'installment_number' => 2, 'total_installments' => 5]);
        $this->assertDatabaseHas('bank_connection_transactions', ['external_transaction_id' => 'c-2', 'amount_cents' => 50000, 'direction' => 'in']);

        // Mirror only: no ledger record is created
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('bank_accounts', 0);
        $this->assertDatabaseCount('credit_cards', 0);

        $this->getJson('/api/v1/finance/bank-connections')->assertOk()
            ->assertJsonPath('data.totals.bank_balance_cents', 152347)
            ->assertJsonPath('data.totals.card_balance_cents', 82010)
            ->assertJsonPath('data.connections.0.accounts.0.transactions_count', 3)
            ->assertJsonPath('data.connections.0.accounts.0.last_transaction_date', '2026-11-12');

        $this->getJson("/api/v1/finance/bank-connections/accounts/{$checking->id}/transactions")->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.description', 'Padaria')
            ->assertJsonPath('meta.total', 3);
    }

    public function test_sandbox_cards_come_with_the_sign_inverted_and_are_read_the_right_way(): void
    {
        // What the Pluggy sandbox (connector "Pluggy Bank") really sends: charges and the bill owed negative
        $this->item['connector'] += ['isSandbox' => true];
        $this->accounts[1]['balance'] = -1006.2;
        $this->tx[self::CARD] = [$this->transaction('s-1', -89.9, 'CREDIT', '2026-11-10T03:00:00.000Z', 'SMART FIT ACADEMIA')];

        $this->connect()->assertCreated();
        $this->assertSame(100620, BankConnectionAccount::where('external_account_id', self::CARD)->sole()->balance_cents);
        $this->assertDatabaseHas('bank_connection_transactions', ['external_transaction_id' => 's-1', 'direction' => 'out', 'amount_cents' => 8990, 'date' => '2026-11-10']);
        // The checking account keeps the bank sign
        $this->assertDatabaseHas('bank_connection_transactions', ['external_transaction_id' => 't-1', 'direction' => 'out']);
    }

    public function test_an_item_created_for_someone_else_is_refused(): void
    {
        $this->item = $this->itemPayload('another-user');
        $this->connect()->assertUnprocessable()->assertJsonValidationErrors('item_id');

        $this->item = null;
        $this->connect()->assertUnprocessable();
        $this->assertDatabaseCount('bank_connections', 0);
    }

    public function test_an_item_registered_by_another_user_cannot_be_taken(): void
    {
        $other = User::factory()->create();
        BankConnection::factory()->create(['user_id' => $other->id, 'external_item_id' => self::ITEM]);

        $this->connect()->assertUnprocessable();
        $this->assertSame($other->id, BankConnection::sole()->user_id);
    }

    public function test_item_id_must_be_a_uuid(): void
    {
        $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/finance/bank-connections', ['item_id' => '../items'])
            ->assertUnprocessable()->assertJsonValidationErrors('item_id');
    }

    public function test_registering_the_same_item_twice_keeps_one_connection(): void
    {
        $this->connect()->assertCreated();
        $this->connect()->assertCreated();
        $this->assertDatabaseCount('bank_connections', 1);
        $this->assertDatabaseCount('bank_connection_transactions', 5);
    }

    public function test_a_later_sync_reads_a_recent_window_and_follows_the_bank(): void
    {
        $this->connect()->assertCreated();
        $connection = BankConnection::sole();
        BankConnectionAccount::where('external_account_id', self::CHECKING)->sole()->transactions()
            ->create(['user_id' => $this->user->id, 'external_transaction_id' => 'old', 'date' => '2025-01-10', 'description' => 'Antiga',
                'amount_cents' => 100, 'direction' => 'out', 'status' => 'posted', 'payload' => []]);

        // Pluggy settled the pending one under a new id and the balance moved
        $this->tx[self::CHECKING] = [
            $this->tx[self::CHECKING][0],
            $this->transaction('t-3b', -19.9, 'DEBIT', '2026-11-12T15:00:00.000Z', 'Padaria'),
        ];
        $this->accounts[0]['balance'] = 1503.57;

        $this->postJson("/api/v1/finance/bank-connections/{$connection->id}/sync")->assertOk()
            ->assertJsonPath('data.accounts.0.balance_cents', 150357);

        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/v2/transactions') && str_contains($r->url(), 'dateFrom='));
        $this->assertDatabaseMissing('bank_connection_transactions', ['external_transaction_id' => 't-3']);
        $this->assertDatabaseHas('bank_connection_transactions', ['external_transaction_id' => 't-3b', 'status' => 'posted']);
        // Outside the window: kept
        $this->assertDatabaseHas('bank_connection_transactions', ['external_transaction_id' => 'old']);
    }

    public function test_an_expired_authorisation_keeps_the_data_and_asks_for_action(): void
    {
        $this->connect()->assertCreated();
        $connection = BankConnection::sole();
        $this->item = $this->itemPayload($this->user->id, 'LOGIN_ERROR');
        $this->accounts = [];
        $this->tx = [];

        $this->postJson("/api/v1/finance/bank-connections/{$connection->id}/sync")->assertOk()
            ->assertJsonPath('data.needs_action', true)
            ->assertJsonCount(2, 'data.accounts');
        $this->assertDatabaseCount('bank_connection_transactions', 5);

        // The widget can be reopened in update mode for this item
        $this->postJson('/api/v1/finance/bank-connections/connect-token', ['connection_id' => $connection->id])
            ->assertOk()->assertJsonPath('data.update_item_id', self::ITEM);
        Http::assertSent(fn (Request $r) => $r->url() === 'https://api.pluggy.ai/connect_token' && $r['itemId'] === self::ITEM);
    }

    public function test_pluggy_failure_answers_502_and_is_kept_on_the_connection(): void
    {
        $this->connect()->assertCreated();
        $connection = BankConnection::sole();
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['api.pluggy.ai/*' => Http::response(['message' => 'boom'], 500)]);

        $this->postJson("/api/v1/finance/bank-connections/{$connection->id}/sync")->assertStatus(502);
        $this->assertNotNull($connection->fresh()->last_sync_error);
        $this->assertDatabaseCount('bank_connection_transactions', 5);
    }

    public function test_renaming_and_disconnecting(): void
    {
        $this->connect()->assertCreated();
        $connection = BankConnection::sole();

        $this->patchJson("/api/v1/finance/bank-connections/{$connection->id}", ['name' => 'Nubank pessoal'])
            ->assertOk()->assertJsonPath('data.name', 'Nubank pessoal');
        $this->patchJson("/api/v1/finance/bank-connections/{$connection->id}", ['name' => ''])
            ->assertOk()->assertJsonPath('data.name', 'Nubank')->assertJsonPath('data.custom_name', null);

        $this->deleteJson("/api/v1/finance/bank-connections/{$connection->id}")->assertNoContent();
        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && $r->url() === 'https://api.pluggy.ai/items/'.self::ITEM);
        $this->assertDatabaseCount('bank_connections', 0);
        $this->assertDatabaseCount('bank_connection_accounts', 0);
        $this->assertDatabaseCount('bank_connection_transactions', 0);
    }

    public function test_users_only_see_their_own_connections(): void
    {
        $this->connect()->assertCreated();
        $account = BankConnectionAccount::firstOrFail();
        $other = User::factory()->create();

        $this->actingAs($other, 'sanctum')->getJson('/api/v1/finance/bank-connections')->assertOk()
            ->assertJsonCount(0, 'data.connections')
            ->assertJsonPath('data.totals.bank_balance_cents', 0);
        $this->getJson("/api/v1/finance/bank-connections/accounts/{$account->id}/transactions")->assertForbidden();
    }

    public function test_the_scheduled_pull_reads_every_connection(): void
    {
        $this->connect()->assertCreated();
        $this->accounts[0]['balance'] = 10;

        $this->artisan('bank-sync:pull')->assertSuccessful();
        $this->assertSame(1000, BankConnectionAccount::where('external_account_id', self::CHECKING)->sole()->balance_cents);
    }
}
