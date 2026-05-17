<?php

namespace App\Domains\Habits\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HabitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->name,
            'category' => $this->category,
            'description' => $this->description,
            'frequency' => $this->frequency_type?->value,
            'target_days' => [],
            'color' => $this->color,
            'icon' => $this->icon,
            'is_active' => $this->archived_at === null,
            'is_archived' => $this->archived_at !== null,
            'current_streak' => $this->computeCurrentStreak(),
            'longest_streak' => $this->computeLongestStreak(),
            'logs' => HabitLogResource::collection($this->whenLoaded('logs')),
            'start_date' => $this->start_date?->toDateString(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }

    private function computeCurrentStreak(): int
    {
        if (! $this->relationLoaded('logs')) {
            return 0;
        }

        $dates = $this->logs
            ->pluck('completed_date')
            ->map(fn ($d) => is_string($d) ? $d : $d->toDateString())
            ->unique()
            ->sort()
            ->reverse()
            ->values();

        if ($dates->isEmpty()) {
            return 0;
        }

        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();
        $latest = $dates->first();

        if ($latest !== $today && $latest !== $yesterday) {
            return 0;
        }

        $streak = 0;
        $expected = Carbon::parse($latest);

        foreach ($dates as $dateStr) {
            if ($dateStr === $expected->toDateString()) {
                $streak++;
                $expected->subDay();
            } else {
                break;
            }
        }

        return $streak;
    }

    private function computeLongestStreak(): int
    {
        if (! $this->relationLoaded('logs')) {
            return 0;
        }

        $dates = $this->logs
            ->pluck('completed_date')
            ->map(fn ($d) => is_string($d) ? $d : $d->toDateString())
            ->unique()
            ->sort()
            ->values();

        if ($dates->isEmpty()) {
            return 0;
        }

        $longest = 1;
        $current = 1;

        for ($i = 1; $i < $dates->count(); $i++) {
            $prev = Carbon::parse($dates[$i - 1]);
            $curr = Carbon::parse($dates[$i]);

            if ($prev->diffInDays($curr) === 1) {
                $current++;
                $longest = max($longest, $current);
            } else {
                $current = 1;
            }
        }

        return $longest;
    }
}
