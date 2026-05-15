<?php

namespace App\Domains\Purchases\Controllers;

use App\Domains\Purchases\Models\PurchaseItem;
use App\Domains\Purchases\Requests\StorePurchaseItemRequest;
use App\Domains\Purchases\Requests\UpdatePurchaseItemRequest;
use App\Domains\Purchases\Resources\PurchaseItemResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseItemController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = PurchaseItem::forUser($request->user()->id)
            ->orderBy('is_bought')
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        return $this->success(PurchaseItemResource::collection($items));
    }

    public function store(StorePurchaseItemRequest $request): JsonResponse
    {
        $item = PurchaseItem::create([
            'user_id' => $request->user()->id,
            ...$request->validated(),
        ]);

        return $this->created(new PurchaseItemResource($item), 'Item created');
    }

    public function update(UpdatePurchaseItemRequest $request, PurchaseItem $purchase): JsonResponse
    {
        $this->authorize('update', $purchase);
        $purchase->update($request->validated());

        return $this->success(new PurchaseItemResource($purchase), 'Item updated');
    }

    public function destroy(Request $request, PurchaseItem $purchase): JsonResponse
    {
        $this->authorize('delete', $purchase);
        $purchase->delete();

        return $this->noContent();
    }
}
