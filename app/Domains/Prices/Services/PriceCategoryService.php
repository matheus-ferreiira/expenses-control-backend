<?php

namespace App\Domains\Prices\Services;

use App\Domains\Prices\DTOs\PriceCategoryDTO;
use App\Domains\Prices\Models\PriceCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class PriceCategoryService
{
    public function list(User $user): Collection
    {
        return PriceCategory::forUser($user->id)->orderBy('name')->get();
    }

    public function create(User $user, PriceCategoryDTO $dto): PriceCategory
    {
        return PriceCategory::create([
            'user_id' => $user->id,
            'name' => $dto->name,
            'icon' => $dto->icon,
        ]);
    }

    public function update(PriceCategory $category, PriceCategoryDTO $dto): PriceCategory
    {
        $category->update([
            'name' => $dto->name,
            'icon' => $dto->icon,
        ]);

        return $category;
    }

    public function delete(PriceCategory $category): void
    {
        $category->delete();
    }
}
