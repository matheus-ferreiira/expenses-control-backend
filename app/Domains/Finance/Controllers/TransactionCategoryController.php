<?php

namespace App\Domains\Finance\Controllers;

use App\Domains\Finance\Models\TransactionCategory;
use App\Domains\Finance\Requests\StoreTransactionCategoryRequest;
use App\Domains\Finance\Requests\UpdateTransactionCategoryRequest;
use App\Domains\Finance\Resources\TransactionCategoryResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $categories = TransactionCategory::forUser($request->user()->id)->get();

        return $this->success(TransactionCategoryResource::collection($categories));
    }

    public function store(StoreTransactionCategoryRequest $request): JsonResponse
    {
        $category = TransactionCategory::create([...$request->validated(), 'user_id' => $request->user()->id]);

        return $this->created(new TransactionCategoryResource($category));
    }

    public function update(UpdateTransactionCategoryRequest $request, TransactionCategory $transactionCategory): JsonResponse
    {
        $this->authorize('update', $transactionCategory);
        $transactionCategory->update($request->validated());

        return $this->success(new TransactionCategoryResource($transactionCategory));
    }

    public function destroy(Request $request, TransactionCategory $transactionCategory): JsonResponse
    {
        $this->authorize('delete', $transactionCategory);
        $transactionCategory->delete();

        return $this->noContent();
    }
}
