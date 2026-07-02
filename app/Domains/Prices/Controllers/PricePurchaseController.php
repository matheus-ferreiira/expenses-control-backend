<?php

namespace App\Domains\Prices\Controllers;

use App\Domains\Prices\DTOs\PricePurchaseDTO;
use App\Domains\Prices\Models\PricePurchase;
use App\Domains\Prices\Requests\StorePricePurchaseRequest;
use App\Domains\Prices\Requests\UpdatePricePurchaseRequest;
use App\Domains\Prices\Resources\PricePurchaseResource;
use App\Domains\Prices\Services\PricePurchaseService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PricePurchaseController extends Controller
{
    public function __construct(
        private readonly PricePurchaseService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->success(PricePurchaseResource::collection($this->service->list($request->user())));
    }

    public function store(StorePricePurchaseRequest $request): JsonResponse
    {
        $purchase = $this->service->create($request->user(), PricePurchaseDTO::fromArray($request->validated()));

        return $this->created(new PricePurchaseResource($purchase->load(['product', 'store', 'sale'])), 'Purchase created');
    }

    public function show(Request $request, PricePurchase $purchase): JsonResponse
    {
        $this->authorize('view', $purchase);

        return $this->success(new PricePurchaseResource($purchase->load(['product', 'store', 'sale'])));
    }

    public function update(UpdatePricePurchaseRequest $request, PricePurchase $purchase): JsonResponse
    {
        $this->authorize('update', $purchase);

        $data = array_merge([
            'product_id' => $purchase->product_id,
            'store_id' => $purchase->store_id,
            'price_paid' => (float) $purchase->price_paid,
            'purchased_at' => $purchase->purchased_at->toDateString(),
            'warranty_months' => $purchase->warranty_months,
            'current_value' => $purchase->current_value !== null ? (float) $purchase->current_value : null,
            'notes' => $purchase->notes,
        ], $request->validated());

        $updated = $this->service->update($purchase, PricePurchaseDTO::fromArray($data));

        return $this->success(new PricePurchaseResource($updated->load(['product', 'store', 'sale'])), 'Purchase updated');
    }

    public function destroy(Request $request, PricePurchase $purchase): JsonResponse
    {
        $this->authorize('delete', $purchase);
        $this->service->delete($purchase);

        return $this->noContent();
    }
}
