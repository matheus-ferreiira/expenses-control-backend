<?php

namespace App\Domains\Finance\Controllers;

use App\Domains\Finance\Models\TransactionTag;
use App\Domains\Finance\Requests\StoreTransactionTagRequest;
use App\Domains\Finance\Requests\UpdateTransactionTagRequest;
use App\Domains\Finance\Resources\TransactionTagResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionTagController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tags = TransactionTag::forUser($request->user()->id)
            ->orderBy('name')
            ->get();

        return $this->success(TransactionTagResource::collection($tags));
    }

    public function store(StoreTransactionTagRequest $request): JsonResponse
    {
        $tag = TransactionTag::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
            'color' => $request->validated()['color'] ?? '#6b7280',
        ]);

        return $this->created(new TransactionTagResource($tag));
    }

    public function update(UpdateTransactionTagRequest $request, TransactionTag $transactionTag): JsonResponse
    {
        $this->authorize('update', $transactionTag);
        $transactionTag->update($request->validated());

        return $this->success(new TransactionTagResource($transactionTag));
    }

    public function destroy(Request $request, TransactionTag $transactionTag): JsonResponse
    {
        $this->authorize('delete', $transactionTag);
        $transactionTag->delete();

        return $this->noContent();
    }
}
