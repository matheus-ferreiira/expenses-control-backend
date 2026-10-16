<?php

namespace App\Domains\Finance\Controllers;

use App\Domains\Finance\DTOs\TransactionDTO;
use App\Domains\Finance\Models\Transaction;
use App\Domains\Finance\Requests\StoreTransactionRequest;
use App\Domains\Finance\Requests\TransactionFilterRequest;
use App\Domains\Finance\Requests\UpdateTransactionRequest;
use App\Domains\Finance\Resources\TransactionResource;
use App\Domains\Finance\Services\TransactionService;
use App\Domains\Finance\Services\UserClock;
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
        $validated = $request->validated();
        $scope = $validated['scope'] ?? 'this_only';
        $transaction = $this->service->update($transaction, TransactionDTO::fromArray($validated), $scope);

        return $this->success(new TransactionResource($transaction), 'Transaction updated');
    }

    public function confirm(Request $request, Transaction $transaction): JsonResponse
    {
        $this->authorize('update', $transaction);
        // What really happened; both default to the forecast. The action refuses a future date.
        $validated = $request->validate([
            'amount' => ['sometimes', 'numeric', 'min:0.01', 'max:9999999999'],
            'date' => ['sometimes', 'date_format:Y-m-d'],
        ]);
        $transaction = $this->service->confirm($transaction, isset($validated['amount']) ? (float) $validated['amount'] : null, $validated['date'] ?? null);

        return $this->success(new TransactionResource($transaction), 'Transaction confirmed');
    }

    /** Confirm several pending transactions at once: only the ones already due, each at its forecast. */
    public function confirmBatch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['uuid'],
        ]);

        // Ownership enforced by the forUser scope — foreign ids are silently ignored.
        $today = UserClock::today($request->user());
        $transactions = Transaction::forUser($request->user()->id)
            ->whereIn('id', $validated['ids'])
            ->get();
        [$due, $future] = $transactions->partition(fn (Transaction $t) => $t->transaction_date->toDateString() <= $today);

        $confirmed = $due->map(fn (Transaction $t) => $this->service->confirm($t))->values();
        $message = "{$confirmed->count()} transações confirmadas".($future->isNotEmpty() ? " · {$future->count()} ainda não venceram e ficaram pendentes" : '');

        return $this->success(TransactionResource::collection($confirmed), $message);
    }

    public function destroy(Request $request, Transaction $transaction): JsonResponse
    {
        $this->authorize('delete', $transaction);
        $validated = $request->validate(['scope' => ['sometimes', 'in:this_only,this_and_future']]);
        $this->service->delete($transaction, $validated['scope'] ?? 'this_only');

        return $this->noContent();
    }
}
