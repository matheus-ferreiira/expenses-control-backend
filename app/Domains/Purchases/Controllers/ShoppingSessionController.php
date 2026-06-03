<?php

namespace App\Domains\Purchases\Controllers;

use App\Domains\Finance\Models\Transaction;
use App\Domains\Purchases\Models\ShoppingSession;
use App\Domains\Purchases\Requests\FinishShoppingSessionRequest;
use App\Domains\Purchases\Requests\StoreShoppingSessionRequest;
use App\Domains\Purchases\Resources\ShoppingSessionResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShoppingSessionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $sessions = ShoppingSession::forUser($request->user()->id)
            ->with('items')
            ->orderByDesc('created_at')
            ->get();

        return $this->success(ShoppingSessionResource::collection($sessions));
    }

    public function store(StoreShoppingSessionRequest $request): JsonResponse
    {
        $session = ShoppingSession::create([
            'user_id' => $request->user()->id,
            'title' => $request->title,
        ]);

        return $this->created(new ShoppingSessionResource($session->load('items')), 'Sessão criada');
    }

    public function show(Request $request, ShoppingSession $session): JsonResponse
    {
        $this->authorize('view', $session);

        return $this->success(new ShoppingSessionResource($session->load('items')));
    }

    public function finish(FinishShoppingSessionRequest $request, ShoppingSession $session): JsonResponse
    {
        $this->authorize('finish', $session);

        if ($session->status !== 'active') {
            return $this->error('Sessão já foi finalizada', 422);
        }

        $session->update([
            'status' => 'finished',
            'total' => $request->total,
            'finished_at' => now(),
        ]);

        if ($request->filled('bank_account_id') || $request->filled('credit_card_id')) {
            $transaction = Transaction::create([
                'user_id' => $request->user()->id,
                'account_id' => $request->bank_account_id,
                'card_id' => $request->credit_card_id,
                'category_id' => $request->category_id,
                'type' => 'expense',
                'amount' => $request->total,
                'description' => $session->title,
                'transaction_date' => now()->toDateString(),
            ]);

            $session->update(['transaction_id' => $transaction->id]);
        }

        return $this->success(new ShoppingSessionResource($session->load('items')), 'Compra finalizada');
    }

    public function destroy(Request $request, ShoppingSession $session): JsonResponse
    {
        $this->authorize('delete', $session);

        if ($session->status !== 'active') {
            return $this->error('Apenas sessões ativas podem ser excluídas', 422);
        }

        $session->delete();

        return $this->noContent();
    }
}
