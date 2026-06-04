<?php

namespace App\Providers;

use App\Domains\Bookmarks\Models\Bookmark;
use App\Domains\Bookmarks\Models\BookmarkCollection;
use App\Domains\Bookmarks\Policies\BookmarkCollectionPolicy;
use App\Domains\Bookmarks\Policies\BookmarkPolicy;
use App\Domains\Calendar\Models\CalendarEvent;
use App\Domains\Calendar\Policies\CalendarEventPolicy;
use App\Domains\Finance\Models\BankAccount;
use App\Domains\Finance\Models\Budget;
use App\Domains\Finance\Models\CreditCard;
use App\Domains\Finance\Models\FinanceGoal;
use App\Domains\Finance\Models\Transaction;
use App\Domains\Finance\Models\TransactionCategory;
use App\Domains\Finance\Models\TransactionTag;
use App\Domains\Finance\Policies\BankAccountPolicy;
use App\Domains\Finance\Policies\BudgetPolicy;
use App\Domains\Finance\Policies\CreditCardPolicy;
use App\Domains\Finance\Policies\FinanceGoalPolicy;
use App\Domains\Finance\Policies\TransactionCategoryPolicy;
use App\Domains\Finance\Policies\TransactionPolicy;
use App\Domains\Finance\Policies\TransactionTagPolicy;
use App\Domains\Goals\Models\Goal;
use App\Domains\Goals\Policies\GoalPolicy;
use App\Domains\Habits\Models\Habit;
use App\Domains\Habits\Policies\HabitPolicy;
use App\Domains\Notes\Models\Note;
use App\Domains\Notes\Models\NoteTag;
use App\Domains\Notes\Policies\NotePolicy;
use App\Domains\Notes\Policies\NoteTagPolicy;
use App\Domains\Purchases\Models\PurchaseItem;
use App\Domains\Purchases\Models\ShoppingItem;
use App\Domains\Purchases\Models\ShoppingSession;
use App\Domains\Purchases\Policies\PurchaseItemPolicy;
use App\Domains\Purchases\Policies\ShoppingItemPolicy;
use App\Domains\Purchases\Policies\ShoppingSessionPolicy;
use App\Domains\Tasks\Models\Task;
use App\Domains\Tasks\Models\TaskLabel;
use App\Domains\Tasks\Policies\TaskLabelPolicy;
use App\Domains\Tasks\Policies\TaskPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Gate::policy(BookmarkCollection::class, BookmarkCollectionPolicy::class);
        Gate::policy(Bookmark::class, BookmarkPolicy::class);
        Gate::policy(Task::class, TaskPolicy::class);
        Gate::policy(TaskLabel::class, TaskLabelPolicy::class);
        Gate::policy(Habit::class, HabitPolicy::class);
        Gate::policy(BankAccount::class, BankAccountPolicy::class);
        Gate::policy(Budget::class, BudgetPolicy::class);
        Gate::policy(CreditCard::class, CreditCardPolicy::class);
        Gate::policy(FinanceGoal::class, FinanceGoalPolicy::class);
        Gate::policy(Transaction::class, TransactionPolicy::class);
        Gate::policy(TransactionCategory::class, TransactionCategoryPolicy::class);
        Gate::policy(TransactionTag::class, TransactionTagPolicy::class);
        Gate::policy(Goal::class, GoalPolicy::class);
        Gate::policy(CalendarEvent::class, CalendarEventPolicy::class);
        Gate::policy(Note::class, NotePolicy::class);
        Gate::policy(NoteTag::class, NoteTagPolicy::class);
        Gate::policy(PurchaseItem::class, PurchaseItemPolicy::class);
        Gate::policy(ShoppingSession::class, ShoppingSessionPolicy::class);
        Gate::policy(ShoppingItem::class, ShoppingItemPolicy::class);
    }
}
