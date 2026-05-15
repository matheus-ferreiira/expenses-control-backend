<?php

namespace App\Domains\Reports\Controllers;

use App\Domains\Habits\Models\Habit;
use App\Domains\Habits\Models\HabitLog;
use App\Domains\Habits\Services\HabitStatsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(
        private readonly HabitStatsService $habitStatsService,
    ) {}

    public function habitsLogCount(Request $request): JsonResponse
    {
        $request->validate(['since' => ['required', 'date_format:Y-m-d']]);
        $user = $request->user();

        $count = HabitLog::whereHas('habit', fn ($q) => $q->where('user_id', $user->id))
            ->where('completed_date', '>=', $request->query('since'))
            ->count();

        return $this->success(['count' => $count]);
    }

    public function weeklyProductivity(Request $request): JsonResponse
    {
        $user = $request->user();
        $startOfWeek = now()->startOfWeek();
        $endOfWeek = now()->endOfWeek();

        $habitsStats = Habit::forUser($user->id)
            ->active()
            ->with(['logs' => fn ($q) => $q->whereDate('completed_date', '>=', today()->subYears(2))->orderByDesc('completed_date')])
            ->get()
            ->map(function ($habit) {
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
