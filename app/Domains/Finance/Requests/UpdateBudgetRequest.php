<?php

namespace App\Domains\Finance\Requests;

use App\Domains\Finance\Models\Budget;
use App\Domains\Finance\Models\FinanceGoal;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Validator;

class UpdateBudgetRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'base_amount' => ['sometimes', 'numeric', 'min:0.01', 'max:9999999999'],
            'is_template' => ['sometimes', 'boolean'],
            'items' => ['sometimes', 'array'],
            'items.*.category_id' => ['required_with:items', 'uuid', 'exists:transaction_categories,id'],
            'items.*.amount' => ['required_with:items', 'numeric', 'min:0.01', 'max:9999999999'],
            'items.*.percentage' => ['sometimes', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $items = $this->input('items');
            if ($items === null) {
                return; // items not being updated, skip
            }

            /** @var Budget|null $budget */
            $budget = $this->route('budget');
            $baseAmount = (float) ($this->input('base_amount') ?? $budget?->base_amount ?? 0);

            $itemsTotal = collect($items)->sum(function ($item) use ($baseAmount) {
                $amount = (float) ($item['amount'] ?? 0);
                return $baseAmount > 0 ? ($amount / $baseAmount) * 100 : 0;
            });

            $goalsTotal = 0;
            if ($baseAmount > 0 && $this->user()) {
                $goalsTotal = FinanceGoal::where('user_id', $this->user()->id)
                    ->where('status', 'active')
                    ->where('monthly_contribution', '>', 0)
                    ->sum('monthly_contribution');
                $goalsTotal = ($goalsTotal / $baseAmount) * 100;
            }

            $totalPercentage = round($itemsTotal + $goalsTotal, 2);

            if ($totalPercentage > 100) {
                $validator->errors()->add(
                    'items',
                    "A soma das alocações ({$totalPercentage}%) não pode ultrapassar 100% do valor base."
                );
            }
        });
    }
}
