<?php

namespace App\Domains\Prices\Services;

use App\Domains\Prices\DTOs\PriceProductDTO;
use App\Domains\Prices\Models\PriceProduct;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class PriceProductService
{
    public function list(User $user, array $filters = []): Collection
    {
        $query = PriceProduct::forUser($user->id)
            ->with(['category', 'latestPriceRecord']);

        $this->withStats($query);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('name')->get();
    }

    public function withStats(Builder $query): Builder
    {
        return $query
            ->withMin('priceRecords', 'price')
            ->withMax('priceRecords', 'price')
            ->withAvg('priceRecords', 'price')
            ->withCount('priceRecords');
    }

    public function find(PriceProduct $product): PriceProduct
    {
        return $this->withStats(PriceProduct::query()->whereKey($product->id))
            ->with(['category', 'latestPriceRecord'])
            ->firstOrFail();
    }

    public function create(User $user, PriceProductDTO $dto): PriceProduct
    {
        return PriceProduct::create([
            'user_id' => $user->id,
            'category_id' => $dto->categoryId,
            'name' => $dto->name,
            'brand' => $dto->brand,
            'model' => $dto->model,
            'specs' => $dto->specs,
            'notes' => $dto->notes,
            'target_price' => $dto->targetPrice,
            'launch_price' => $dto->launchPrice,
            'status' => $dto->status,
        ]);
    }

    public function update(PriceProduct $product, PriceProductDTO $dto): PriceProduct
    {
        $product->update([
            'category_id' => $dto->categoryId,
            'name' => $dto->name,
            'brand' => $dto->brand,
            'model' => $dto->model,
            'specs' => $dto->specs,
            'notes' => $dto->notes,
            'target_price' => $dto->targetPrice,
            'launch_price' => $dto->launchPrice,
            'status' => $dto->status,
        ]);

        return $product;
    }

    public function delete(PriceProduct $product): void
    {
        $product->delete();
    }
}
