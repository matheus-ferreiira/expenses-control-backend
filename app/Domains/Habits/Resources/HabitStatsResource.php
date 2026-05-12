<?php

namespace App\Domains\Habits\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HabitStatsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'habit' => new HabitResource($this->resource['habit']),
            'stats' => $this->resource['stats'],
        ];
    }
}
