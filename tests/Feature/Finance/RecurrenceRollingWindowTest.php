<?php

namespace Tests\Feature\Finance;

use App\Domains\Finance\Actions\CreateTransactionAction;
use App\Domains\Finance\Actions\ExtendRecurringTransactionsAction;
use App\Domains\Finance\Models\BankAccount;
use App\Domains\Finance\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RecurrenceRollingWindowTest extends TestCase
{
    use RefreshDatabase;

    private function createRecurring(User $user, BankAccount $account, array $config): void
    {
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/finance/transactions', [
                'type' => 'expense',
                'amount' => 100,
                'description' => 'Assinatura',
                'transaction_date' => now()->toDateString(),
                'account_id' => $account->id,
                'is_recurring' => true,
                'recurrence_config' => $config,
            ])
            ->assertCreated();
    }

    public function test_never_ending_series_materializes_only_within_horizon(): void
    {
        $user = User::factory()->create();
        $account = BankAccount::factory()->create(['user_id' => $user->id]);

        $this->createRecurring($user, $account, ['frequency' => 'monthly', 'end_type' => 'never']);

        $horizon = now()->addMonthsNoOverflow(CreateTransactionAction::HORIZON_MONTHS)->toDateString();
        $count = Transaction::where('description', 'Assinatura')->count();
        $beyond = Transaction::where('description', 'Assinatura')
            ->where('transaction_date', '>', $horizon)
            ->count();

        // ~13 occurrences (today + 12 months), never the old 60
        $this->assertLessThanOrEqual(CreateTransactionAction::HORIZON_MONTHS + 1, $count);
        $this->assertGreaterThan(1, $count);
        $this->assertSame(0, $beyond);
    }

    public function test_extend_action_appends_next_occurrences_as_time_advances(): void
    {
        $user = User::factory()->create();
        $account = BankAccount::factory()->create(['user_id' => $user->id]);

        $this->createRecurring($user, $account, ['frequency' => 'monthly', 'end_type' => 'never']);
        $before = Transaction::where('description', 'Assinatura')->count();

        // A month from now, the window uncovers one more occurrence
        $this->travel(1)->months();
        app(ExtendRecurringTransactionsAction::class)->executeForUser($user->id);

        $after = Transaction::where('description', 'Assinatura')->count();
        $this->assertGreaterThan($before, $after);
        $this->travelBack();
    }

    public function test_count_based_series_never_exceeds_its_total(): void
    {
        $user = User::factory()->create();
        $account = BankAccount::factory()->create(['user_id' => $user->id]);

        $this->createRecurring($user, $account, ['frequency' => 'monthly', 'end_type' => 'count', 'count' => 3]);
        $this->assertSame(3, Transaction::where('description', 'Assinatura')->count());

        // Even after extending far in the future, total stays at 3
        $this->travel(6)->months();
        app(ExtendRecurringTransactionsAction::class)->executeForUser($user->id);
        $this->assertSame(3, Transaction::where('description', 'Assinatura')->count());
        $this->travelBack();
    }

    public function test_prune_removes_legacy_pending_rows_beyond_horizon(): void
    {
        $user = User::factory()->create();
        $account = BankAccount::factory()->create(['user_id' => $user->id]);
        $groupId = (string) Str::uuid();

        // Simulate legacy data: 24 monthly pendings (12 beyond the horizon)
        for ($i = 0; $i < 24; $i++) {
            Transaction::factory()->create([
                'user_id' => $user->id,
                'account_id' => $account->id,
                'description' => 'Legado',
                'status' => $i === 0 ? 'confirmed' : 'pending',
                'is_recurring' => true,
                'recurrence_group_id' => $groupId,
                'recurrence_config' => ['frequency' => 'monthly', 'end_type' => 'never'],
                'transaction_date' => now()->addMonthsNoOverflow($i)->toDateString(),
            ]);
        }

        $pruned = app(ExtendRecurringTransactionsAction::class)->pruneForUser($user->id);

        $this->assertGreaterThan(0, $pruned);
        $horizon = now()->addMonthsNoOverflow(CreateTransactionAction::HORIZON_MONTHS)->toDateString();
        $this->assertSame(0, Transaction::where('description', 'Legado')
            ->where('transaction_date', '>', $horizon)->count());
        // Confirmed row untouched
        $this->assertSame(1, Transaction::where('description', 'Legado')->where('status', 'confirmed')->count());
    }

    public function test_fully_deleted_series_is_not_resurrected(): void
    {
        $user = User::factory()->create();
        $account = BankAccount::factory()->create(['user_id' => $user->id]);

        $this->createRecurring($user, $account, ['frequency' => 'monthly', 'end_type' => 'never']);
        Transaction::where('description', 'Assinatura')->get()->each->delete(); // soft delete all

        $this->travel(2)->months();
        $created = app(ExtendRecurringTransactionsAction::class)->executeForUser($user->id);

        $this->assertSame(0, $created);
        $this->travelBack();
    }

    public function test_confirm_batch_confirms_only_own_transactions_and_updates_balance(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $account = BankAccount::factory()->create(['user_id' => $user->id, 'balance' => 1000]);
        $otherAccount = BankAccount::factory()->create(['user_id' => $other->id, 'balance' => 1000]);

        $mine = Transaction::factory()->count(2)->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'type' => 'expense',
            'amount' => 100,
            'status' => 'pending',
        ]);
        $foreign = Transaction::factory()->create([
            'user_id' => $other->id,
            'account_id' => $otherAccount->id,
            'type' => 'expense',
            'amount' => 100,
            'status' => 'pending',
        ]);

        $ids = $mine->pluck('id')->push($foreign->id)->all();

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/finance/transactions/confirm-batch', ['ids' => $ids])
            ->assertOk();

        $this->assertCount(2, $response->json('data'));
        $this->assertSame(800.0, (float) $account->fresh()->balance);
        $this->assertSame('pending', $foreign->fresh()->status->value);
    }
}
