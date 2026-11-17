<?php

namespace App\Domains\BankSync\Controllers;

use App\Domains\BankSync\Models\BankConnection;
use App\Domains\BankSync\Models\BankConnectionAccount;
use App\Domains\BankSync\Models\BankConnectionTransaction;
use App\Domains\BankSync\Requests\ApplyReviewRequest;
use App\Domains\BankSync\Requests\ConnectTokenRequest;
use App\Domains\BankSync\Requests\LinkBankAccountRequest;
use App\Domains\BankSync\Requests\StoreBankConnectionRequest;
use App\Domains\BankSync\Requests\UpdateBankConnectionRequest;
use App\Domains\BankSync\Resources\BankConnectionResource;
use App\Domains\BankSync\Resources\BankConnectionTransactionResource;
use App\Domains\BankSync\Resources\BankReviewItemResource;
use App\Domains\BankSync\Services\BankReconciler;
use App\Domains\BankSync\Services\BankSyncService;
use App\Domains\BankSync\Services\PluggyClient;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BankConnectionController extends Controller
{
    public function __construct(
        private readonly BankSyncService $sync,
        private readonly PluggyClient $pluggy,
        private readonly BankReconciler $reconciler,
    ) {}

    public function status(Request $request): JsonResponse
    {
        return $this->success([
            'configured' => $this->pluggy->configured(),
            'include_sandbox' => (bool) config('services.pluggy.include_sandbox'),
            // Bank transactions waiting in "A lançar" (Visão Geral shows a card when > 0)
            'to_review_count' => BankConnectionTransaction::where('user_id', $request->user()->id)->where('review_status', BankConnectionTransaction::NEW)->count(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $overview = $this->sync->overview($request->user());

        return $this->success([
            'connections' => BankConnectionResource::collection($overview['connections'])->resolve(),
            'totals' => $overview['totals'],
        ]);
    }

    public function connectToken(ConnectTokenRequest $request): JsonResponse
    {
        $connection = null;
        if ($id = $request->validated('connection_id')) {
            $connection = BankConnection::findOrFail($id);
            $this->authorize('update', $connection);
        }

        return $this->success([
            'access_token' => $this->sync->connectToken($request->user(), $connection),
            // The widget opens in update mode for this item (renewing an expired authorisation)
            'update_item_id' => $connection?->external_item_id,
        ]);
    }

    public function store(StoreBankConnectionRequest $request): JsonResponse
    {
        $connection = $this->sync->register($request->user(), $request->validated('item_id'));

        return $this->created(new BankConnectionResource($connection), 'Banco conectado');
    }

    public function update(UpdateBankConnectionRequest $request, BankConnection $bankConnection): JsonResponse
    {
        $this->authorize('update', $bankConnection);

        return $this->success(new BankConnectionResource($this->sync->rename($bankConnection, $request->validated('name'))));
    }

    public function sync(BankConnection $bankConnection): JsonResponse
    {
        $this->authorize('update', $bankConnection);

        return $this->success(new BankConnectionResource($this->sync->sync($bankConnection)));
    }

    public function destroy(BankConnection $bankConnection): JsonResponse
    {
        $this->authorize('delete', $bankConnection);
        $this->sync->disconnect($bankConnection);

        return $this->noContent();
    }

    /** Links a bank account (or card) to a ledger account (or card); null unlinks. */
    public function link(LinkBankAccountRequest $request, BankConnectionAccount $bankConnectionAccount): JsonResponse
    {
        $this->authorize('update', $bankConnectionAccount);
        $ledgerId = $bankConnectionAccount->isCredit() ? $request->validated('credit_card_id') : $request->validated('bank_account_id');
        $this->reconciler->link($bankConnectionAccount, $ledgerId, $request->validated('import_from'));

        return $this->success(new BankConnectionResource($this->sync->loadAccounts($bankConnectionAccount->bankConnection)));
    }

    /** "A lançar": bank transactions waiting for a decision, with suggestions. */
    public function review(Request $request): JsonResponse
    {
        $items = $this->reconciler->reviewItems($request->user());

        return $this->success([
            'items' => BankReviewItemResource::collection($items)->resolve(),
            'count' => count($items),
        ]);
    }

    public function applyReview(ApplyReviewRequest $request): JsonResponse
    {
        $result = $this->reconciler->apply($request->user(), $request->validated('items'));

        return $this->success($result + ['remaining' => BankConnectionTransaction::where('user_id', $request->user()->id)->where('review_status', BankConnectionTransaction::NEW)->count()]);
    }

    public function undoReview(BankConnectionTransaction $bankConnectionTransaction): JsonResponse
    {
        $this->authorize('update', $bankConnectionTransaction);
        $this->reconciler->undo($bankConnectionTransaction);

        return $this->success(new BankConnectionTransactionResource($bankConnectionTransaction->refresh()));
    }

    public function transactions(Request $request, BankConnectionAccount $bankConnectionAccount): JsonResponse
    {
        $this->authorize('view', $bankConnectionAccount);
        $page = $bankConnectionAccount->transactions()
            ->orderByDesc('date')->orderByDesc('created_at')->orderBy('id')
            ->paginate(min(max((int) $request->integer('per_page', 50), 1), 100));

        return $this->paginatedSuccess(BankConnectionTransactionResource::collection($page));
    }
}
