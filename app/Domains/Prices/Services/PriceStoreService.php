<?php

namespace App\Domains\Prices\Services;

use App\Domains\Prices\DTOs\PriceStoreDTO;
use App\Domains\Prices\Models\PriceStore;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class PriceStoreService
{
    public function list(User $user): Collection
    {
        return PriceStore::forUser($user->id)->orderBy('name')->get();
    }

    public function create(User $user, PriceStoreDTO $dto): PriceStore
    {
        return PriceStore::create([
            'user_id' => $user->id,
            'name' => $dto->name,
            'website_url' => $dto->websiteUrl,
            'notes' => $dto->notes,
        ]);
    }

    public function update(PriceStore $store, PriceStoreDTO $dto): PriceStore
    {
        $store->update([
            'name' => $dto->name,
            'website_url' => $dto->websiteUrl,
            'notes' => $dto->notes,
        ]);

        return $store;
    }

    public function delete(PriceStore $store): void
    {
        $store->delete();
    }
}
