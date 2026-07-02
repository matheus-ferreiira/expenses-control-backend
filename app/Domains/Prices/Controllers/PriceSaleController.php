<?php

namespace App\Domains\Prices\Controllers;

use App\Domains\Prices\DTOs\PriceSaleDTO;
use App\Domains\Prices\Models\PriceSale;
use App\Domains\Prices\Requests\StorePriceSaleRequest;
use App\Domains\Prices\Requests\UpdatePriceSaleRequest;
use App\Domains\Prices\Resources\PriceSaleResource;
use App\Domains\Prices\Services\PriceSaleService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PriceSaleController extends Controller
{
    public function __construct(
        private readonly PriceSaleService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->success(PriceSaleResource::collection($this->service->list($request->user())));
    }

    public function store(StorePriceSaleRequest $request): JsonResponse
    {
        $sale = $this->service->create($request->user(), PriceSaleDTO::fromArray($request->validated()));

        return $this->created(new PriceSaleResource($sale->load('purchase.product')), 'Sale created');
    }

    public function show(Request $request, PriceSale $sale): JsonResponse
    {
        $this->authorize('view', $sale);

        return $this->success(new PriceSaleResource($sale->load('purchase.product')));
    }

    public function update(UpdatePriceSaleRequest $request, PriceSale $sale): JsonResponse
    {
        $this->authorize('update', $sale);

        $data = array_merge([
            'purchase_id' => $sale->purchase_id,
            'sale_price' => (float) $sale->sale_price,
            'sold_at' => $sale->sold_at->toDateString(),
            'notes' => $sale->notes,
        ], $request->validated());

        $updated = $this->service->update($sale, PriceSaleDTO::fromArray($data));

        return $this->success(new PriceSaleResource($updated->load('purchase.product')), 'Sale updated');
    }

    public function destroy(Request $request, PriceSale $sale): JsonResponse
    {
        $this->authorize('delete', $sale);
        $this->service->delete($sale);

        return $this->noContent();
    }
}
