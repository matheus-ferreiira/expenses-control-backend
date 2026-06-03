<?php

namespace App\Domains\Finance\Controllers;

use App\Domains\Finance\Models\FinanceGoal;
use App\Domains\Finance\Requests\StoreFinanceGoalRequest;
use App\Domains\Finance\Requests\UpdateFinanceGoalRequest;
use App\Domains\Finance\Resources\FinanceGoalResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinanceGoalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $goals = FinanceGoal::with('bankAccount')
            ->forUser($request->user()->id)
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->success(FinanceGoalResource::collection($goals));
    }

    public function store(StoreFinanceGoalRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $goal = FinanceGoal::create([
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'target_amount' => $validated['target_amount'],
            'monthly_contribution' => $validated['monthly_contribution'] ?? 0,
            'deadline' => $validated['deadline'] ?? null,
            'color' => $validated['color'] ?? null,
            'icon' => $validated['icon'] ?? null,
            'bank_account_id' => $validated['bank_account_id'] ?? null,
            'status' => 'active',
        ]);

        $goal->load('bankAccount');

        $response = new FinanceGoalResource($goal);
        $data = $response->toArray($request);

        // Signal frontend to offer creating a recurring transaction
        $data['create_recurring_transaction'] = (float) ($validated['monthly_contribution'] ?? 0) > 0;

        return $this->created($data, 'Goal created');
    }

    public function update(UpdateFinanceGoalRequest $request, FinanceGoal $goal): JsonResponse
    {
        $this->authorize('update', $goal);
        $goal->update($request->validated());
        $goal->load('bankAccount');

        return $this->success(new FinanceGoalResource($goal), 'Goal updated');
    }

    public function complete(Request $request, FinanceGoal $goal): JsonResponse
    {
        $this->authorize('update', $goal);
        $goal->update(['status' => 'completed']);
        $goal->load('bankAccount');

        return $this->success(new FinanceGoalResource($goal), 'Goal completed');
    }

    public function destroy(FinanceGoal $goal): JsonResponse
    {
        $this->authorize('delete', $goal);
        $goal->delete();

        return $this->noContent();
    }
}
