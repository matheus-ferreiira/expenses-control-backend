<?php

namespace Tests\Feature\Finance;

use App\Domains\BankSync\Models\BankConnection;
use App\Domains\BankSync\Models\BankConnectionAccount;
use App\Domains\BankSync\Models\BankConnectionTransaction as BankTx;
use App\Domains\BankSync\Models\BankReviewRule;
use App\Domains\BankSync\Services\BankReconciler;
use App\Domains\Finance\Models\BankAccount;
use App\Domains\Finance\Models\CreditCard;
use App\Domains\Finance\Models\Transaction;
use App\Domains\Finance\Models\TransactionCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Reconciling bank transactions (Pluggy) with the ledger: what is already there is linked, the rest waits in
 * "A lançar" with a suggestion, and nothing reaches the ledger without the user's approval.
 */
class BankReconcileTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private BankAccount $nubank;

    private BankAccount $caixa;

    private BankConnection $connection;

    private BankConnectionAccount $checking;

    private TransactionCategory $food;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->travelTo('2026-11-17 12:00:00');
        $this->user = User::factory()->create(['name' => 'Matheus Ferreira', 'timezone' => 'America/Sao_Paulo']);
        $this->nubank = BankAccount::factory()->create(['user_id' => $this->user->id, 'name' => 'Nubank', 'balance' => '500.00']);
        $this->caixa = BankAccount::factory()->create(['user_id' => $this->user->id, 'name' => 'Caixa', 'balance' => '3000.00']);
        $this->food = TransactionCategory::factory()->create(['user_id' => $this->user->id, 'name' => 'Alimentação', 'type' => 'expense']);
        $this->connection = BankConnection::factory()->create(['user_id' => $this->user->id]);
        $this->checking = BankConnectionAccount::factory()->create(['bank_connection_id' => $this->connection->id]);
        $this->actingAs($this->user, 'sanctum');
    }

    private function bankTx(BankConnectionAccount $account, string $date, float $amount, string $direction, string $description, array $extra = []): BankTx
    {
        return BankTx::create($extra + [
            'bank_connection_account_id' => $account->id, 'user_id' => $account->user_id,
            'external_transaction_id' => (string) Str::uuid(), 'date' => $date, 'description' => $description,
            'amount_cents' => (int) round($amount * 100), 'direction' => $direction, 'status' => 'posted', 'payload' => [],
        ]);
    }

    /** Pluggy's card metadata naming the bill (due month) a charge went to. */
    private static function bill(string $month): array
    {
        return ['creditCardMetadata' => ['billForecastDate' => $month]];
    }

    private function ledger(array $attributes): Transaction
    {
        return Transaction::factory()->create($attributes + ['user_id' => $this->user->id, 'status' => 'confirmed']);
    }

    /** A month already in the ledger (one movement a day up to 28/10), then three weeks the user did not enter. */
    private function octoberInTheLedger(): void
    {
        foreach (range(20, 28) as $day) {
            $date = "2026-10-{$day}";
            $this->ledger(['account_id' => $this->nubank->id, 'type' => 'expense', 'amount' => 83, 'description' => 'Lanche', 'category_id' => $this->food->id, 'transaction_date' => $date]);
            $this->bankTx($this->checking, $date, 83, 'out', 'Transferência enviada pelo Pix|SAFE2PAY INTERMEDIACAO DE NEG');
        }
    }

    private function link(BankConnectionAccount $account, array $body = [])
    {
        $key = $account->isCredit() ? 'credit_card_id' : 'bank_account_id';

        return $this->patchJson("/api/v1/finance/bank-connections/accounts/{$account->id}", $body + [$key => $account->isCredit() ? null : $this->nubank->id]);
    }

    private function review(array $items)
    {
        return $this->postJson('/api/v1/finance/bank-connections/review', ['items' => $items]);
    }

    public function test_linking_matches_what_is_in_the_ledger_and_queues_the_rest_from_where_they_stop_agreeing(): void
    {
        $this->octoberInTheLedger();
        $missing = $this->bankTx($this->checking, '2026-11-03', 56, 'out', 'Transferência enviada pelo Pix|THAIS FREITAS DA SILVA');
        $later = $this->bankTx($this->checking, '2026-11-12', 21, 'in', 'Transferência Recebida|PETERSON ALVES GERVAZIO');
        $future = $this->bankTx($this->checking, '2026-11-30', 10, 'out', 'Agendado');

        $this->link($this->checking)->assertOk()
            ->assertJsonPath('data.accounts.0.link.import_from', '2026-11-03')
            ->assertJsonPath('data.accounts.0.link.name', 'Nubank')
            ->assertJsonPath('data.accounts.0.to_review_count', 2);

        $this->assertSame(9, BankTx::where('review_status', BankTx::MATCHED)->whereNotNull('transaction_id')->count());
        $this->assertSame(BankTx::NEW, $missing->fresh()->review_status);
        $this->assertSame(BankTx::NEW, $later->fresh()->review_status);
        $this->assertNull($future->fresh()->review_status, 'a future-dated movement waits for its date');
        // Linking writes nothing to the ledger
        $this->assertSame(9, Transaction::count());

        // A start date chosen by the user wins; older gaps become history
        $this->link($this->checking, ['import_from' => '2026-11-10'])->assertOk()->assertJsonPath('data.accounts.0.to_review_count', 1);
        $this->assertSame(BankTx::BEFORE_START, $missing->fresh()->review_status);
    }

    public function test_review_suggests_from_history_and_approving_creates_the_ledger_record_and_learns(): void
    {
        $this->octoberInTheLedger();
        $snack = $this->bankTx($this->checking, '2026-11-04', 46.8, 'out', 'Transferência enviada|SAFE2PAY INTERMEDIACAO DE NEGOCIOS LTD');
        $friend = $this->bankTx($this->checking, '2026-11-05', 30, 'out', 'Transferência enviada pelo Pix|JAQUELINE DA SILVA FARIA');
        $this->link($this->checking)->assertOk();

        $items = collect($this->getJson('/api/v1/finance/bank-connections/review')->assertOk()->assertJsonPath('data.count', 2)->json('data.items'))->keyBy('id');
        // Same counterpart as the matched October snacks (another spelling of it): same description and category
        $this->assertSame(['expense', 'Lanche', $this->food->id, 'history'], [
            $items[$snack->id]['suggestion']['action'], $items[$snack->id]['suggestion']['description'],
            $items[$snack->id]['suggestion']['category_id'], $items[$snack->id]['suggestion']['source'],
        ]);
        $this->assertSame(['expense', 'JAQUELINE DA SILVA FARIA', null], [
            $items[$friend->id]['suggestion']['action'], $items[$friend->id]['suggestion']['description'], $items[$friend->id]['suggestion']['source'],
        ]);

        $this->review([
            ['id' => $snack->id, 'action' => 'expense', 'description' => 'Lanche', 'category_id' => $this->food->id],
            ['id' => $friend->id, 'action' => 'expense', 'description' => 'Jantinha', 'category_id' => $this->food->id],
        ])->assertOk()->assertJsonPath('data.applied', 2)->assertJsonPath('data.remaining', 0);

        $created = Transaction::find($friend->fresh()->transaction_id);
        $this->assertSame(['Jantinha', '30.00', '2026-11-05', $this->nubank->id, $this->food->id, 'confirmed'], [
            $created->description, $created->amount, $created->transaction_date->toDateString(), $created->account_id, $created->category_id, $created->status->value,
        ]);
        $this->assertSame(BankTx::IMPORTED, $friend->fresh()->review_status);
        $this->assertSame('423.20', $this->nubank->fresh()->balance, 'confirmed expenses move the balance like any other');

        // Next time the same person shows up, the choice comes back as a rule
        $again = $this->bankTx($this->checking, '2026-11-07', 30, 'out', 'Transferência enviada pelo Pix|JAQUELINE DA SILVA FARIA');
        app(BankReconciler::class)->reconcile($this->checking->fresh());
        $suggestion = collect($this->getJson('/api/v1/finance/bank-connections/review')->json('data.items'))->firstWhere('id', $again->id)['suggestion'];
        $this->assertSame(['Jantinha', 'rule'], [$suggestion['description'], $suggestion['source']]);
        $this->assertSame(1, BankReviewRule::where('match_key', 'JAQUELINE DA SILVA F')->value('uses'));
    }

    public function test_a_pending_forecast_is_suggested_and_confirmed_with_the_real_value_and_date(): void
    {
        $this->octoberInTheLedger();
        $rent = $this->ledger(['account_id' => $this->nubank->id, 'type' => 'expense', 'amount' => 700, 'description' => 'Aluguel', 'status' => 'pending', 'transaction_date' => '2026-10-31']);
        $light = $this->ledger(['account_id' => $this->nubank->id, 'type' => 'expense', 'amount' => 300, 'description' => 'Luz', 'status' => 'pending', 'transaction_date' => '2026-11-15']);
        $paidRent = $this->bankTx($this->checking, '2026-11-04', 700, 'out', 'Transferência enviada pelo Pix|MATEUS CARVALHO TOSTES');
        $paidLight = $this->bankTx($this->checking, '2026-11-16', 292.27, 'out', 'Pagamento efetuado|ENEL DISTRIBUICAO RIO');
        $this->link($this->checking)->assertOk();

        $items = collect($this->getJson('/api/v1/finance/bank-connections/review')->json('data.items'))->keyBy('id');
        $this->assertSame(['confirm', $rent->id, 'Aluguel'], [$items[$paidRent->id]['suggestion']['action'], $items[$paidRent->id]['suggestion']['transaction_id'], $items[$paidRent->id]['suggestion']['description']]);
        $this->assertSame(['confirm', $light->id], [$items[$paidLight->id]['suggestion']['action'], $items[$paidLight->id]['suggestion']['transaction_id']]);

        $this->review([
            ['id' => $paidRent->id, 'action' => 'confirm', 'transaction_id' => $rent->id],
            ['id' => $paidLight->id, 'action' => 'confirm', 'transaction_id' => $light->id],
        ])->assertOk()->assertJsonPath('data.applied', 2);

        $this->assertSame(['confirmed', '2026-11-16', '292.27'], [$light->fresh()->status->value, $light->fresh()->transaction_date->toDateString(), $light->fresh()->amount]);
        $this->assertSame(['confirmed', '2026-11-04'], [$rent->fresh()->status->value, $rent->fresh()->transaction_date->toDateString()]);
        $this->assertSame([BankTx::MATCHED, $light->id], [$paidLight->fresh()->review_status, $paidLight->fresh()->transaction_id]);
        // No new ledger row: the forecast itself was confirmed
        $this->assertSame(11, Transaction::count());
    }

    public function test_a_bill_already_confirmed_in_a_confirmar_is_linked_not_duplicated(): void
    {
        $this->octoberInTheLedger();
        // The user confirmed the Internet bill in "A confirmar" with the forecast value before the bank showed it
        $internet = $this->ledger(['account_id' => $this->nubank->id, 'type' => 'expense', 'amount' => 129.90, 'description' => 'Internet',
            'transaction_date' => '2026-11-10', 'is_recurring' => true, 'recurrence_group_id' => (string) Str::uuid()]);
        $paid = $this->bankTx($this->checking, '2026-11-16', 135.06, 'out', 'Pagamento efetuado|BIA PADUA INTERNET E SERV DE COMUNICACAO');
        $this->link($this->checking)->assertOk();

        $suggestion = $this->getJson('/api/v1/finance/bank-connections/review')->json('data.items.0.suggestion');
        $this->assertSame(['match', $internet->id, 'Internet'], [$suggestion['action'], $suggestion['transaction_id'], $suggestion['description']]);
        $this->review([['id' => $paid->id, 'action' => 'match', 'transaction_id' => $internet->id]])->assertOk()->assertJsonPath('data.applied', 1);
        $this->assertSame(10, Transaction::count());
        // The bank's value and date win; the account takes the 5.16 difference
        $this->assertSame(['135.06', '2026-11-16'], [$internet->fresh()->amount, $internet->fresh()->transaction_date->toDateString()]);
        $this->assertSame('494.84', $this->nubank->fresh()->balance);
    }

    public function test_money_from_oneself_is_a_transfer_between_accounts(): void
    {
        $this->octoberInTheLedger();
        $salary = $this->bankTx($this->checking, '2026-11-15', 2500, 'in', 'Transferência Recebida|MATHEUS FERREIRA PINHEIRO');
        $this->link($this->checking)->assertOk();

        $suggestion = $this->getJson('/api/v1/finance/bank-connections/review')->json('data.items.0.suggestion');
        $this->assertSame('transfer', $suggestion['action']);

        $this->review([['id' => $salary->id, 'action' => 'transfer', 'account_id' => $this->caixa->id, 'description' => 'Do salário']])
            ->assertOk()->assertJsonPath('data.applied', 1);
        $transfer = Transaction::find($salary->fresh()->transaction_id);
        $this->assertSame(['transfer', $this->caixa->id, $this->nubank->id], [$transfer->type->value, $transfer->account_id, $transfer->destination_account_id]);
        $this->assertSame(['500.00', '3000.00'], [$this->caixa->fresh()->balance, $this->nubank->fresh()->balance]);
    }

    public function test_cards_match_installments_by_number_and_the_bill_payment_ties_both_sides(): void
    {
        $card = CreditCard::factory()->create(['user_id' => $this->user->id, 'bank_account_id' => $this->nubank->id, 'name' => 'Nubank', 'closing_day' => 13, 'due_day' => 20]);
        $cardAccount = BankConnectionAccount::factory()->create(['bank_connection_id' => $this->connection->id, 'type' => 'CREDIT', 'subtype' => 'CREDIT_CARD', 'name' => 'platinum']);
        $this->octoberInTheLedger();
        // Installment 2/3 is in the ledger on the purchase anniversary; the bank dates it on the statement
        $installment = $this->ledger(['card_id' => $card->id, 'type' => 'expense', 'amount' => 161.12, 'description' => 'Mercado Livre (2/3)',
            'status' => 'pending', 'transaction_date' => '2026-11-20', 'installment_number' => 2, 'total_installments' => 3]);
        foreach (range(20, 28) as $day) {
            $this->ledger(['card_id' => $card->id, 'type' => 'expense', 'amount' => 10, 'description' => 'Café', 'transaction_date' => "2026-10-{$day}"]);
            $this->bankTx($cardAccount, "2026-10-{$day}", 10, 'out', 'Cafe');
        }
        // The bank tells the bill of each charge: installment 2/3 already went to December's
        $bankInstallment = $this->bankTx($cardAccount, '2026-11-13', 161.12, 'out', 'Mercadolivre*Mercadol 2/3', ['installment_number' => 2, 'total_installments' => 3, 'payload' => self::bill('2026-12')]);
        $newPurchase = $this->bankTx($cardAccount, '2026-11-04', 30.01, 'out', 'Mercadolivre*Sosondas 1/5', ['installment_number' => 1, 'total_installments' => 5, 'payload' => self::bill('2026-11')]);
        $spotify = $this->ledger(['card_id' => $card->id, 'type' => 'expense', 'amount' => 23.90, 'description' => 'Spotify', 'transaction_date' => '2026-11-12']);
        $bankSpotify = $this->bankTx($cardAccount, '2026-11-12', 23.90, 'out', 'Dl*Spotify P4921', ['payload' => self::bill('2026-12')]);
        $received = $this->bankTx($cardAccount, '2026-11-15', 1407.22, 'in', 'Pagamento recebido');
        $paid = $this->bankTx($this->checking, '2026-11-15', 1407.22, 'out', 'Pagamento de fatura');

        $this->link($this->checking)->assertOk();
        $this->link($cardAccount, ['credit_card_id' => $card->id])->assertOk();

        $this->assertSame([BankTx::MATCHED, $installment->id], [$bankInstallment->fresh()->review_status, $bankInstallment->fresh()->transaction_id]);
        // Matched charges follow the bank's bill, not the app's closing day (12/11 would be November's)
        $this->assertSame([BankTx::MATCHED, '2026-12'], [$bankSpotify->fresh()->review_status, $spotify->fresh()->statement_month]);
        $this->assertSame('2026-12', $installment->fresh()->statement_month);
        $items = collect($this->getJson('/api/v1/finance/bank-connections/review')->json('data.items'))->keyBy('id');
        $this->assertSame(['pay_card', $card->id], [$items[$paid->id]['suggestion']['action'], $items[$paid->id]['suggestion']['card_id']]);
        $this->assertSame('pay_card', $items[$received->id]['suggestion']['action']);

        // Paying from the account side; the card side is then recognized, not paid twice
        $this->review([
            ['id' => $paid->id, 'action' => 'pay_card', 'card_id' => $card->id],
            ['id' => $newPurchase->id, 'action' => 'expense', 'description' => 'Sonda', 'category_id' => $this->food->id],
            ['id' => $received->id, 'action' => 'pay_card'],
        ])->assertOk()->assertJsonPath('data.applied', 3);

        $payment = Transaction::find($paid->fresh()->transaction_id);
        $this->assertSame(['transfer', 'card_payment', '2026-11', $card->id, $this->nubank->id], [
            $payment->type->value, $payment->economic_kind, $payment->statement_month, $payment->card_id, $payment->account_id,
        ]);
        $this->assertSame([BankTx::MATCHED, $payment->id], [$received->fresh()->review_status, $received->fresh()->transaction_id]);
        $this->assertSame(1, Transaction::where('economic_kind', 'card_payment')->count());

        $purchase = Transaction::find($newPurchase->fresh()->transaction_id);
        $this->assertSame(['Sonda (1/5)', $card->id, null, '30.01', '2026-11'], [$purchase->description, $purchase->card_id, $purchase->account_id, $purchase->amount, $purchase->statement_month]);
        // The payment keeps the bill it settles
        $this->assertSame('2026-11', $payment->fresh()->statement_month);
    }

    public function test_a_card_subscription_already_in_the_app_is_linked_not_duplicated(): void
    {
        $card = CreditCard::factory()->create(['user_id' => $this->user->id, 'bank_account_id' => $this->nubank->id, 'closing_day' => 13, 'due_day' => 20]);
        $cardAccount = BankConnectionAccount::factory()->create(['bank_connection_id' => $this->connection->id, 'type' => 'CREDIT', 'subtype' => 'CREDIT_CARD']);
        // The series occurrence (with IOF) realized on the 14th; the bank charged on the 6th, IOF apart
        $series = $this->ledger(['card_id' => $card->id, 'type' => 'expense', 'amount' => 107.57, 'description' => 'OpenAI (ChatGPT) + IOF',
            'transaction_date' => '2026-11-14', 'is_recurring' => true, 'recurrence_group_id' => (string) Str::uuid()]);
        $charge = $this->bankTx($cardAccount, '2026-11-06', 103.90, 'out', 'Openai *Chatgpt Subscr', ['payload' => self::bill('2026-11')]);
        $next = $this->ledger(['card_id' => $card->id, 'type' => 'expense', 'amount' => 107.57, 'description' => 'OpenAI (ChatGPT) + IOF', 'status' => 'pending',
            'transaction_date' => '2026-12-14', 'is_recurring' => true, 'recurrence_group_id' => $series->recurrence_group_id]);
        $this->link($cardAccount, ['credit_card_id' => $card->id, 'import_from' => '2026-11-01'])->assertOk();

        $suggestion = $this->getJson('/api/v1/finance/bank-connections/review')->json('data.items.0.suggestion');
        $this->assertSame(['match', $series->id], [$suggestion['action'], $suggestion['transaction_id']]);

        $this->review([['id' => $charge->id, 'action' => 'match', 'transaction_id' => $series->id]])->assertOk()->assertJsonPath('data.applied', 1);
        $this->assertSame([BankTx::MATCHED, $series->id], [$charge->fresh()->review_status, $charge->fresh()->transaction_id]);
        $this->assertSame(2, Transaction::count());
        // It takes what the bank charged, on the day and in the bill the bank says; the next forecast is untouched
        $this->assertSame(['103.90', '2026-11-06', '2026-11'], [$series->fresh()->amount, $series->fresh()->transaction_date->toDateString(), $series->fresh()->statement_month]);
        $this->assertSame(['107.57', 'pending', null], [$next->fresh()->amount, $next->fresh()->status->value, $next->fresh()->statement_month]);
    }

    public function test_linking_a_card_forecast_that_has_not_realized_yet_confirms_it_with_the_bank(): void
    {
        $card = CreditCard::factory()->create(['user_id' => $this->user->id, 'bank_account_id' => $this->nubank->id, 'closing_day' => 11, 'due_day' => 20]);
        $cardAccount = BankConnectionAccount::factory()->create(['bank_connection_id' => $this->connection->id, 'type' => 'CREDIT', 'subtype' => 'CREDIT_CARD']);
        $installment = $this->ledger(['card_id' => $card->id, 'type' => 'expense', 'amount' => 173.40, 'description' => 'Nova Era - Parcela 3/4', 'status' => 'pending',
            'transaction_date' => '2026-12-07', 'is_recurring' => true, 'recurrence_group_id' => (string) Str::uuid()]);
        $charge = $this->bankTx($cardAccount, '2026-11-13', 173.40, 'out', 'Nova Era', ['payload' => self::bill('2026-12')]);
        $this->link($cardAccount, ['credit_card_id' => $card->id, 'import_from' => '2026-11-01'])->assertOk();

        $this->review([['id' => $charge->id, 'action' => 'match', 'transaction_id' => $installment->id]])->assertOk()->assertJsonPath('data.applied', 1);
        $this->assertSame(['confirmed', '2026-11-13', '2026-12'], [$installment->fresh()->status->value, $installment->fresh()->transaction_date->toDateString(), $installment->fresh()->statement_month]);
        $this->assertSame('500.00', $this->nubank->fresh()->balance);
    }

    public function test_records_created_from_the_bank_are_locked_and_can_be_undone_from_review(): void
    {
        $this->octoberInTheLedger();
        $friend = $this->bankTx($this->checking, '2026-11-05', 30, 'out', 'Transferência enviada pelo Pix|JAQUELINE DA SILVA FARIA');
        $this->link($this->checking)->assertOk();
        $this->review([['id' => $friend->id, 'action' => 'expense', 'description' => 'Jantinha', 'category_id' => $this->food->id]])->assertOk();
        $created = Transaction::find($friend->fresh()->transaction_id);

        $this->getJson('/api/v1/finance/transactions?per_page=50')->assertOk();
        $listed = collect($this->getJson('/api/v1/finance/transactions?per_page=50')->json('data'))->firstWhere('id', $created->id);
        $this->assertTrue($listed['recovery_locked']);

        $body = ['type' => 'expense', 'description' => 'Jantinha', 'transaction_date' => '2026-11-05', 'account_id' => $this->nubank->id];
        $this->putJson("/api/v1/finance/transactions/{$created->id}", $body + ['amount' => 31])->assertUnprocessable();
        $other = TransactionCategory::factory()->create(['user_id' => $this->user->id, 'type' => 'expense']);
        $this->putJson("/api/v1/finance/transactions/{$created->id}", $body + ['amount' => 30, 'category_id' => $other->id])->assertOk();
        $this->deleteJson("/api/v1/finance/transactions/{$created->id}")->assertUnprocessable();

        $this->postJson("/api/v1/finance/bank-connections/review/{$friend->id}/undo")->assertOk()->assertJsonPath('data.review_status', BankTx::NEW);
        $this->assertSoftDeleted('transactions', ['id' => $created->id]);
        $this->assertSame('500.00', $this->nubank->fresh()->balance);
    }

    public function test_ignoring_and_taking_it_back(): void
    {
        $this->octoberInTheLedger();
        $fee = $this->bankTx($this->checking, '2026-11-03', 1, 'out', 'Transferência enviada pelo Pix|LIVEPIX LTDA');
        $this->link($this->checking)->assertOk();

        $this->review([['id' => $fee->id, 'action' => 'ignore']])->assertOk()->assertJsonPath('data.remaining', 0);
        $this->assertSame(BankTx::IGNORED, $fee->fresh()->review_status);
        $this->review([['id' => $fee->id, 'action' => 'expense']])->assertOk()->assertJsonPath('data.applied', 0)->assertJsonPath('data.skipped.0.id', $fee->id);

        $this->postJson("/api/v1/finance/bank-connections/review/{$fee->id}/undo")->assertOk()->assertJsonPath('data.review_status', BankTx::NEW);
        $this->assertSame(9, Transaction::count());
    }

    public function test_deleting_a_matched_ledger_record_sends_the_bank_transaction_back_to_review(): void
    {
        $this->octoberInTheLedger();
        $this->link($this->checking, ['import_from' => '2026-10-20'])->assertOk();
        $matched = BankTx::where('review_status', BankTx::MATCHED)->orderBy('date')->firstOrFail();

        $this->deleteJson("/api/v1/finance/transactions/{$matched->transaction_id}")->assertNoContent();
        app(BankReconciler::class)->reconcile($this->checking->fresh());

        $this->assertSame([BankTx::NEW, null], [$matched->fresh()->review_status, $matched->fresh()->transaction_id]);
    }

    public function test_invalid_choices_are_refused_and_nothing_is_written(): void
    {
        $this->octoberInTheLedger();
        $tx = $this->bankTx($this->checking, '2026-11-03', 56, 'out', 'Transferência enviada pelo Pix|THAIS');
        $this->link($this->checking)->assertOk();
        $income = TransactionCategory::factory()->create(['user_id' => $this->user->id, 'type' => 'income']);

        $this->review([['id' => $tx->id, 'action' => 'teleport']])->assertUnprocessable();
        $this->review([['id' => $tx->id, 'action' => 'expense', 'category_id' => $income->id]])->assertOk()
            ->assertJsonPath('data.applied', 0)->assertJsonPath('data.skipped.0.message', 'Escolha uma categoria de despesa.');
        $this->review([['id' => $tx->id, 'action' => 'transfer', 'account_id' => $this->nubank->id]])->assertOk()->assertJsonPath('data.applied', 0);
        $this->assertSame(BankTx::NEW, $tx->fresh()->review_status);
        $this->assertSame(9, Transaction::count());
    }

    public function test_another_user_cannot_review_or_link(): void
    {
        $this->octoberInTheLedger();
        $tx = $this->bankTx($this->checking, '2026-11-03', 56, 'out', 'Transferência enviada pelo Pix|THAIS');
        $this->link($this->checking)->assertOk();
        $other = User::factory()->create();
        $otherAccount = BankAccount::factory()->create(['user_id' => $other->id]);

        $this->actingAs($other, 'sanctum');
        $this->getJson('/api/v1/finance/bank-connections/review')->assertOk()->assertJsonPath('data.count', 0);
        $this->review([['id' => $tx->id, 'action' => 'ignore']])->assertOk()->assertJsonPath('data.applied', 0);
        $this->patchJson("/api/v1/finance/bank-connections/accounts/{$this->checking->id}", ['bank_account_id' => $otherAccount->id])->assertForbidden();
        // Their own bank account cannot be linked to someone else's ledger account
        $theirs = BankConnectionAccount::factory()->create(['bank_connection_id' => BankConnection::factory()->create(['user_id' => $other->id])->id]);
        $this->patchJson("/api/v1/finance/bank-connections/accounts/{$theirs->id}", ['bank_account_id' => $this->nubank->id])->assertUnprocessable();
        $this->postJson("/api/v1/finance/bank-connections/review/{$tx->id}/undo")->assertForbidden();
        $this->assertSame(BankTx::NEW, $tx->fresh()->review_status);
    }

    public function test_same_value_from_two_people_and_two_lunches_in_one_pix_link_to_the_right_records(): void
    {
        $this->octoberInTheLedger();
        $peterson = $this->ledger(['account_id' => $this->nubank->id, 'type' => 'income', 'amount' => 21, 'description' => 'Reembolso Peterson almoço', 'transaction_date' => '2026-10-28']);
        $luyny = $this->ledger(['account_id' => $this->nubank->id, 'type' => 'income', 'amount' => 21, 'description' => 'Reembolso Luyny almoço', 'transaction_date' => '2026-10-28']);
        $luynyBefore = $this->ledger(['account_id' => $this->nubank->id, 'type' => 'income', 'amount' => 20.75, 'description' => 'Reembolso Luyny almoço 27/10', 'transaction_date' => '2026-10-28']);
        // Luyny pays two lunches in one Pix; Peterson pays one. Bank order puts Luyny's 21.00-like value first.
        $fromLuyny = $this->bankTx($this->checking, '2026-10-28', 41.75, 'in', 'Transferência Recebida|LUYNY FERNANDES FARIA');
        $fromPeterson = $this->bankTx($this->checking, '2026-10-28', 21, 'in', 'Transferência Recebida|PETERSON ALVES GERVAZIO');

        $this->link($this->checking, ['import_from' => '2026-10-20'])->assertOk()->assertJsonPath('data.accounts.0.to_review_count', 0);

        $this->assertSame($peterson->id, $fromPeterson->fresh()->transaction_id);
        $this->assertContains($fromLuyny->fresh()->transaction_id, [$luyny->id, $luynyBefore->id]);

        // Next Pix from Peterson: described as his reimbursement, not someone else's
        $next = $this->bankTx($this->checking, '2026-11-05', 21, 'in', 'Transferência Recebida|PETERSON ALVES GERVAZIO');
        app(BankReconciler::class)->reconcile($this->checking->fresh());
        $suggestion = collect($this->getJson('/api/v1/finance/bank-connections/review')->json('data.items'))->firstWhere('id', $next->id)['suggestion'];
        $this->assertSame(['income', 'Reembolso Peterson almoço'], [$suggestion['action'], $suggestion['description']]);
        $this->getJson('/api/v1/finance/bank-connections/status')->assertOk()->assertJsonPath('data.to_review_count', 1);
    }

    public function test_a_shared_lunch_suggests_the_users_part_and_only_that_counts_as_spending(): void
    {
        foreach (range(20, 28) as $day) {
            $lunch = $this->ledger(['account_id' => $this->nubank->id, 'type' => 'expense', 'amount' => 83, 'description' => 'Almoço compartilhado, 4 pessoas', 'category_id' => $this->food->id, 'transaction_date' => "2026-10-{$day}"]);
            $lunch->forceFill(['economic_kind' => 'shared_expense', 'economic_expense_cents' => 2075, 'economic_income_cents' => 0])->save();
            $this->bankTx($this->checking, "2026-10-{$day}", 83, 'out', 'Transferência enviada pelo Pix|SAFE2PAY INTERMEDIACAO DE NEG');
        }
        $today = $this->bankTx($this->checking, '2026-11-04', 63, 'out', 'Transferência enviada pelo Pix|SAFE2PAY INTERMEDIACAO DE NEG');
        $this->link($this->checking)->assertOk();

        $suggestion = $this->getJson('/api/v1/finance/bank-connections/review')->json('data.items.0.suggestion');
        $this->assertSame(['Almoço compartilhado, 4 pessoas', 1575], [$suggestion['description'], $suggestion['own_share_cents']]);

        $this->review([['id' => $today->id, 'action' => 'expense', 'description' => 'Almoço compartilhado, 3 pessoas', 'category_id' => $this->food->id, 'own_share_cents' => 2100]])
            ->assertOk()->assertJsonPath('data.applied', 1);
        $created = Transaction::find($today->fresh()->transaction_id);
        $this->assertSame(['shared_expense', 2100, 0, '63.00'], [$created->economic_kind, (int) $created->economic_expense_cents, (int) $created->economic_income_cents, $created->amount]);
        $this->assertEqualsWithDelta(0.3333, BankReviewRule::where('match_key', 'SAFE PAY INTERMEDIAC')->value('own_share_ratio'), 0.0001);
    }

    public function test_two_bank_accounts_cannot_share_one_ledger_account(): void
    {
        $this->link($this->checking)->assertOk();
        $twin = BankConnectionAccount::factory()->create(['bank_connection_id' => $this->connection->id]);
        $this->link($twin)->assertUnprocessable()->assertJsonValidationErrors('bank_account_id');
    }
}
