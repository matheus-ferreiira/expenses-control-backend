<?php

namespace App\Domains\Prices\Services;

use App\Domains\Prices\DTOs\PriceSaleDTO;
use App\Domains\Prices\Models\PriceSale;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class PriceSaleService
{
    public function list(User $user): Collection
    {
        return PriceSale::forUser($user->id)
            ->with('purchase.product')
            ->orderByDesc('sold_at')
            ->orderByDesc('created_at')
            ->get();
    }

    public function create(User $user, PriceSaleDTO $dto): PriceSale
    {
        return PriceSale::create([
            'user_id' => $user->id,
            'purchase_id' => $dto->purchaseId,
            'sale_price' => $dto->salePrice,
            'sold_at' => $dto->soldAt,
            'notes' => $dto->notes,
        ]);
    }

    public function update(PriceSale $sale, PriceSaleDTO $dto): PriceSale
    {
        $sale->update([
            'sale_price' => $dto->salePrice,
            'sold_at' => $dto->soldAt,
            'notes' => $dto->notes,
        ]);

        return $sale;
    }

    public function delete(PriceSale $sale): void
    {
        $sale->delete();
    }
}
