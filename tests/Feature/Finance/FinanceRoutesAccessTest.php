<?php

namespace Tests\Feature\Finance;

use App\Domains\Finance\Models\BankAccount;
use App\Domains\Finance\Models\CreditCard;
use App\Domains\Finance\Models\FinanceGoal;
use App\Domains\Finance\Models\Transaction;
use App\Domains\Finance\Models\TransactionCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every finance route that takes a record: the owner succeeds and another user is refused.
 * Two bugs lived in these basic paths unnoticed (archiving an account answered 500; editing, completing
 * and deleting a goal answered 403 to the owner), because no test walked them.
 */
class FinanceRoutesAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $other;

    /** @var array<string, string> */
    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['timezone' => 'America/Sao_Paulo']);
        $this->other = User::factory()->create();
        $u = $this->owner->id;

        $account = BankAccount::factory()->create(['user_id' => $u, 'balance' => '1000.00']);
        $empty = BankAccount::factory()->create(['user_id' => $u]);
        $card = CreditCard::factory()->create(['user_id' => $u, 'bank_account_id' => $account->id]);
        $emptyCard = CreditCard::factory()->create(['user_id' => $u, 'bank_account_id' => $account->id]);
        $category = TransactionCategory::factory()->create(['user_id' => $u, 'type' => 'expense']);
        $transaction = Transaction::factory()->create([
            'user_id' => $u, 'account_id' => $account->id, 'category_id' => $category->id,
            'type' => 'expense', 'amount' => '50.00', 'status' => 'pending', 'transaction_date' => now()->subDay()->toDateString(),
        ]);
        $goal = FinanceGoal::factory()->create(['user_id' => $u, 'status' => 'active']);

        $as = $this->actingAs($this->owner, 'sanctum');
        $tag = $as->postJson('/api/v1/finance/tags', ['name' => 'Viagem'])->assertCreated()->json('data.id');
        $budget = $as->postJson('/api/v1/finance/budgets', [
            'month' => 10, 'year' => 2026, 'base_amount' => 5000,
            'items' => [['category_id' => $category->id, 'amount' => 300]],
        ])->assertCreated()->json('data.id');

        $this->ids = [
            'account' => $account->id, 'empty' => $empty->id, 'card' => $card->id, 'emptyCard' => $emptyCard->id,
            'category' => $category->id, 'transaction' => $transaction->id, 'goal' => $goal->id, 'tag' => $tag, 'budget' => $budget,
        ];
    }

    /** [method, uri, body] — deletions last so the other calls still find their record */
    private function routes(): array
    {
        $i = $this->ids;

        return [
            ['GET', "accounts/{$i['account']}", []],
            ['PATCH', "accounts/{$i['account']}", ['name' => 'Conta renomeada']],
            ['PATCH', "accounts/{$i['empty']}", ['is_active' => false]],
            ['GET', "accounts/{$i['account']}/balance-at?date=".now()->toDateString(), []],
            ['GET', "accounts/{$i['account']}/balance-observations", []],
            ['POST', "accounts/{$i['account']}/balance-observations", ['date' => now()->toDateString(), 'balance' => '1000.00', 'evidence' => 'teste']],
            ['GET', "cards/{$i['card']}", []],
            ['PATCH', "cards/{$i['card']}", ['name' => 'Cartão renomeado']],
            ['GET', "cards/{$i['card']}/overview", []],
            ['GET', "cards/{$i['card']}/statement?cycle=".now()->format('Y-m'), []],
            ['GET', "transactions/{$i['transaction']}", []],
            ['PUT', "transactions/{$i['transaction']}", [
                'type' => 'expense', 'amount' => 60, 'description' => 'Editada', 'transaction_date' => now()->subDay()->toDateString(),
                'account_id' => $i['account'], 'category_id' => $i['category'],
            ]],
            ['PATCH', "transactions/{$i['transaction']}/confirm", []],
            ['PUT', "categories/{$i['category']}", ['name' => 'Categoria renomeada']],
            ['PUT', "tags/{$i['tag']}", ['name' => 'Tag renomeada']],
            ['PUT', "budgets/{$i['budget']}", ['base_amount' => 4800]],
            ['PUT', "goals/{$i['goal']}", ['name' => 'Meta renomeada']],
            ['POST', "goals/{$i['goal']}/complete", []],
            ['DELETE', "transactions/{$i['transaction']}", []],
            ['DELETE', "tags/{$i['tag']}", []],
            ['DELETE', "budgets/{$i['budget']}", []],
            ['DELETE', "goals/{$i['goal']}", []],
            ['DELETE', "cards/{$i['emptyCard']}", []],
            ['DELETE', "accounts/{$i['empty']}", []],
        ];
    }

    public function test_another_user_is_refused_on_every_finance_record_route(): void
    {
        foreach ($this->routes() as [$method, $uri, $body]) {
            $status = $this->actingAs($this->other, 'sanctum')->json($method, "/api/v1/finance/{$uri}", $body)->status();
            // 422 is a refusal too: validation runs first and rejects the owner's account/category ids
            $this->assertContains($status, [403, 404, 422], "{$method} {$uri} answered {$status} to another user");
        }

        // Nothing of the owner's changed
        $this->assertDatabaseHas('transactions', ['id' => $this->ids['transaction'], 'status' => 'pending', 'deleted_at' => null]);
        $this->assertDatabaseHas('finance_goals', ['id' => $this->ids['goal'], 'status' => 'active', 'deleted_at' => null]);
        $this->assertDatabaseHas('bank_accounts', ['id' => $this->ids['empty'], 'is_active' => true, 'deleted_at' => null]);
        $this->assertDatabaseHas('credit_cards', ['id' => $this->ids['emptyCard'], 'deleted_at' => null]);
    }

    public function test_the_owner_succeeds_on_every_finance_record_route(): void
    {
        foreach ($this->routes() as [$method, $uri, $body]) {
            $response = $this->actingAs($this->owner, 'sanctum')->json($method, "/api/v1/finance/{$uri}", $body);
            $this->assertTrue($response->isSuccessful(), "{$method} {$uri} answered {$response->status()} to the owner: ".$response->getContent());
        }
    }
}
