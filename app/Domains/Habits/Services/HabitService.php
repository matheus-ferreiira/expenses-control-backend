<?php

namespace App\Domains\Habits\Services;

use App\Domains\Habits\Actions\CreateHabitAction;
use App\Domains\Habits\Actions\LogHabitAction;
use App\Domains\Habits\DTOs\HabitDTO;
use App\Domains\Habits\DTOs\HabitLogDTO;
use App\Domains\Habits\Models\Habit;
use App\Domains\Habits\Models\HabitLog;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

final class HabitService
{
    public function __construct(
        private readonly CreateHabitAction $createHabit,
        private readonly LogHabitAction $logHabit,
        private readonly HabitStatsService $statsService,
    ) {}

    public function list(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = Habit::forUser($user->id)->active();

        if (isset($filters['archived']) && $filters['archived']) {
            $query = Habit::forUser($user->id)->archived();
        }

        if (! empty($filters['search'])) {
            $query->where('name', 'ilike', "%{$filters['search']}%");
        }

        return $query
            ->with(['logs' => fn ($q) => $q->whereDate('completed_date', '>=', today()->subDays(89))->orderByDesc('completed_date')])
            ->paginate($filters['per_page'] ?? 20);
    }

    public function getTodayHabits(User $user): Collection
    {
        return Habit::forUser($user->id)
            ->active()
            ->with(['logs' => fn ($q) => $q->whereDate('completed_date', '>=', today()->subDays(89))->orderByDesc('completed_date')])
            ->get();
    }

    public function create(User $user, HabitDTO $dto): Habit
    {
        return $this->createHabit->execute($user, $dto);
    }

    public function update(Habit $habit, HabitDTO $dto): Habit
    {
        $habit->update([
            'name' => $dto->name,
            'category' => $dto->category,
            'description' => $dto->description,
            'frequency_type' => $dto->frequencyType,
            'target_frequency' => $dto->targetFrequency,
            'color' => $dto->color,
            'icon' => $dto->icon,
        ]);

        return $habit;
    }

    public function archive(Habit $habit, bool $archive = true): Habit
    {
        $habit->update(['archived_at' => $archive ? now() : null]);

        return $habit;
    }

    public function delete(Habit $habit): void
    {
        $habit->delete();
    }

    public function log(Habit $habit, HabitLogDTO $dto): HabitLog
    {
        return $this->logHabit->execute($habit, $dto);
    }

    public function unlog(Habit $habit, string $date): bool
    {
        return (bool) $habit->logs()->whereDate('completed_date', $date)->delete();
    }

    public function getStats(Habit $habit): array
    {
        return $this->statsService->getStats($habit);
    }

    public function getHeatmap(Habit $habit, int $days = 365): array
    {
        return $this->statsService->getHeatmap($habit, $days);
    }
}
