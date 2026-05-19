<?php

namespace App\Domains\Finance\Controllers;

use App\Domains\Finance\Models\BankAccount;
use App\Domains\Finance\Models\CreditCard;
use App\Domains\Finance\Requests\StoreCreditCardRequest;
use App\Domains\Finance\Requests\UpdateCreditCardRequest;
use App\Domains\Finance\Resources\CreditCardResource;
use App\Domains\Finance\Services\BankAccountService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreditCardController extends Controller
{
    public function __construct(
        private readonly BankAccountService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $cards = CreditCard::forUser($request->user()->id)->with('bankAccount')->get();

        return $this->success(CreditCardResource::collection($cards));
    }

    public function store(StoreCreditCardRequest $request, BankAccount $bankAccount): JsonResponse
    {
        $this->authorize('update', $bankAccount);
        $card = $this->service->createCreditCard($bankAccount, $request->validated());

        return $this->created(new CreditCardResource($card->load('bankAccount')), 'Card created');
    }

    public function show(Request $request, CreditCard $creditCard): JsonResponse
    {
        $this->authorize('view', $creditCard);

        return $this->success(new CreditCardResource($creditCard));
    }

    public function update(UpdateCreditCardRequest $request, CreditCard $creditCard): JsonResponse
    {
        $this->authorize('update', $creditCard);
        $card = $this->service->updateCreditCard($creditCard, $request->validated());

        return $this->success(new CreditCardResource($card), 'Card updated');
    }

    public function destroy(Request $request, CreditCard $creditCard): JsonResponse
    {
        $this->authorize('delete', $creditCard);
        $this->service->deleteCreditCard($creditCard);

        return $this->noContent();
    }
}
