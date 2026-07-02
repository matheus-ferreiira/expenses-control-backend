<?php

namespace App\Domains\Prices\Resources;

use App\Domains\Prices\Enums\GoalStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PriceProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'name' => $this->name,
            'brand' => $this->brand,
            'model' => $this->model,
            'specs' => $this->specs,
            'notes' => $this->notes,
            'target_price' => $this->target_price !== null ? (float) $this->target_price : null,
            'launch_price' => $this->launch_price !== null ? (float) $this->launch_price : null,
            'status' => $this->status->value,
            'category' => new PriceCategoryResource($this->whenLoaded('category')),
            'stats' => $this->whenHas('price_records_count', fn () => $this->buildStats()),
            'created_at' => $this->created_at->toISOString(),
        ];
    }

    private function buildStats(): array
    {
        $target = $this->target_price !== null ? (float) $this->target_price : null;
        $last = $this->latestPriceRecord !== null ? (float) $this->latestPriceRecord->price : null;

        return [
            'last_price' => $last,
            'last_recorded_at' => $this->latestPriceRecord?->recorded_at?->toDateString(),
            'min_price' => $this->price_records_min_price !== null ? (float) $this->price_records_min_price : null,
            'avg_price' => $this->price_records_avg_price !== null ? round((float) $this->price_records_avg_price, 2) : null,
            'max_price' => $this->price_records_max_price !== null ? (float) $this->price_records_max_price : null,
            'records_count' => (int) $this->price_records_count,
            'goal_status' => $last !== null ? GoalStatus::fromPrice($target, $last)?->value : null,
        ];
    }
}
