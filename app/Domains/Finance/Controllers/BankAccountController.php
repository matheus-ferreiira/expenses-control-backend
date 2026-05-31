<?php

namespace App\Domains\Finance\Controllers;

use App\Domains\Finance\DTOs\BankAccountDTO;
use App\Domains\Finance\Models\BankAccount;
use App\Domains\Finance\Requests\StoreBankAccountRequest;
use App\Domains\Finance\Requests\UpdateBankAccountRequest;
use App\Domains\Finance\Resources\BankAccountResource;
use App\Domains\Finance\Services\BankAccountService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BankAccountController extends Controller
{
    public function __construct(
        private readonly BankAccountService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $accounts = $this->service->list($request->user());

        return $this->success(BankAccountResource::collection($accounts));
    }

    public function store(StoreBankAccountRequest $request): JsonResponse
    {
        $account = $this->service->create($request->user(), BankAccountDTO::fromArray($request->validated()));

        return $this->created(new BankAccountResource($account), 'Account created');
    }

    public function show(Request $request, BankAccount $account): JsonResponse
    {
        $this->authorize('view', $account);

        return $this->success(new BankAccountResource($account->load('creditCards')));
    }

    public function update(UpdateBankAccountRequest $request, BankAccount $account): JsonResponse
    {
        $this->authorize('update', $account);

        $validated = $request->validated();
        // Preserve the current balance if not explicitly provided in the request
        if (! array_key_exists('balance', $validated)) {
            $validated['balance'] = (float) $account->balance;
        }

        $updated = $this->service->update($account, BankAccountDTO::fromArray($validated));

        return $this->success(new BankAccountResource($updated), 'Account updated');
    }

    public function destroy(Request $request, BankAccount $account): JsonResponse
    {
        $this->authorize('delete', $account);
        $this->service->delete($account);

        return $this->noContent();
    }

    public function consolidatedBalance(Request $request): JsonResponse
    {
        return $this->success($this->service->getConsolidatedBalance($request->user()));
    }

    public function historicalBalance(Request $request): JsonResponse
    {
        $month = (int) $request->query('month', date('n'));
        $year = (int) $request->query('year', date('Y'));

        return $this->success($this->service->getHistoricalBalance($request->user(), $year, $month));
    }
}
