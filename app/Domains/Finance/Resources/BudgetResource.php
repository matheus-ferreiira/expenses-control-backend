<?php

namespace App\Domains\Finance\Resources;

use App\Domains\Finance\Models\Budget;
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
                'amount' => (float) $item->amount,
                'percentage' => (float) $item->percentage,
                'spent' => $spent,
                'spent_percentage' => $spentPct,
                'remaining' => $remaining,
                'status' => $status,
            ];
        });

        $totalBudgeted = $items->sum('amount');
        $baseAmount = (float) $budget->base_amount;
        $totalSpent = $items->sum('spent');
        $freeAmount = max(0, $baseAmount - $totalBudgeted);
        $budgetedPct = $baseAmount > 0 ? round(($totalBudgeted / $baseAmount) * 100, 2) : 0;
        $freePct = $baseAmount > 0 ? round(($freeAmount / $baseAmount) * 100, 2) : 0;

        return [
            'id' => $budget->id,
            'month' => $budget->month,
            'year' => $budget->year,
            'base_amount' => $baseAmount,
            'is_template' => $budget->is_template,
            'summary' => [
                'base_amount' => $baseAmount,
                'total_budgeted' => $totalBudgeted,
                'total_budgeted_percentage' => $budgetedPct,
                'total_spent' => $totalSpent,
                'free_amount' => $freeAmount,
                'free_percentage' => $freePct,
            ],
            'items' => $items->values(),
            'created_at' => $budget->created_at,
            'updated_at' => $budget->updated_at,
        ];
    }
}
