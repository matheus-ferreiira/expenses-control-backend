<?php

namespace App\Domains\Finance\Resources;

use App\Domains\Finance\Models\Budget;
use App\Domains\Finance\Models\FinanceGoal;
use App\Domains\Finance\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BudgetResource extends JsonResource
{
    public function __construct(
        Budget $resource,
        private readonly string $startDate = '',
        private readonly string $endDate = '',
    ) {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        /** @var Budget $budget */
        $budget = $this->resource;

        $spentByCategory = [];
        if ($this->startDate && $this->endDate) {
            $spentByCategory = Transaction::query()
                ->where('user_id', $budget->user_id)
                ->where('type', 'expense')
                ->where('status', 'confirmed')
                ->whereBetween('transaction_date', [$this->startDate, $this->endDate])
                ->whereNotNull('category_id')
                ->selectRaw('category_id, SUM(amount) as total')
                ->groupBy('category_id')
                ->pluck('total', 'category_id')
                ->toArray();
        }

        $items = $budget->items->map(function ($item) use ($spentByCategory, $budget) {
            $spent = (float) ($spentByCategory[$item->category_id] ?? 0);
            $amount = (float) $item->amount;
            $spentPct = $amount > 0 ? round(($spent / $amount) * 100, 2) : 0;
            $remaining = max(0, $amount - $spent);

            $status = 'on_track';
            if ($spentPct >= 100) $status = 'exceeded';
            elseif ($spentPct >= 70) $status = 'warning';

            return [
                'id' => $item->id,
                'category_id' => $item->category_id,
                'category_name' => $item->category?->name,
                'category_color' => $item->category?->color,
                'category_icon' => $item->category?->icon,
                'amount' => $amount,
                'percentage' => (float) $item->percentage,
                'spent' => $spent,
                'spent_percentage' => $spentPct,
                'remaining' => $remaining,
                'status' => $status,
            ];
        });

        // Active goals with monthly contribution
        $goals = FinanceGoal::where('user_id', $budget->user_id)
            ->where('status', 'active')
            ->where('monthly_contribution', '>', 0)
            ->get();

        $baseAmount = (float) $budget->base_amount;

        $goalsItems = $goals->map(function ($goal) use ($baseAmount) {
            $amount = (float) $goal->monthly_contribution;
            $pct = $baseAmount > 0 ? round(($amount / $baseAmount) * 100, 2) : 0;
            return [
                'id' => $goal->id,
                'name' => $goal->name,
                'color' => $goal->color,
                'icon' => $goal->icon ?? 'Flag',
                'amount' => $amount,
                'percentage' => $pct,
                'type' => 'goal',
            ];
        });

        $totalFromCategories = $items->sum('amount');
        $totalFromGoals = $goals->sum('monthly_contribution');
        $totalBudgeted = $totalFromCategories + (float) $totalFromGoals;
        $totalSpent = $items->sum('spent');
        $freeAmount = $baseAmount - $totalBudgeted;
        $budgetedPct = $baseAmount > 0 ? round(($totalBudgeted / $baseAmount) * 100, 2) : 0;
        $freePct = $baseAmount > 0 ? round(($freeAmount / $baseAmount) * 100, 2) : 0;
        $categoriesPct = $baseAmount > 0 ? round(($totalFromCategories / $baseAmount) * 100, 2) : 0;
        $goalsPct = $baseAmount > 0 ? round(((float) $totalFromGoals / $baseAmount) * 100, 2) : 0;

        return [
            'id' => $budget->id,
            'month' => $budget->month,
            'year' => $budget->year,
            'base_amount' => $baseAmount,
            'is_template' => $budget->is_template,
            'summary' => [
                'base_amount' => $baseAmount,
                'total_from_categories' => (float) $totalFromCategories,
                'total_from_goals' => (float) $totalFromGoals,
                'total_budgeted' => $totalBudgeted,
                'total_budgeted_percentage' => $budgetedPct,
                'total_spent' => $totalSpent,
                'free_amount' => $freeAmount,
                'free_percentage' => $freePct,
                'categories_percentage' => $categoriesPct,
                'goals_percentage' => $goalsPct,
            ],
            'items' => $items->values(),
            'goals_items' => $goalsItems->values(),
            'created_at' => $budget->created_at,
            'updated_at' => $budget->updated_at,
        ];
    }
}
