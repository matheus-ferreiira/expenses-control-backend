<?php

namespace App\Domains\Finance\Controllers;

use App\Domains\Finance\Models\Budget;
use App\Domains\Finance\Requests\StoreBudgetRequest;
use App\Domains\Finance\Requests\UpdateBudgetRequest;
use App\Domains\Finance\Resources\BudgetResource;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BudgetController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        $month = (int) $request->query('month');
        $year = (int) $request->query('year');
        $userId = $request->user()->id;

        $budget = Budget::with('items.category')
            ->forUser($userId)
            ->where('month', $month)
            ->where('year', $year)
            ->first();

        if (! $budget) {
            return $this->success(null);
        }

        $startDate = Carbon::create($year, $month, 1)->startOfMonth()->toDateString();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth()->toDateString();

        return $this->success(new BudgetResource($budget, $startDate, $endDate));
    }

    public function store(StoreBudgetRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $userId = $request->user()->id;

        $budget = Budget::create([
            'user_id' => $userId,
            'month' => $validated['month'],
            'year' => $validated['year'],
            'base_amount' => $validated['base_amount'],
            'is_template' => $validated['is_template'] ?? false,
        ]);

        if (! empty($validated['items'])) {
            foreach ($validated['items'] as $item) {
                $percentage = $item['percentage'] ?? ($budget->base_amount > 0
                    ? round(($item['amount'] / (float) $budget->base_amount) * 100, 2)
                    : 0);

                $budget->items()->create([
                    'category_id' => $item['category_id'],
                    'amount' => $item['amount'],
                    'percentage' => $percentage,
                ]);
            }
        }

        $budget->load('items.category');
        $startDate = Carbon::create($validated['year'], $validated['month'], 1)->startOfMonth()->toDateString();
        $endDate = Carbon::create($validated['year'], $validated['month'], 1)->endOfMonth()->toDateString();

        return $this->created(new BudgetResource($budget, $startDate, $endDate), 'Budget created');
    }

    public function update(UpdateBudgetRequest $request, Budget $budget): JsonResponse
    {
        $this->authorize('update', $budget);
        $validated = $request->validated();

        if (isset($validated['base_amount'])) {
            $budget->update(['base_amount' => $validated['base_amount']]);
        }
        if (isset($validated['is_template'])) {
            $budget->update(['is_template' => $validated['is_template']]);
        }

        if (isset($validated['items'])) {
            $budget->items()->delete();

            $baseAmount = (float) $budget->base_amount;
            foreach ($validated['items'] as $item) {
                $percentage = $item['percentage'] ?? ($baseAmount > 0
                    ? round(($item['amount'] / $baseAmount) * 100, 2)
                    : 0);

                $budget->items()->create([
                    'category_id' => $item['category_id'],
                    'amount' => $item['amount'],
                    'percentage' => $percentage,
                ]);
            }
        }

        $budget->load('items.category');
        $startDate = Carbon::create($budget->year, $budget->month, 1)->startOfMonth()->toDateString();
        $endDate = Carbon::create($budget->year, $budget->month, 1)->endOfMonth()->toDateString();

        return $this->success(new BudgetResource($budget, $startDate, $endDate), 'Budget updated');
    }

    public function destroy(Budget $budget): JsonResponse
    {
        $this->authorize('delete', $budget);
        $budget->items()->delete();
        $budget->delete();

        return $this->noContent();
    }

    public function previous(Request $request): JsonResponse
    {
        $request->validate([
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        $month = (int) $request->query('month');
        $year = (int) $request->query('year');
        $userId = $request->user()->id;

        // Calculate previous month
        $date = Carbon::create($year, $month, 1)->subMonth();
        $prevMonth = $date->month;
        $prevYear = $date->year;

        $budget = Budget::with('items.category')
            ->forUser($userId)
            ->where('month', $prevMonth)
            ->where('year', $prevYear)
            ->first();

        if (! $budget) {
            return $this->success(null);
        }

        $startDate = Carbon::create($prevYear, $prevMonth, 1)->startOfMonth()->toDateString();
        $endDate = Carbon::create($prevYear, $prevMonth, 1)->endOfMonth()->toDateString();

        return $this->success(new BudgetResource($budget, $startDate, $endDate));
    }
}
