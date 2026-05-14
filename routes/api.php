<?php

use App\Domains\Auth\Controllers\AuthController;
use App\Domains\Calendar\Controllers\CalendarEventController;
use App\Domains\Finance\Controllers\BankAccountController;
use App\Domains\Finance\Controllers\CreditCardController;
use App\Domains\Finance\Controllers\FinanceReportController;
use App\Domains\Finance\Controllers\TransactionCategoryController;
use App\Domains\Finance\Controllers\TransactionController;
use App\Domains\Goals\Controllers\GoalController;
use App\Domains\Habits\Controllers\HabitController;
use App\Domains\Notes\Controllers\NoteController;
use App\Domains\Notes\Controllers\NoteTagController;
use App\Domains\Purchases\Controllers\PurchaseItemController;
use App\Domains\Reports\Controllers\DashboardController;
use App\Domains\Reports\Controllers\ReportController;
use App\Domains\Tasks\Controllers\SubtaskController;
use App\Domains\Tasks\Controllers\TaskController;
use App\Domains\Tasks\Controllers\TaskLabelController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // Auth - public routes
    Route::prefix('auth')->group(function () {
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

            // Subtasks
            Route::post('{task}/subtasks', [SubtaskController::class, 'store']);
            Route::put('{task}/subtasks/{subtask}', [SubtaskController::class, 'update']);
            Route::delete('{task}/subtasks/{subtask}', [SubtaskController::class, 'destroy']);
        });

        // Task Labels
        Route::apiResource('task-labels', TaskLabelController::class)->except(['show']);

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
            Route::get('balance', [BankAccountController::class, 'consolidatedBalance']);

            Route::apiResource('accounts', BankAccountController::class);

            // Credit Cards (nested under accounts)
            Route::post('accounts/{bankAccount}/cards', [CreditCardController::class, 'store']);

            // Credit Cards standalone
            Route::get('cards', [CreditCardController::class, 'index']);
            Route::get('cards/{creditCard}', [CreditCardController::class, 'show']);
            Route::put('cards/{creditCard}', [CreditCardController::class, 'update']);
            Route::delete('cards/{creditCard}', [CreditCardController::class, 'destroy']);

            // Transactions
            Route::apiResource('transactions', TransactionController::class);

            // Categories
            Route::get('categories', [TransactionCategoryController::class, 'index']);
            Route::post('categories', [TransactionCategoryController::class, 'store']);
            Route::put('categories/{transactionCategory}', [TransactionCategoryController::class, 'update']);
            Route::delete('categories/{transactionCategory}', [TransactionCategoryController::class, 'destroy']);

            // Reports
            Route::prefix('reports')->group(function () {
                Route::get('monthly', [FinanceReportController::class, 'monthlySummary']);
                Route::get('yearly', [FinanceReportController::class, 'yearlySummary']);
                Route::get('cashflow', [FinanceReportController::class, 'cashFlow']);
                Route::get('balance', [FinanceReportController::class, 'consolidatedBalance']);
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

        // Purchases
        Route::apiResource('purchases', PurchaseItemController::class)->except(['show']);
    });
});
