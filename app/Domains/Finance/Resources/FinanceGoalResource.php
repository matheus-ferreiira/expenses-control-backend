<?php

namespace App\Domains\Finance\Resources;

use App\Domains\Finance\Models\FinanceGoal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FinanceGoalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var FinanceGoal $goal */
        $goal = $this->resource;

        $targetAmount = (float) $goal->target_amount;
        $monthlyContribution = (float) $goal->monthly_contribution;

        // Current amount: bank account balance if linked, else sum of confirmed
        // transactions of ANY type linked via goal_id — an aporte is usually an
        // expense/transfer leaving the checking account, not an income.
        if ($goal->bank_account_id && $goal->bankAccount) {
            $currentAmount = (float) $goal->bankAccount->balance;
        } else {
            $currentAmount = (float) $goal->transactions()
                ->where('status', 'confirmed')
                ->sum('amount');
        }

        $progressPct = $targetAmount > 0
            ? min(100, round(($currentAmount / $targetAmount) * 100, 2))
            : 0;

        $remaining = max(0, $targetAmount - $currentAmount);

        // Months remaining until deadline
        $monthsRemaining = null;
        if ($goal->deadline) {
            $now = now()->startOfMonth();
            $deadline = $goal->deadline->startOfMonth();
            $monthsRemaining = max(0, (int) $now->diffInMonths($deadline, false));
        }

        // On-track calculation
        $onTrack = null;
        if ($monthlyContribution > 0) {
            $monthsElapsed = (int) now()->startOfMonth()->diffInMonths(
                $goal->created_at->startOfMonth(),
                false,
            );
            $expectedAmount = $monthsElapsed * $monthlyContribution;
            $onTrack = $currentAmount >= $expectedAmount;
        }

        return [
            'id' => $goal->id,
            'name' => $goal->name,
            'target_amount' => $targetAmount,
            'monthly_contribution' => $monthlyContribution,
            'deadline' => $goal->deadline?->toDateString(),
            'color' => $goal->color,
            'icon' => $goal->icon,
            'bank_account_id' => $goal->bank_account_id,
            'bank_account' => $goal->bank_account_id && $goal->bankAccount
                ? ['id' => $goal->bankAccount->id, 'name' => $goal->bankAccount->name]
                : null,
            'status' => $goal->status,
            'current_amount' => $currentAmount,
            'progress_percentage' => $progressPct,
            'remaining' => $remaining,
            'months_remaining' => $monthsRemaining,
            'on_track' => $onTrack,
            'created_at' => $goal->created_at,
            'updated_at' => $goal->updated_at,
        ];
    }
}
