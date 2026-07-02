<?php

namespace App\Domains\Prices\Services;

use App\Domains\Prices\DTOs\PriceRecordDTO;
use App\Domains\Prices\Models\PriceRecord;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class PriceRecordService
{
    public function list(User $user, array $filters = [], int $perPage = 50): LengthAwarePaginator
    {
        $query = PriceRecord::forUser($user->id)
            ->with(['product:id,name,target_price', 'store:id,name']);

        if (! empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        if (! empty($filters['store_id'])) {
            $query->where('store_id', $filters['store_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('recorded_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('recorded_at', '<=', $filters['date_to']);
        }

        return $query
            ->orderByDesc('recorded_at')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function create(User $user, PriceRecordDTO $dto): PriceRecord
    {
        return PriceRecord::create([
            'user_id' => $user->id,
            'product_id' => $dto->productId,
            'store_id' => $dto->storeId,
            'price' => $dto->price,
            'recorded_at' => $dto->recordedAt,
            'url' => $dto->url,
            'notes' => $dto->notes,
        ]);
    }

    public function update(PriceRecord $record, PriceRecordDTO $dto): PriceRecord
    {
        $record->update([
            'product_id' => $dto->productId,
            'store_id' => $dto->storeId,
            'price' => $dto->price,
            'recorded_at' => $dto->recordedAt,
            'url' => $dto->url,
            'notes' => $dto->notes,
        ]);

        return $record;
    }

    public function delete(PriceRecord $record): void
    {
        $record->delete();
    }
}
