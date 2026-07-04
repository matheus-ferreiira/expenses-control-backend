<?php

use App\Domains\Auth\Controllers\AuthController;
use App\Domains\Bookmarks\Controllers\BookmarkCollectionController;
use App\Domains\Bookmarks\Controllers\BookmarkController;
use App\Domains\Calendar\Controllers\CalendarEventController;
use App\Domains\Finance\Controllers\BankAccountController;
use App\Domains\Finance\Controllers\BudgetController;
use App\Domains\Finance\Controllers\CreditCardController;
use App\Domains\Finance\Controllers\FinanceGoalController;
use App\Domains\Finance\Controllers\FinanceReportController;
use App\Domains\Finance\Controllers\TransactionCategoryController;
use App\Domains\Finance\Controllers\TransactionController;
use App\Domains\Finance\Controllers\TransactionTagController;
use App\Domains\Goals\Controllers\GoalController;
use App\Domains\Habits\Controllers\HabitController;
use App\Domains\Notes\Controllers\NoteController;
use App\Domains\Notes\Controllers\NoteTagController;
use App\Domains\Prices\Controllers\PriceCategoryController;
use App\Domains\Prices\Controllers\PriceProductController;
use App\Domains\Prices\Controllers\PricePurchaseController;
use App\Domains\Prices\Controllers\PriceRecordController;
use App\Domains\Prices\Controllers\PriceReportController;
use App\Domains\Prices\Controllers\PriceSaleController;
use App\Domains\Prices\Controllers\PriceStoreController;
use App\Domains\Purchases\Controllers\PurchaseItemController;
use App\Domains\Purchases\Controllers\ShoppingItemController;
use App\Domains\Purchases\Controllers\ShoppingSessionController;
use App\Domains\Reports\Controllers\DashboardController;
use App\Domains\Reports\Controllers\ReportController;
use App\Domains\Tasks\Controllers\SubtaskController;
use App\Domains\Tasks\Controllers\TaskController;
use App\Domains\Tasks\Controllers\TaskLabelController;
use App\Domains\Tasks\Controllers\TaskListController;
use App\Domains\Tasks\Controllers\TaskTagController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // Google OAuth — no throttle, stateless redirect
    Route::prefix('auth')->group(function () {
        Route::get('google/redirect', [AuthController::class, 'googleRedirect']);
        Route::get('google/callback', [AuthController::class, 'googleCallback']);
    });

    // Auth - public routes
    Route::prefix('auth')->middleware('throttle:10,1')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
        Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('reset-password', [AuthController::class, 'resetPassword']);
    });

    // Authenticated routes
    Route::middleware('auth:sanctum')->group(function () {

        // Auth
        Route::prefix('auth')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('logout-all', [AuthController::class, 'logoutAll']);
            Route::get('me', [AuthController::class, 'me']);
            Route::patch('profile', [AuthController::class, 'updateProfile']);
            Route::patch('password', [AuthController::class, 'updatePassword']);
            Route::patch('settings', [AuthController::class, 'updateSettings']);
            Route::delete('reset-data', [AuthController::class, 'resetData']);
        });

        // Dashboard
        Route::get('dashboard', [DashboardController::class, 'index']);

        // Tasks
        Route::prefix('tasks')->group(function () {
            Route::get('/', [TaskController::class, 'index']);
            Route::post('/', [TaskController::class, 'store']);
            Route::post('reorder', [TaskController::class, 'reorder']);
            Route::get('{task}', [TaskController::class, 'show']);
            Route::put('{task}', [TaskController::class, 'update']);
            Route::delete('{task}', [TaskController::class, 'destroy']);
            Route::patch('{task}/complete', [TaskController::class, 'complete']);
            Route::patch('{task}/archive', [TaskController::class, 'archive']);
            Route::patch('{task}/unarchive', [TaskController::class, 'unarchive']);
            Route::get('{task}/recurrence-history', [TaskController::class, 'recurrenceHistory']);

            // Subtasks
            Route::post('{task}/subtasks', [SubtaskController::class, 'store']);
            Route::put('{task}/subtasks/{subtask}', [SubtaskController::class, 'update']);
            Route::delete('{task}/subtasks/{subtask}', [SubtaskController::class, 'destroy']);
        });

        // Task Labels
        Route::apiResource('task-labels', TaskLabelController::class)->except(['show']);

        // Task Lists
        Route::apiResource('task-lists', TaskListController::class)->except(['show']);

        // Task Tags
        Route::apiResource('task-tags', TaskTagController::class)->except(['show']);

        // Habits
        Route::prefix('habits')->group(function () {
            Route::get('/', [HabitController::class, 'index']);
            Route::post('/', [HabitController::class, 'store']);
            Route::get('today', [HabitController::class, 'today']);
            Route::get('{habit}', [HabitController::class, 'show']);
            Route::put('{habit}', [HabitController::class, 'update']);
            Route::delete('{habit}', [HabitController::class, 'destroy']);
            Route::patch('{habit}/archive', [HabitController::class, 'archive']);
            Route::patch('{habit}/unarchive', [HabitController::class, 'unarchive']);
            Route::post('{habit}/log', [HabitController::class, 'log']);
            Route::delete('{habit}/log', [HabitController::class, 'unlog']);
            Route::get('{habit}/stats', [HabitController::class, 'stats']);
            Route::get('{habit}/heatmap', [HabitController::class, 'heatmap']);
        });

        // Finance - Bank Accounts
        Route::prefix('finance')->group(function () {
            Route::get('balance/historical', [BankAccountController::class, 'historicalBalance']);
            Route::get('balance', [BankAccountController::class, 'consolidatedBalance']);

            Route::apiResource('accounts', BankAccountController::class);

            // Credit Cards (nested under accounts)
            Route::post('accounts/{bankAccount}/cards', [CreditCardController::class, 'store']);

            // Credit Cards standalone
            Route::get('cards', [CreditCardController::class, 'index']);
            Route::post('cards', [CreditCardController::class, 'storeStandalone']);
            Route::get('cards/{creditCard}', [CreditCardController::class, 'show']);
            Route::post('cards/{creditCard}/pay-statement', [CreditCardController::class, 'payStatement']);
            Route::get('cards/{creditCard}/statement-payment', [CreditCardController::class, 'statementPayment']);
            Route::put('cards/{creditCard}', [CreditCardController::class, 'update']);
            Route::patch('cards/{creditCard}', [CreditCardController::class, 'update']);
            Route::delete('cards/{creditCard}', [CreditCardController::class, 'destroy']);

            // Transactions
            Route::patch('transactions/confirm-batch', [TransactionController::class, 'confirmBatch']);
            Route::apiResource('transactions', TransactionController::class);
            Route::patch('transactions/{transaction}/confirm', [TransactionController::class, 'confirm']);

            // Categories
            Route::get('categories', [TransactionCategoryController::class, 'index']);
            Route::post('categories', [TransactionCategoryController::class, 'store']);
            Route::put('categories/{transactionCategory}', [TransactionCategoryController::class, 'update']);
            Route::delete('categories/{transactionCategory}', [TransactionCategoryController::class, 'destroy']);

            // Transaction Tags
            Route::get('tags', [TransactionTagController::class, 'index']);
            Route::post('tags', [TransactionTagController::class, 'store']);
            Route::put('tags/{transactionTag}', [TransactionTagController::class, 'update']);
            Route::delete('tags/{transactionTag}', [TransactionTagController::class, 'destroy']);

            // Reports
            Route::prefix('reports')->group(function () {
                Route::get('monthly', [FinanceReportController::class, 'monthlySummary']);
                Route::get('yearly', [FinanceReportController::class, 'yearlySummary']);
                Route::get('cashflow', [FinanceReportController::class, 'cashFlow']);
                Route::get('balance', [FinanceReportController::class, 'consolidatedBalance']);
            });

            // Budgets
            Route::prefix('budgets')->group(function () {
                Route::get('previous', [BudgetController::class, 'previous']);
                Route::get('/', [BudgetController::class, 'index']);
                Route::post('/', [BudgetController::class, 'store']);
                Route::put('{budget}', [BudgetController::class, 'update']);
                Route::delete('{budget}', [BudgetController::class, 'destroy']);
            });

            // Finance Goals
            Route::prefix('goals')->group(function () {
                Route::get('/', [FinanceGoalController::class, 'index']);
                Route::post('/', [FinanceGoalController::class, 'store']);
                Route::put('{financeGoal}', [FinanceGoalController::class, 'update']);
                Route::post('{financeGoal}/complete', [FinanceGoalController::class, 'complete']);
                Route::delete('{financeGoal}', [FinanceGoalController::class, 'destroy']);
            });
        });

        // Goals
        Route::prefix('goals')->group(function () {
            Route::get('/', [GoalController::class, 'index']);
            Route::post('/', [GoalController::class, 'store']);
            Route::get('{goal}', [GoalController::class, 'show']);
            Route::put('{goal}', [GoalController::class, 'update']);
            Route::delete('{goal}', [GoalController::class, 'destroy']);
            Route::patch('{goal}/progress', [GoalController::class, 'updateProgress']);
        });

        // Calendar
        Route::prefix('calendar')->group(function () {
            Route::get('/', [CalendarEventController::class, 'index']);
            Route::post('/', [CalendarEventController::class, 'store']);
            Route::get('upcoming', [CalendarEventController::class, 'upcoming']);
            Route::get('{calendarEvent}', [CalendarEventController::class, 'show']);
            Route::put('{calendarEvent}', [CalendarEventController::class, 'update']);
            Route::delete('{calendarEvent}', [CalendarEventController::class, 'destroy']);
        });

        // Reports
        Route::prefix('reports')->group(function () {
            Route::get('habits-log-count', [ReportController::class, 'habitsLogCount']);
            Route::get('weekly-productivity', [ReportController::class, 'weeklyProductivity']);
        });

        // Notes
        Route::prefix('notes')->group(function () {
            Route::get('/', [NoteController::class, 'index']);
            Route::post('/', [NoteController::class, 'store']);
            Route::get('{note}', [NoteController::class, 'show']);
            Route::patch('{note}', [NoteController::class, 'update']);
            Route::delete('{note}', [NoteController::class, 'destroy']);
            Route::patch('{note}/pin', [NoteController::class, 'pin']);
            Route::patch('{note}/favorite', [NoteController::class, 'favorite']);
            Route::patch('{note}/archive', [NoteController::class, 'archive']);
            Route::patch('{note}/unarchive', [NoteController::class, 'unarchive']);
        });

        // Note Tags
        Route::prefix('note-tags')->group(function () {
            Route::get('/', [NoteTagController::class, 'index']);
            Route::post('/', [NoteTagController::class, 'store']);
            Route::put('{noteTag}', [NoteTagController::class, 'update']);
            Route::delete('{noteTag}', [NoteTagController::class, 'destroy']);
        });

        // Bookmarks
        Route::prefix('bookmarks')->group(function () {
            // Collections — reorder BEFORE parameterized route
            Route::post('collections/reorder', [BookmarkCollectionController::class, 'reorder']);
            Route::get('collections', [BookmarkCollectionController::class, 'index']);
            Route::post('collections', [BookmarkCollectionController::class, 'store']);
            Route::put('collections/{collection}', [BookmarkCollectionController::class, 'update']);
            Route::delete('collections/{collection}', [BookmarkCollectionController::class, 'destroy']);

            // Links within a collection — reorder BEFORE parameterized route
            Route::post('collections/{collection}/links/reorder', [BookmarkController::class, 'reorder']);
            Route::get('collections/{collection}/links', [BookmarkController::class, 'index']);
            Route::post('collections/{collection}/links', [BookmarkController::class, 'store']);
            Route::put('links/{bookmark}', [BookmarkController::class, 'update']);
            Route::delete('links/{bookmark}', [BookmarkController::class, 'destroy']);
            Route::patch('links/{bookmark}/favorite', [BookmarkController::class, 'toggleFavorite']);
        });

        // Price tracking
        Route::prefix('prices')->group(function () {
            Route::get('dashboard', [PriceReportController::class, 'dashboard']);
            Route::get('patrimony', [PriceReportController::class, 'patrimony']);
            Route::get('compare', [PriceReportController::class, 'compare']);
            Route::get('products/{product}/price-history', [PriceReportController::class, 'priceHistory']);
            Route::apiResource('categories', PriceCategoryController::class);
            Route::apiResource('stores', PriceStoreController::class);
            Route::apiResource('products', PriceProductController::class);
            Route::apiResource('price-records', PriceRecordController::class);
            Route::apiResource('purchases', PricePurchaseController::class)->names('prices.purchases');
            Route::apiResource('sales', PriceSaleController::class);
        });

        // Purchases (legacy checklist — preserved)
        Route::apiResource('purchases', PurchaseItemController::class)->except(['show']);

        // Shopping Sessions
        Route::prefix('shopping')->group(function () {
            Route::get('sessions', [ShoppingSessionController::class, 'index']);
            Route::post('sessions', [ShoppingSessionController::class, 'store']);
            Route::get('sessions/{session}', [ShoppingSessionController::class, 'show']);
            Route::post('sessions/{session}/finish', [ShoppingSessionController::class, 'finish']);
            Route::put('sessions/{session}', [ShoppingSessionController::class, 'update']);
            Route::patch('sessions/{session}/reopen', [ShoppingSessionController::class, 'reopen']);
            Route::delete('sessions/{session}', [ShoppingSessionController::class, 'destroy']);
            Route::get('items/frequent', [ShoppingItemController::class, 'frequent']);
            Route::post('sessions/{session}/items', [ShoppingItemController::class, 'store']);
            Route::put('items/{item}', [ShoppingItemController::class, 'update']);
            Route::delete('items/{item}', [ShoppingItemController::class, 'destroy']);
        });
    });
});
