<?php

namespace App\Domains\Prices\Services;

use App\Domains\Prices\DTOs\PricePurchaseDTO;
use App\Domains\Prices\Models\PricePurchase;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class PricePurchaseService
{
    public function list(User $user): Collection
    {
        return PricePurchase::forUser($user->id)
            ->with(['product', 'store', 'sale'])
            ->orderByDesc('purchased_at')
            ->orderByDesc('created_at')
            ->get();
    }

    public function create(User $user, PricePurchaseDTO $dto): PricePurchase
    {
        return PricePurchase::create([
            'user_id' => $user->id,
            'product_id' => $dto->productId,
            'store_id' => $dto->storeId,
            'price_paid' => $dto->pricePaid,
            'purchased_at' => $dto->purchasedAt,
            'warranty_months' => $dto->warrantyMonths,
            'current_value' => $dto->currentValue,
            'notes' => $dto->notes,
        ]);
    }

    public function update(PricePurchase $purchase, PricePurchaseDTO $dto): PricePurchase
    {
        $purchase->update([
            'product_id' => $dto->productId,
            'store_id' => $dto->storeId,
            'price_paid' => $dto->pricePaid,
            'purchased_at' => $dto->purchasedAt,
            'warranty_months' => $dto->warrantyMonths,
            'current_value' => $dto->currentValue,
            'notes' => $dto->notes,
        ]);

        return $purchase;
    }

    public function delete(PricePurchase $purchase): void
    {
        $purchase->delete();
    }
}
