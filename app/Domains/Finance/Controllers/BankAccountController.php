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

    public function show(Request $request, BankAccount $bankAccount): JsonResponse
    {
        $this->authorize('view', $bankAccount);

        return $this->success(new BankAccountResource($bankAccount->load('creditCards')));
    }

    public function update(UpdateBankAccountRequest $request, BankAccount $bankAccount): JsonResponse
    {
        $this->authorize('update', $bankAccount);
        $account = $this->service->update($bankAccount, BankAccountDTO::fromArray($request->validated()));

        return $this->success(new BankAccountResource($account), 'Account updated');
    }

    public function destroy(Request $request, BankAccount $bankAccount): JsonResponse
    {
        $this->authorize('delete', $bankAccount);
        $this->service->delete($bankAccount);

        return $this->noContent();
    }

    public function consolidatedBalance(Request $request): JsonResponse
    {
        return $this->success($this->service->getConsolidatedBalance($request->user()));
    }
}
