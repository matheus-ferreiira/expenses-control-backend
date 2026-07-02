<?php

namespace App\Domains\Prices\Controllers;

use App\Domains\Prices\DTOs\PriceRecordDTO;
use App\Domains\Prices\Models\PriceRecord;
use App\Domains\Prices\Requests\StorePriceRecordRequest;
use App\Domains\Prices\Requests\UpdatePriceRecordRequest;
use App\Domains\Prices\Resources\PriceRecordResource;
use App\Domains\Prices\Services\PriceRecordService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PriceRecordController extends Controller
{
    public function __construct(
        private readonly PriceRecordService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 50), 200);
        $records = $this->service->list(
            $request->user(),
            $request->only(['product_id', 'store_id', 'date_from', 'date_to']),
            max($perPage, 1),
        );

        return $this->paginatedSuccess(PriceRecordResource::collection($records));
    }

    public function store(StorePriceRecordRequest $request): JsonResponse
    {
        $record = $this->service->create($request->user(), PriceRecordDTO::fromArray($request->validated()));

        return $this->created(new PriceRecordResource($record->load(['product:id,name,target_price', 'store:id,name'])), 'Price record created');
    }

    public function show(Request $request, PriceRecord $priceRecord): JsonResponse
    {
        $this->authorize('view', $priceRecord);

        return $this->success(new PriceRecordResource($priceRecord->load(['product:id,name,target_price', 'store:id,name'])));
    }

    public function update(UpdatePriceRecordRequest $request, PriceRecord $priceRecord): JsonResponse
    {
        $this->authorize('update', $priceRecord);

        $data = array_merge([
            'product_id' => $priceRecord->product_id,
            'store_id' => $priceRecord->store_id,
            'price' => (float) $priceRecord->price,
            'recorded_at' => $priceRecord->recorded_at->toDateString(),
            'url' => $priceRecord->url,
            'notes' => $priceRecord->notes,
        ], $request->validated());

        $updated = $this->service->update($priceRecord, PriceRecordDTO::fromArray($data));

        return $this->success(new PriceRecordResource($updated->load(['product:id,name,target_price', 'store:id,name'])), 'Price record updated');
    }

    public function destroy(Request $request, PriceRecord $priceRecord): JsonResponse
    {
        $this->authorize('delete', $priceRecord);
        $this->service->delete($priceRecord);

        return $this->noContent();
    }
}
