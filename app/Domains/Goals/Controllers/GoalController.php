<?php

namespace App\Domains\Goals\Controllers;

use App\Domains\Goals\DTOs\GoalDTO;
use App\Domains\Goals\Models\Goal;
use App\Domains\Goals\Requests\StoreGoalRequest;
use App\Domains\Goals\Requests\UpdateGoalRequest;
use App\Domains\Goals\Resources\GoalResource;
use App\Domains\Goals\Services\GoalService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoalController extends Controller
{
    public function __construct(
        private readonly GoalService $goalService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $goals = $this->goalService->list($request->user(), $request->query());
        return $this->paginatedSuccess(GoalResource::collection($goals));
    }

    public function store(StoreGoalRequest $request): JsonResponse
    {
        $goal = $this->goalService->create($request->user(), GoalDTO::fromArray($request->validated()));
        return $this->created(new GoalResource($goal), 'Goal created');
    }

    public function show(Request $request, Goal $goal): JsonResponse
    {
        $this->authorize('view', $goal);
        return $this->success(new GoalResource($goal));
    }

    public function update(UpdateGoalRequest $request, Goal $goal): JsonResponse
    {
        $this->authorize('update', $goal);
        $goal = $this->goalService->update($goal, GoalDTO::fromArray($request->validated()));
        return $this->success(new GoalResource($goal), 'Goal updated');
    }

    public function destroy(Request $request, Goal $goal): JsonResponse
    {
        $this->authorize('delete', $goal);
        $this->goalService->delete($goal);
        return $this->noContent();
    }

    public function updateProgress(Request $request, Goal $goal): JsonResponse
    {
        $this->authorize('update', $goal);
        $request->validate(['current_amount' => ['required', 'numeric', 'min:0']]);
        $goal = $this->goalService->updateProgress($goal, (float) $request->current_amount);
        return $this->success(new GoalResource($goal), 'Progress updated');
    }
}
