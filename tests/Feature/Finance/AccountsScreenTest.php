<?php

namespace Tests\Feature\Finance;

use App\Domains\Finance\Models\BankAccount;
use App\Domains\Finance\Models\CreditCard;
use App\Domains\Finance\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Accounts screen: money in and out per account in a month, and the date of the user's last check. */
class AccountsScreenTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_movements_show_cash_in_and_out_per_account_for_the_month(): void
    {
        $user = User::factory()->create(['timezone' => 'America/Sao_Paulo']);
        $nubank = BankAccount::factory()->create(['user_id' => $user->id]);
        $box = BankAccount::factory()->create(['user_id' => $user->id, 'type' => 'investment']);
        $card = CreditCard::factory()->create(['user_id' => $user->id, 'bank_account_id' => $nubank->id]);
        $tx = fn (array $a) => Transaction::factory()->create(['user_id' => $user->id, 'status' => 'confirmed', 'transaction_date' => '2026-10-10', ...$a]);

        $tx(['account_id' => $nubank->id, 'type' => 'income', 'amount' => '2500.00']);
        $tx(['account_id' => $nubank->id, 'type' => 'expense', 'amount' => '74.00']);
        $tx(['account_id' => $nubank->id, 'destination_account_id' => $box->id, 'type' => 'transfer', 'amount' => '500.00']);
        $tx(['account_id' => $nubank->id, 'card_id' => $card->id, 'type' => 'transfer', 'amount' => '560.15']); // card payment
        $tx(['account_id' => null, 'card_id' => $card->id, 'type' => 'expense', 'amount' => '92.00']); // card purchase: not cash
        $tx(['account_id' => $nubank->id, 'type' => 'expense', 'amount' => '700.00', 'status' => 'pending']);
        $tx(['account_id' => $nubank->id, 'type' => 'expense', 'amount' => '33.00', 'transaction_date' => '2026-09-30']);

        $rows = collect($this->actingAs($user, 'sanctum')->getJson('/api/v1/finance/accounts/movements?year=2026&month=10')->assertOk()->json('data'))->keyBy('account_id');

        $this->assertEqualsWithDelta(2500, $rows[$nubank->id]['in'], 0.001);
        $this->assertEqualsWithDelta(74 + 500 + 560.15, $rows[$nubank->id]['out'], 0.001);
        $this->assertEqualsWithDelta(500, $rows[$box->id]['in'], 0.001);
        $this->assertEqualsWithDelta(0, $rows[$box->id]['out'], 0.001);
    }

    public function test_last_checked_on_ignores_openings_brought_in_by_imports(): void
    {
        $user = User::factory()->create(['timezone' => 'America/Sao_Paulo']);
        $imported = BankAccount::factory()->create(['user_id' => $user->id, 'name' => 'Importada']);
        $checked = BankAccount::factory()->create(['user_id' => $user->id, 'name' => 'Conferida']);
        $batch = (string) Str::uuid();
        DB::table('import_batches')->insert(['id' => $batch, 'user_id' => $user->id, 'reference' => 'r', 'plan_hash' => 'h', 'version' => 4, 'status' => 'applied', 'cutoff' => '2026-06-01', 'created_at' => now(), 'updated_at' => now()]);
        $checkpoint = fn (BankAccount $a, string $on, string $kind, ?string $batchId) => DB::table('account_balance_checkpoints')->insert([
            'id' => (string) Str::uuid(), 'user_id' => $user->id, 'account_id' => $a->id, 'batch_id' => $batchId,
            'observed_on' => $on, 'balance_cents' => 100, 'kind' => $kind, 'evidence' => 'test', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $checkpoint($imported, '2026-06-01', 'opening', $batch);
        $checkpoint($checked, '2026-06-01', 'opening', $batch);
        $checkpoint($checked, '2026-10-15', 'observation', null);

        $accounts = collect($this->actingAs($user, 'sanctum')->getJson('/api/v1/finance/accounts')->assertOk()->json('data'))->keyBy('name');

        $this->assertNull($accounts['Importada']['last_checked_on']);
        $this->assertTrue($accounts['Importada']['balance_verified']);
        $this->assertSame('2026-10-15', $accounts['Conferida']['last_checked_on']);
    }
}
