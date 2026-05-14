<?php

namespace App\Domains\Reports\Controllers;

use App\Domains\Habits\Models\Habit;
use App\Domains\Habits\Services\HabitStatsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(
        private readonly HabitStatsService $habitStatsService,
    ) {}

    public function weeklyProductivity(Request $request): JsonResponse
    {
        $user = $request->user();
        $startOfWeek = now()->startOfWeek();
        $endOfWeek = now()->endOfWeek();

        $habitsStats = Habit::forUser($user->id)->active()->get()->map(function ($habit) {
            return [
                'habit' => $habit->only(['id', 'name', 'color']),
                'stats' => $this->habitStatsService->getStats($habit),
            ];
        });

        return $this->success([
            'week_start' => $startOfWeek->toDateString(),
            'week_end' => $endOfWeek->toDateString(),
            'habits' => $habitsStats,
        ]);
    }
}
