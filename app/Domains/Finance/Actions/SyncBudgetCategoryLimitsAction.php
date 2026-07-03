<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Finance\Models\Budget;
use App\Domains\Finance\Models\TransactionCategory;

final class SyncBudgetCategoryLimitsAction
{
    /**
     * Keep transaction_categories.monthly_limit in sync with the CURRENT month's
     * budget items, so the category panel (FinancePage) and the budget screen
     * always agree. The budget is the single source of truth:
     * - category present in the budget  → monthly_limit = item amount
     * - category absent from the budget → monthly_limit = null
     *
     * Budgets for other months don't touch the limits — monthly_limit always
     * mirrors the month the user is living in.
     */
    public function execute(Budget $budget): void
    {
        if ($budget->month !== now()->month || $budget->year !== now()->year) {
            return;
        }

        $limits = $budget->items()
            ->pluck('amount', 'category_id');

        TransactionCategory::query()
            ->where('user_id', $budget->user_id)
            ->where('type', 'expense')
            ->get()
            ->each(function (TransactionCategory $category) use ($limits) {
                $newLimit = $limits->has($category->id)
                    ? (float) $limits->get($category->id)
                    : null;

                $current = $category->monthly_limit !== null ? (float) $category->monthly_limit : null;

                if ($current !== $newLimit) {
                    $category->update(['monthly_limit' => $newLimit]);
                }
            });
    }
}
