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
use Illuminate\Support\Facades\DB;

class ShoppingItemController extends Controller
{
    /**
     * Itens mais frequentes do histórico do usuário — combustível do
     * autocomplete e dos chips de 1 toque. Inclui itens de listas antigas
     * (soft-deleted): o nome ainda é sinal de recorrência. A collation *_ci
     * do MySQL agrupa "Fósforo"/"fosforo" como o mesmo item.
     */
    public function frequent(Request $request): JsonResponse
    {
        $frequent = ShoppingItem::withTrashed()
            ->where('user_id', $request->user()->id)
            ->select('name', DB::raw('COUNT(*) as uses'), DB::raw('MAX(created_at) as last_used'))
            ->groupBy('name')
            ->orderByDesc('uses')
            ->orderByDesc('last_used')
            ->limit(20)
            ->get()
            ->map(fn ($row) => ['name' => $row->name, 'uses' => (int) $row->uses]);

        return $this->success($frequent);
    }

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
