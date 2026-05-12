<?php

namespace App\Domains\Finance\Controllers;

use App\Domains\Finance\Models\TransactionCategory;
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

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', 'in:income,expense'],
            'icon' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:20'],
        ]);

        $category = TransactionCategory::create([...$data, 'user_id' => $request->user()->id]);
        return $this->created(new TransactionCategoryResource($category));
    }

    public function update(Request $request, TransactionCategory $transactionCategory): JsonResponse
    {
        abort_unless($transactionCategory->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'icon' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:20'],
        ]);

        $transactionCategory->update($data);
        return $this->success(new TransactionCategoryResource($transactionCategory));
    }

    public function destroy(Request $request, TransactionCategory $transactionCategory): JsonResponse
    {
        abort_unless($transactionCategory->user_id === $request->user()->id, 403);
        $transactionCategory->delete();
        return $this->noContent();
    }
}
