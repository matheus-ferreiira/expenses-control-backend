<?php

namespace App\Domains\Prices\Services;

use App\Domains\Prices\Enums\GoalStatus;
use App\Domains\Prices\Enums\ProductStatus;
use App\Domains\Prices\Models\PriceProduct;
use App\Domains\Prices\Models\PricePurchase;
use App\Domains\Prices\Models\PriceRecord;
use App\Domains\Prices\Models\PriceSale;
use App\Domains\Prices\Models\PriceStore;
use App\Domains\Prices\Resources\PriceRecordResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

final class PriceReportService
{
    public function getDashboard(User $user): array
    {
        $statusCounts = PriceProduct::forUser($user->id)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $counts = [
            'products_total' => (int) $statusCounts->sum(),
            'products_tracking' => (int) ($statusCounts[ProductStatus::Tracking->value] ?? 0),
            'products_purchased' => (int) ($statusCounts[ProductStatus::Purchased->value] ?? 0),
            'products_discarded' => (int) ($statusCounts[ProductStatus::Discarded->value] ?? 0),
            'records_total' => PriceRecord::forUser($user->id)->count(),
            'stores_total' => PriceStore::forUser($user->id)->count(),
        ];

        $invested = (float) PricePurchase::forUser($user->id)->sum('price_paid');
        $recovered = (float) PriceSale::forUser($user->id)->sum('sale_price');

        $trackedProducts = PriceProduct::forUser($user->id)
            ->tracking()
            ->with('latestPriceRecord')
            ->withMin('priceRecords', 'price')
            ->withMax('priceRecords', 'price')
            ->withAvg('priceRecords', 'price')
            ->withCount('priceRecords')
            ->orderBy('name')
            ->get()
            ->map(fn (PriceProduct $product) => $this->trackedProductSummary($product))
            ->sortBy(fn (array $item) => match ($item['goal_status']) {
                GoalStatus::Excellent->value => 0,
                GoalStatus::Near->value => 1,
                GoalStatus::Above->value => 2,
                default => 3,
            })
            ->values()
            ->all();

        $recentRecords = PriceRecord::forUser($user->id)
            ->with(['product:id,name,target_price', 'store:id,name'])
            ->orderByDesc('recorded_at')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return [
            'counts' => $counts,
            'patrimony_snapshot' => [
                'invested' => $invested,
                'recovered' => $recovered,
                'net_cost' => round($invested - $recovered, 2),
            ],
            'tracked_products' => $trackedProducts,
            'recent_records' => PriceRecordResource::collection($recentRecords)->resolve(),
        ];
    }

    public function getPriceHistory(PriceProduct $product, array $filters = []): array
    {
        $query = $product->priceRecords()->with('store:id,name');

        if (! empty($filters['store_id'])) {
            $query->where('store_id', $filters['store_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('recorded_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('recorded_at', '<=', $filters['date_to']);
        }

        $records = $query
            ->orderBy('recorded_at')
            ->orderBy('created_at')
            ->get();

        $launchPrice = $product->launch_price !== null ? (float) $product->launch_price : null;
        $stats = $this->buildStats($records);
        $stats['savings_vs_launch'] = ($launchPrice !== null && $stats['min'] !== null)
            ? round($launchPrice - $stats['min'], 2)
            : null;

        $series = $records
            ->groupBy('store_id')
            ->map(fn (Collection $group) => [
                'store' => [
                    'id' => $group->first()->store->id,
                    'name' => $group->first()->store->name,
                ],
                'points' => $group->map(fn (PriceRecord $record) => [
                    'date' => $record->recorded_at->toDateString(),
                    'price' => (float) $record->price,
                ])->values()->all(),
            ])
            ->values()
            ->all();

        return [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'target_price' => $product->target_price !== null ? (float) $product->target_price : null,
                'launch_price' => $launchPrice,
            ],
            'stats' => $stats,
            'series' => $series,
        ];
    }

    public function compare(User $user, array $productIds): array
    {
        $products = PriceProduct::forUser($user->id)
            ->whereIn('id', $productIds)
            ->with(['priceRecords' => fn ($q) => $q->orderBy('recorded_at')->orderBy('created_at')])
            ->get()
            ->sortBy(fn (PriceProduct $product) => array_search($product->id, $productIds, true))
            ->values();

        return [
            'products' => $products->map(function (PriceProduct $product) {
                $target = $product->target_price !== null ? (float) $product->target_price : null;
                $stats = $this->buildStats($product->priceRecords);
                $stats['goal_status'] = $stats['last'] !== null
                    ? GoalStatus::fromPrice($target, $stats['last'])?->value
                    : null;

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'target_price' => $target,
                    'stats' => $stats,
                    'series' => $product->priceRecords->map(fn (PriceRecord $record) => [
                        'date' => $record->recorded_at->toDateString(),
                        'price' => (float) $record->price,
                    ])->values()->all(),
                ];
            })->all(),
        ];
    }

