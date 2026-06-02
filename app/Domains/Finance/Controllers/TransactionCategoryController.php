<?php

namespace App\Domains\Finance\Controllers;

use App\Domains\Finance\Models\TransactionCategory;
use App\Domains\Finance\Requests\StoreTransactionCategoryRequest;
use App\Domains\Finance\Requests\UpdateTransactionCategoryRequest;
use App\Domains\Finance\Resources\TransactionCategoryResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TransactionCategoryController extends Controller
{
    /** Default categories seeded for new users on first access. */
    private const DEFAULTS = [
        ['name' => 'Alimentação',  'type' => 'expense', 'icon' => 'UtensilsCrossed', 'color' => '#f97316'],
        ['name' => 'Transporte',   'type' => 'expense', 'icon' => 'Car',             'color' => '#3b82f6'],
        ['name' => 'Moradia',      'type' => 'expense', 'icon' => 'Home',            'color' => '#8b5cf6'],
        ['name' => 'Saúde',        'type' => 'expense', 'icon' => 'Heart',           'color' => '#ef4444'],
        ['name' => 'Lazer',        'type' => 'expense', 'icon' => 'Gamepad2',        'color' => '#22c55e'],
        ['name' => 'Educação',     'type' => 'expense', 'icon' => 'GraduationCap',   'color' => '#06b6d4'],
        ['name' => 'Roupas',       'type' => 'expense', 'icon' => 'ShoppingBag',     'color' => '#ec4899'],
        ['name' => 'Assinatura',   'type' => 'expense', 'icon' => 'Smartphone',      'color' => '#6b7280'],
        ['name' => 'Salário',      'type' => 'income',  'icon' => 'Briefcase',       'color' => '#22c55e'],
        ['name' => 'Freelance',    'type' => 'income',  'icon' => 'Laptop',          'color' => '#3b82f6'],
        ['name' => 'Investimento', 'type' => 'income',  'icon' => 'TrendingUp',      'color' => '#14b8a6'],
        ['name' => 'Outros',       'type' => 'income',  'icon' => 'Star',            'color' => '#6b7280'],
    ];

    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $categories = TransactionCategory::forUser($userId)->get();

        // Lazy-seed defaults only if the user has never had any category (including soft-deleted).
        // Checking withTrashed() prevents re-seeding when all categories were soft-deleted.
        if (TransactionCategory::withTrashed()->where('user_id', $userId)->doesntExist()) {
            $now = now();
            $rows = array_map(fn ($d) => array_merge($d, [
                'id' => (string) Str::uuid(),
                'user_id' => $userId,
                'is_default' => true,
                'monthly_limit' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]), self::DEFAULTS);

            TransactionCategory::insert($rows);
            $categories = TransactionCategory::forUser($userId)->get();
        }

        return $this->success(TransactionCategoryResource::collection($categories));
    }

    public function store(StoreTransactionCategoryRequest $request): JsonResponse
    {
        $category = TransactionCategory::create([...$request->validated(), 'user_id' => $request->user()->id]);

        return $this->created(new TransactionCategoryResource($category));
    }

    public function update(UpdateTransactionCategoryRequest $request, TransactionCategory $transactionCategory): JsonResponse
    {
        $this->authorize('update', $transactionCategory);
        $transactionCategory->update($request->validated());

        return $this->success(new TransactionCategoryResource($transactionCategory));
    }

    public function destroy(Request $request, TransactionCategory $transactionCategory): JsonResponse
    {
        $this->authorize('delete', $transactionCategory);
        $transactionCategory->delete();

        return $this->noContent();
    }
}
