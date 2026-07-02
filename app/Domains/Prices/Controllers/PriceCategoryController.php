<?php

namespace App\Domains\Prices\Controllers;

use App\Domains\Prices\DTOs\PriceCategoryDTO;
use App\Domains\Prices\Models\PriceCategory;
use App\Domains\Prices\Requests\StorePriceCategoryRequest;
use App\Domains\Prices\Requests\UpdatePriceCategoryRequest;
use App\Domains\Prices\Resources\PriceCategoryResource;
use App\Domains\Prices\Services\PriceCategoryService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PriceCategoryController extends Controller
{
    public function __construct(
        private readonly PriceCategoryService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->success(PriceCategoryResource::collection($this->service->list($request->user())));
    }

    public function store(StorePriceCategoryRequest $request): JsonResponse
    {
        $category = $this->service->create($request->user(), PriceCategoryDTO::fromArray($request->validated()));

        return $this->created(new PriceCategoryResource($category), 'Category created');
    }

    public function show(Request $request, PriceCategory $category): JsonResponse
    {
        $this->authorize('view', $category);

        return $this->success(new PriceCategoryResource($category));
    }

    public function update(UpdatePriceCategoryRequest $request, PriceCategory $category): JsonResponse
    {
        $this->authorize('update', $category);

        $data = array_merge([
            'name' => $category->name,
            'icon' => $category->icon,
        ], $request->validated());

        $updated = $this->service->update($category, PriceCategoryDTO::fromArray($data));

        return $this->success(new PriceCategoryResource($updated), 'Category updated');
    }

    public function destroy(Request $request, PriceCategory $category): JsonResponse
    {
        $this->authorize('delete', $category);
        $this->service->delete($category);

        return $this->noContent();
    }
}