    public function getPatrimony(User $user): array
    {
        $purchases = PricePurchase::forUser($user->id)
            ->with(['product:id,name', 'sale'])
            ->orderByDesc('purchased_at')
            ->orderByDesc('created_at')
            ->get();

        $owned = $purchases->filter(fn (PricePurchase $purchase) => $purchase->sale === null);

        $invested = (float) $purchases->sum(fn (PricePurchase $p) => (float) $p->price_paid);
        $recovered = (float) $purchases->sum(fn (PricePurchase $p) => $p->sale !== null ? (float) $p->sale->sale_price : 0.0);
        $currentWorth = (float) $owned->sum(fn (PricePurchase $p) => $p->current_value !== null ? (float) $p->current_value : (float) $p->price_paid);
        $depreciation = (float) $owned
            ->filter(fn (PricePurchase $p) => $p->current_value !== null)
            ->sum(fn (PricePurchase $p) => max(0, (float) $p->price_paid - (float) $p->current_value));

        $byYear = $purchases
            ->groupBy(fn (PricePurchase $purchase) => $purchase->purchased_at->year)
            ->map(fn (Collection $group, int $year) => [
                'year' => $year,
                'invested' => round((float) $group->sum(fn (PricePurchase $p) => (float) $p->price_paid), 2),
                'items' => $group->map(fn (PricePurchase $purchase) => [
                    'purchase_id' => $purchase->id,
                    'product_id' => $purchase->product_id,
                    'product_name' => $purchase->product?->name,
                    'price_paid' => (float) $purchase->price_paid,
                    'purchased_at' => $purchase->purchased_at->toDateString(),
                    'warranty_months' => $purchase->warranty_months,
                    'current_value' => $purchase->current_value !== null ? (float) $purchase->current_value : null,
                    'is_sold' => $purchase->sale !== null,
                    'sale_price' => $purchase->sale !== null ? (float) $purchase->sale->sale_price : null,
                    'sold_at' => $purchase->sale?->sold_at?->toDateString(),
                ])->values()->all(),
            ])
            ->sortKeysDesc()
            ->values()
            ->all();

        return [
            'totals' => [
                'invested' => round($invested, 2),
                'recovered' => round($recovered, 2),
                'current_worth' => round($currentWorth, 2),
                'depreciation' => round($depreciation, 2),
                'net_cost' => round($invested - $recovered, 2),
            ],
            'by_year' => $byYear,
        ];
    }

    private function trackedProductSummary(PriceProduct $product): array
    {
        $target = $product->target_price !== null ? (float) $product->target_price : null;
        $launch = $product->launch_price !== null ? (float) $product->launch_price : null;
        $last = $product->latestPriceRecord !== null ? (float) $product->latestPriceRecord->price : null;
        $min = $product->price_records_min_price !== null ? (float) $product->price_records_min_price : null;

        return [
            'id' => $product->id,
            'name' => $product->name,
            'target_price' => $target,
            'launch_price' => $launch,
            'last_price' => $last,
            'last_recorded_at' => $product->latestPriceRecord?->recorded_at?->toDateString(),
            'min_price' => $min,
            'avg_price' => $product->price_records_avg_price !== null ? round((float) $product->price_records_avg_price, 2) : null,
            'max_price' => $product->price_records_max_price !== null ? (float) $product->price_records_max_price : null,
            'records_count' => (int) $product->price_records_count,
            'goal_status' => $last !== null ? GoalStatus::fromPrice($target, $last)?->value : null,
            'savings_vs_launch' => ($launch !== null && $min !== null) ? round($launch - $min, 2) : null,
        ];
    }

    /**
     * Build min/avg/max/last/records_count stats from records sorted by recorded_at asc.
     */
    private function buildStats(Collection|SupportCollection $records): array
    {
        $prices = $records->map(fn ($record) => (float) $record->price);

        return [
            'min' => $records->isEmpty() ? null : $prices->min(),
            'avg' => $records->isEmpty() ? null : round((float) $prices->avg(), 2),
            'max' => $records->isEmpty() ? null : $prices->max(),
            'last' => $records->isEmpty() ? null : (float) $records->last()->price,
            'records_count' => $records->count(),
        ];
    }
}
