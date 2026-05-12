<?php

namespace App\Domains\Finance\Controllers;

use App\Domains\Finance\DTOs\TransactionDTO;
use App\Domains\Finance\Models\Transaction;
use App\Domains\Finance\Requests\StoreTransactionRequest;
use App\Domains\Finance\Requests\TransactionFilterRequest;
use App\Domains\Finance\Requests\UpdateTransactionRequest;
use App\Domains\Finance\Resources\TransactionResource;
use App\Domains\Finance\Services\TransactionService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function __construct(
        private readonly TransactionService $service,
    ) {}

    public function index(TransactionFilterRequest $request): JsonResponse
    {
        $transactions = $this->service->list($request->user(), $request->validated());
        return $this->paginatedSuccess(TransactionResource::collection($transactions));
    }

    public function store(StoreTransactionRequest $request): JsonResponse
    {
        $result = $this->service->create($request->user(), TransactionDTO::fromArray($request->validated()));

        if (is_array($result)) {
            return $this->created(TransactionResource::collection(collect($result)), 'Installments created');
        }

        return $this->created(new TransactionResource($result), 'Transaction created');
    }

    public function show(Request $request, Transaction $transaction): JsonResponse
    {
        $this->authorize('view', $transaction);
        return $this->success(new TransactionResource($transaction->load(['category', 'account', 'card'])));
    }

    public function update(UpdateTransactionRequest $request, Transaction $transaction): JsonResponse
    {
        $this->authorize('update', $transaction);
        $transaction = $this->service->update($transaction, TransactionDTO::fromArray($request->validated()));
        return $this->success(new TransactionResource($transaction), 'Transaction updated');
    }

    public function destroy(Request $request, Transaction $transaction): JsonResponse
    {
        $this->authorize('delete', $transaction);
        $this->service->delete($transaction);
        return $this->noContent();
    }
}
