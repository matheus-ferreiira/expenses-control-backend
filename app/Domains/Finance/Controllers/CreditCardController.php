<?php

namespace App\Domains\Finance\Controllers;

use App\Domains\Finance\Actions\PayCardStatementAction;
use App\Domains\Finance\Enums\TransactionType;
use App\Domains\Finance\Models\BankAccount;
use App\Domains\Finance\Models\CreditCard;
use App\Domains\Finance\Models\Transaction;
use App\Domains\Finance\Requests\PayCardStatementRequest;
use App\Domains\Finance\Requests\StandaloneStoreCreditCardRequest;
use App\Domains\Finance\Requests\StoreCreditCardRequest;
use App\Domains\Finance\Requests\UpdateCreditCardRequest;
use App\Domains\Finance\Resources\CreditCardResource;
use App\Domains\Finance\Resources\TransactionResource;
use App\Domains\Finance\Services\BankAccountService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreditCardController extends Controller
{
    public function __construct(
        private readonly BankAccountService $service,
        private readonly PayCardStatementAction $payStatement,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $cards = CreditCard::forUser($request->user()->id)->with('bankAccount')->get();

        return $this->success(CreditCardResource::collection($cards));
    }

    public function store(StoreCreditCardRequest $request, BankAccount $bankAccount): JsonResponse
    {
        $this->authorize('update', $bankAccount);
        $data = collect($request->validated())->except('bank_account_id')->all();
        $card = $this->service->createCreditCard($bankAccount, $data);

        return $this->created(new CreditCardResource($card->load('bankAccount')), 'Card created');
    }

    public function storeStandalone(StandaloneStoreCreditCardRequest $request): JsonResponse
    {
        $bankAccount = BankAccount::forUser($request->user()->id)
            ->findOrFail($request->validated()['bank_account_id']);
        $this->authorize('update', $bankAccount);
        $data = collect($request->validated())->except('bank_account_id')->all();
        $card = $this->service->createCreditCard($bankAccount, $data);

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

    /** Settle a statement: transfer from a bank account to this card. */
    public function payStatement(PayCardStatementRequest $request, CreditCard $creditCard): JsonResponse
    {
        $this->authorize('update', $creditCard);
        $validated = $request->validated();

        $transaction = $this->payStatement->execute(
            $request->user(),
            $creditCard,
            $validated['account_id'],
            (float) $validated['amount'],
            $validated['statement_month'],
            $validated['payment_date'] ?? null,
        );

        return $this->created(new TransactionResource($transaction), 'Statement paid');
    }

    /** Payment (if any) that settled the given statement month of this card. */
    public function statementPayment(Request $request, CreditCard $creditCard): JsonResponse
    {
        $this->authorize('view', $creditCard);
        $request->validate(['statement_month' => ['required', 'date_format:Y-m']]);

        $payment = Transaction::query()
            ->where('card_id', $creditCard->id)
            ->where('statement_month', $request->query('statement_month'))
            ->where('type', TransactionType::Transfer->value)
            ->with('account')
            ->first();

        return $this->success($payment ? new TransactionResource($payment) : null);
    }
}
