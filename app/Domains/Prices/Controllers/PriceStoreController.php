<?php

namespace App\Domains\Prices\Controllers;

use App\Domains\Prices\DTOs\PriceStoreDTO;
use App\Domains\Prices\Models\PriceStore;
use App\Domains\Prices\Requests\StorePriceStoreRequest;
use App\Domains\Prices\Requests\UpdatePriceStoreRequest;
use App\Domains\Prices\Resources\PriceStoreResource;
use App\Domains\Prices\Services\PriceStoreService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PriceStoreController extends Controller
{
    public function __construct(
        private readonly PriceStoreService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->success(PriceStoreResource::collection($this->service->list($request->user())));
    }

    public function store(StorePriceStoreRequest $request): JsonResponse
    {
        $store = $this->service->create($request->user(), PriceStoreDTO::fromArray($request->validated()));

        return $this->created(new PriceStoreResource($store), 'Store created');
    }

    public function show(Request $request, PriceStore $store): JsonResponse
    {
        $this->authorize('view', $store);

        return $this->success(new PriceStoreResource($store));
    }

    public function update(UpdatePriceStoreRequest $request, PriceStore $store): JsonResponse
    {
        $this->authorize('update', $store);

        $data = array_merge([
            'name' => $store->name,
            'website_url' => $store->website_url,
            'notes' => $store->notes,
        ], $request->validated());

        $updated = $this->service->update($store, PriceStoreDTO::fromArray($data));

        return $this->success(new PriceStoreResource($updated), 'Store updated');
    }

    public function destroy(Request $request, PriceStore $store): JsonResponse
    {
        $this->authorize('delete', $store);
        $this->service->delete($store);

        return $this->noContent();
    }
}
