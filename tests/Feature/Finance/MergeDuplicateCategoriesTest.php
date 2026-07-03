<?php

namespace Tests\Feature\Finance;

use App\Domains\Finance\Models\Transaction;
use App\Domains\Finance\Models\TransactionCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MergeDuplicateCategoriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_merges_duplicates_repointing_transactions_to_most_used(): void
    {
        $user = User::factory()->create();
        $keeper = TransactionCategory::factory()->create(['user_id' => $user->id, 'name' => 'Lazer', 'type' => 'expense']);
        $dupe = TransactionCategory::factory()->create(['user_id' => $user->id, 'name' => 'Lazer', 'type' => 'expense', 'monthly_limit' => 300]);

        Transaction::factory()->count(3)->create(['user_id' => $user->id, 'category_id' => $keeper->id]);
        Transaction::factory()->create(['user_id' => $user->id, 'category_id' => $dupe->id]);

        $this->artisan('finance:merge-duplicate-categories')->assertSuccessful();

        $this->assertSoftDeleted('transaction_categories', ['id' => $dupe->id]);
        $this->assertSame(4, Transaction::where('category_id', $keeper->id)->count());
        // Limit from the duplicate is preserved on the keeper
        $this->assertSame(300.0, (float) $keeper->fresh()->monthly_limit);
    }

    public function test_dry_run_changes_nothing(): void
    {
        $user = User::factory()->create();
        TransactionCategory::factory()->create(['user_id' => $user->id, 'name' => 'Roupas', 'type' => 'expense']);
        TransactionCategory::factory()->create(['user_id' => $user->id, 'name' => 'Roupas', 'type' => 'expense']);

        $this->artisan('finance:merge-duplicate-categories --dry-run')->assertSuccessful();

        $this->assertSame(2, TransactionCategory::where('name', 'Roupas')->count());
    }

    public function test_same_name_for_different_users_is_not_merged(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        TransactionCategory::factory()->create(['user_id' => $a->id, 'name' => 'Moradia', 'type' => 'expense']);
        TransactionCategory::factory()->create(['user_id' => $b->id, 'name' => 'Moradia', 'type' => 'expense']);

        $this->artisan('finance:merge-duplicate-categories')->assertSuccessful();

        $this->assertSame(2, TransactionCategory::where('name', 'Moradia')->count());
    }
}
