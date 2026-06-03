<?php

namespace App\Domains\Purchases\Controllers;

use App\Domains\Purchases\Models\ShoppingItem;
use App\Domains\Purchases\Models\ShoppingSession;
use App\Domains\Purchases\Requests\StoreShoppingItemRequest;
use App\Domains\Purchases\Requests\UpdateShoppingItemRequest;
use App\Domains\Purchases\Resources\ShoppingItemResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShoppingItemController extends Controller
{
    public function store(StoreShoppingItemRequest $request, ShoppingSession $session): JsonResponse
    {
        $this->authorize('update', $session);

        if ($session->status !== 'active') {
            return $this->error('Não é possível adicionar itens a uma sessão finalizada', 422);
        }

        $item = ShoppingItem::create([
            'shopping_session_id' => $session->id,
            'user_id' => $request->user()->id,
            ...$request->validated(),
        ]);

        return $this->created(new ShoppingItemResource($item), 'Item adicionado');
    }

    public function update(UpdateShoppingItemRequest $request, ShoppingItem $item): JsonResponse
    {
        $this->authorize('update', $item);
        $item->update($request->validated());

        return $this->success(new ShoppingItemResource($item), 'Item atualizado');
    }

    public function destroy(Request $request, ShoppingItem $item): JsonResponse
    {
        $this->authorize('delete', $item);
        $item->delete();

        return $this->noContent();
    }
}
