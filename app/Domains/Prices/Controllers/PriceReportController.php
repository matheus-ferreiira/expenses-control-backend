<?php

namespace App\Domains\Prices\Controllers;

use App\Domains\Prices\Models\PriceProduct;
use App\Domains\Prices\Services\PriceReportService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PriceReportController extends Controller
{
    public function __construct(
        private readonly PriceReportService $service,
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        return $this->success($this->service->getDashboard($request->user()));
    }

    public function priceHistory(Request $request, PriceProduct $product): JsonResponse
    {
        $this->authorize('view', $product);

        $request->validate([
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date'],
            'store_id' => ['sometimes', 'uuid'],
        ]);

        return $this->success($this->service->getPriceHistory(
            $product,
            $request->only(['date_from', 'date_to', 'store_id']),
        ));
    }

    public function compare(Request $request): JsonResponse
    {
        $request->validate([
            'product_ids' => ['required', 'array', 'min:2', 'max:4'],
            'product_ids.*' => [
                'required',
                'uuid',
                'distinct',
                Rule::exists('price_products', 'id')
                    ->where('user_id', $request->user()->id)
                    ->whereNull('deleted_at'),
            ],
        ]);

        return $this->success($this->service->compare($request->user(), $request->input('product_ids')));
    }

    public function patrimony(Request $request): JsonResponse
    {
        return $this->success($this->service->getPatrimony($request->user()));
    }
}
