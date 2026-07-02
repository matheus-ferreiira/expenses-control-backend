<?php

namespace App\Domains\Prices\Controllers;

use App\Domains\Prices\DTOs\PriceProductDTO;
use App\Domains\Prices\Models\PriceProduct;
use App\Domains\Prices\Requests\StorePriceProductRequest;
use App\Domains\Prices\Requests\UpdatePriceProductRequest;
use App\Domains\Prices\Resources\PriceProductResource;
use App\Domains\Prices\Services\PriceProductService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PriceProductController extends Controller
{
    public function __construct(
        private readonly PriceProductService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $products = $this->service->list($request->user(), $request->only(['status', 'category_id', 'search']));

        return $this->success(PriceProductResource::collection($products));
    }

    public function store(StorePriceProductRequest $request): JsonResponse
    {
        $product = $this->service->create($request->user(), PriceProductDTO::fromArray($request->validated()));

        return $this->created(new PriceProductResource($product->load('category')), 'Product created');
    }

    public function show(Request $request, PriceProduct $product): JsonResponse
    {
        $this->authorize('view', $product);

        return $this->success(new PriceProductResource($this->service->find($product)));
    }

    public function update(UpdatePriceProductRequest $request, PriceProduct $product): JsonResponse
    {
        $this->authorize('update', $product);

        $data = array_merge($this->currentData($product), $request->validated());
        $updated = $this->service->update($product, PriceProductDTO::fromArray($data));

        return $this->success(new PriceProductResource($updated->load('category')), 'Product updated');
    }

    public function destroy(Request $request, PriceProduct $product): JsonResponse
    {
        $this->authorize('delete', $product);
        $this->service->delete($product);

        return $this->noContent();
    }

    private function currentData(PriceProduct $product): array
    {
        return [
            'category_id' => $product->category_id,
            'name' => $product->name,
            'brand' => $product->brand,
            'model' => $product->model,
            'specs' => $product->specs,
            'notes' => $product->notes,
            'target_price' => $product->target_price !== null ? (float) $product->target_price : null,
            'launch_price' => $product->launch_price !== null ? (float) $product->launch_price : null,
            'status' => $product->status->value,
        ];
    }
}
