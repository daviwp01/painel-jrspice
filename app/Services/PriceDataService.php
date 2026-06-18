<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class PriceDataService
{
    /**
     * Get products for the price table with pre-calculated trends and variations via SQL.
     */
    public function getPriceTableProducts($countryId, $supplierId, Carbon $rangeStart, Carbon $rangeEnd, ?string $sortField, string $sortDirection)
    {
        $query = Product::query()
            ->select('products.*')
            ->where('country_id', $countryId);

        // Descobre qual foi a última data que teve importação até o período selecionado
        $latestImportDate = ProductPrice::whereHas('product', function ($q) use ($countryId) {
            $q->where('country_id', $countryId);
        })
        ->where('date', '<=', $rangeEnd->toDateString())
        ->when($supplierId, fn($q) => $q->where('supplier_id', $supplierId))
        ->max('date');

        if ($latestImportDate) {
            // Se achou uma data, pega a semana dessa data para exibir 100% apenas o que é dessa semana
            $actualRangeStart = Carbon::parse($latestImportDate)->startOfWeek(Carbon::MONDAY);
            $actualRangeEnd = Carbon::parse($latestImportDate)->endOfWeek(Carbon::SUNDAY);

            $query->whereHas('prices', function ($q) use ($supplierId, $actualRangeStart, $actualRangeEnd) {
                $q->whereBetween('date', [$actualRangeStart->toDateString(), $actualRangeEnd->toDateString()])
                    ->when($supplierId, fn($sq) => $sq->where('supplier_id', $supplierId));
            });
            $referenceEnd = $actualRangeEnd;
        } else {
            // Fallback caso não tenha nada
            $query->whereHas('prices', function ($q) use ($supplierId, $rangeEnd) {
                $q->where('date', '<=', $rangeEnd->toDateString())
                    ->when($supplierId, fn($sq) => $sq->where('supplier_id', $supplierId));
            });
            $referenceEnd = $rangeEnd;
        }

        // Subqueries (Lógica d0117d79 - Estabilização)
        $query->addSelect([
            'latest_price' => ProductPrice::select('price')
                ->whereColumn('product_id', 'products.id')
                ->where('date', '<=', $referenceEnd->toDateString())
                ->when($supplierId, fn($q) => $q->where('supplier_id', $supplierId))
                ->orderBy('date', 'desc')
                ->orderBy('price', 'asc')
                ->limit(1),

            'previous_price' => ProductPrice::select('price')
                ->whereColumn('product_id', 'products.id')
                ->where('date', '<', function ($q) use ($supplierId, $referenceEnd) {
                    $q->select('date')
                        ->from('product_prices')
                        ->whereColumn('product_id', 'products.id')
                        ->where('date', '<=', $referenceEnd->toDateString())
                        ->when($supplierId, fn($sq) => $sq->where('supplier_id', $supplierId))
                        ->orderBy('date', 'desc')
                        ->limit(1);
                })
                ->when($supplierId, fn($q) => $q->where('supplier_id', $supplierId))
                ->orderBy('date', 'desc')
                ->orderBy('price', 'asc')
                ->limit(1),

            'latest_supplier' => Supplier::select('name')
                ->join('product_prices', 'product_prices.supplier_id', '=', 'suppliers.id')
                ->whereColumn('product_prices.product_id', 'products.id')
                ->where('product_prices.date', '<=', $referenceEnd->toDateString())
                ->when($supplierId, fn($q) => $q->where('supplier_id', $supplierId))
                ->orderBy('product_prices.date', 'desc')
                ->orderBy('product_prices.price', 'asc')
                ->limit(1),
        ]);

        // Ordenação Global no Banco de Dados
        $direction = strtolower($sortDirection) === 'desc' ? 'desc' : 'asc';

        if ($sortField === 'latest_price') {
            $query->orderBy('latest_price', $direction);
        } elseif ($sortField === 'variation') {
            $query->orderByRaw("
                CASE 
                    WHEN latest_price IS NOT NULL AND previous_price IS NOT NULL AND previous_price > 0
                    THEN (latest_price - previous_price) / previous_price 
                    ELSE 0 
                END $direction
            ");
        } elseif ($sortField === 'name') {
            $query->orderBy('name', $direction);
        } else {
            $query->orderBy('name', 'asc');
        }

        $paginated = $query->paginate(50)->withQueryString();

        // Trasformação final para o frontend
        $paginated->getCollection()->transform(function ($product) {
            $latest = $product->latest_price ? (float) $product->latest_price : null;
            $previous = $product->previous_price ? (float) $product->previous_price : $latest;

            $variation = ($latest && $previous && $previous > 0)
                ? (($latest - $previous) / $previous) * 100
                : 0.0;

            $product->latest_price = $latest;
            $product->previous_price = $previous;
            $product->variation = round($variation, 2);
            $product->status = $variation > 0 ? 'up' : ($variation < 0 ? 'down' : (!$product->previous_price && $product->latest_price ? 'new' : 'none'));
            
            return $product;
        });

        return $paginated;
    }

    /**
     * Get historical price data for a product/supplier list.
     */
    public function getHistoricalData($countryId, $productId, $supplierId, ?string $dateRange, ?string $sortField, string $sortDirection, ?array $range = null)
    {
        $query = ProductPrice::query()
            ->join('products', 'products.id', '=', 'product_prices.product_id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'product_prices.supplier_id')
            ->leftJoin('countries', 'countries.id', '=', 'products.country_id')
            ->select([
                'product_prices.*',
                'products.name as product_name',
                'suppliers.name as supplier_name',
                'countries.name as country_name',
            ])
            ->with([
                'product:id,name,country_id',
                'product.country:id,name',
                'supplier:id,name'
            ]);

        if ($countryId) {
            $query->where('products.country_id', $countryId);
        }

        if ($productId) {
            $query->where('product_prices.product_id', $productId);
        }

        if ($supplierId) {
            $query->where('product_prices.supplier_id', $supplierId);
        }

        if ($range) {
            $query->whereBetween('product_prices.date', [$range['start'], $range['end']]);
        }

        $sortableFields = [
            'name' => 'products.name',
            'country' => 'countries.name',
            'supplier' => 'suppliers.name',
            'date' => 'product_prices.date',
            'price' => 'product_prices.price',
        ];

        if ($sortField && isset($sortableFields[$sortField])) {
            $query->orderBy($sortableFields[$sortField], $sortDirection)
                ->orderBy('product_prices.id', 'desc');
        } else {
            $query->orderBy('product_prices.date', 'desc')
                ->orderBy('suppliers.name', 'asc');
        }

        return $query->paginate(50)->withQueryString();
    }

    /**
     * Calculate historical weekly chart points (minimums).
     */
    /**
     * Calculate historical weekly chart points (minimums).
     * Synchronized with frontend week calculation for perfect alignment.
     */
    public function calculateChartWeekly(int $productId, ?array $range = null): array
    {
        $prices = ProductPrice::where('product_id', $productId)
            ->when($range, fn($q) => $q->whereBetween('date', [$range['start'], $range['end']]))
            ->orderBy('date', 'asc')
            ->get();

        if ($prices->isEmpty()) {
            return [];
        }

        $chartData = [];
        foreach ($prices as $price) {
            $date = Carbon::parse($price->date);
            $year = $date->year;
            $startOfYear = $date->copy()->startOfYear();
            
            // Match JS logic: Math.floor((dateVal - start) / (7 * 24 * 60 * 60 * 1000))
            // using dayOfYear - 1 is exactly the number of full days since Jan 1st 00:00:00
            $week = (int) floor(($date->dayOfYear - 1) / 7);
            
            if ($week < 0) $week = 0;
            if ($week > 51) $week = 51;

            if (!isset($chartData[$year])) {
                $chartData[$year] = array_fill(0, 52, null);
            }
            
            if ($chartData[$year][$week] === null || $price->price < $chartData[$year][$week]) {
                $chartData[$year][$week] = (float)round($price->price, 2);
            }
        }

        return $chartData;
    }

    /**
     * Get the 3 best (lowest) prices for the most recent 3 distinct weeks.
     */
    public function getRecentBestPrices(int $productId, ?array $range = null): array
    {
        return Cache::remember("product_best_prices_{$productId}_" . ($range['start'] ?? 'all'), 300, function () use ($productId, $range) {
            $prices = ProductPrice::with('supplier')
                ->where('product_id', $productId)
                ->when($range, fn($q) => $q->whereBetween('date', [$range['start'], $range['end']]))
                ->orderBy('date', 'desc')
                ->get();

            $weeksMap = [];
            foreach ($prices as $price) {
                $date = Carbon::parse($price->date);
                $year = $date->isoWeekYear(); // Use isoWeekYear to match isoWeek correctly
                $week = $date->isoWeek();
                $yw = sprintf("%04d-%02d", $year, $week);

                if (!isset($weeksMap[$yw]) || $price->price < $weeksMap[$yw]['price']) {
                    $weeksMap[$yw] = [
                        'supplier' => $price->supplier->name ?? 'N/A',
                        'date' => $price->date,
                        'price' => (float)$price->price,
                        'week_label' => "{$year}/{$week}",
                    ];
                }
            }

            krsort($weeksMap);
            return array_slice(array_values($weeksMap), 0, 3);
        });
    }

    /**
     * Calculate metrics (Latest, Year, All-time) via SQL aggregations.
     */
    public function calculateMetrics(int $productId, ?array $range): array
    {
        return Cache::remember("product_metrics_{$productId}_" . ($range['start'] ?? 'all'), 300, function () use ($productId, $range) {
            $latestDate = ProductPrice::where('product_id', $productId)
                ->when($range, fn($q) => $q->whereBetween('date', [$range['start'], $range['end']]))
                ->max('date');

            if (!$latestDate) {
                return [
                    'latest' => ['label' => 'SEM DADOS', 'min' => 0, 'max' => 0, 'spread' => 0],
                    'year' => ['label' => 'ANO', 'min' => 0, 'max' => 0, 'spread' => 0],
                    'all' => ['label' => 'GERAL', 'min' => 0, 'max' => 0, 'spread' => 0],
                ];
            }

            $currentDate = Carbon::parse($latestDate);
            $startOfYear = $currentDate->copy()->startOfYear()->toDateString();

            $latestStats = ProductPrice::where('product_id', $productId)
                ->where('date', $latestDate)
                ->selectRaw('MIN(price) as min_p, MAX(price) as max_p')
                ->first();

            $yearStats = DB::table('product_prices')
                ->selectRaw('MIN(min_weekly) as min_p, MAX(min_weekly) as max_p')
                ->fromSub(function ($query) use ($productId, $startOfYear) {
                    $query->from('product_prices')
                        ->where('product_id', $productId)
                        ->where('date', '>=', $startOfYear)
                        ->groupByRaw('YEARWEEK(date, 1)')
                        ->selectRaw('MIN(price) as min_weekly');
                }, 'weekly_sub')
                ->first();

            $allTimeStats = DB::table('product_prices')
                ->selectRaw('MIN(min_weekly) as min_p, MAX(min_weekly) as max_p, MIN(date_min) as first_date')
                ->fromSub(function ($query) use ($productId) {
                    $query->from('product_prices')
                        ->where('product_id', $productId)
                        ->groupByRaw('YEARWEEK(date, 1)')
                        ->selectRaw('MIN(price) as min_weekly, MIN(date) as date_min');
                }, 'weekly_sub')
                ->first();

            $productInfo = Product::select('id', 'name', 'harvest_month')
                ->where('id', $productId)
                ->first();

            $metrics = [
                'latest' => [
                    'label' => 'ÚLTIMA DATA: ' . $currentDate->format('d/m/Y'),
                    'min' => (float) ($latestStats->min_p ?? 0),
                    'max' => (float) ($latestStats->max_p ?? 0),
                ],
                'year' => [
                    'label' => 'ANO: ' . $currentDate->year,
                    'min' => (float) ($yearStats->min_p ?? 0),
                    'max' => (float) ($yearStats->max_p ?? 0),
                ],
                'all' => [
                    'label' => 'DESDE: ' . ($allTimeStats->first_date ? Carbon::parse($allTimeStats->first_date)->year : $currentDate->year),
                    'min' => (float) ($allTimeStats->min_p ?? 0),
                    'max' => (float) ($allTimeStats->max_p ?? 0),
                ],
                'product_info' => $productInfo,
            ];

            foreach ($metrics as $key => $value) {
                if ($key === 'product_info') continue;
                $metrics[$key]['spread'] = ($value['min'] > 0)
                    ? (($value['max'] - $value['min']) / $value['min']) * 100
                    : 0;
            }

            return $metrics;
        });
    }

    /**
     * Calculate historical chart points (monthly minimums).
     */
    public function calculateChartHistorical(int $productId, ?array $range = null): array
    {
        $pricesRow = ProductPrice::query()
            ->where('product_id', $productId)
            ->when($range, fn($q) => $q->whereBetween('date', [$range['start'], $range['end']]))
            ->selectRaw('YEAR(date) as yr, MONTH(date) as mt, MIN(price) as min_p')
            ->groupBy('yr', 'mt')
            ->orderBy('yr', 'asc')
            ->orderBy('mt', 'asc')
            ->get();

            if ($pricesRow->isEmpty()) {
                return [];
            }

            $chartData = [];
            $firstYear = $pricesRow->first()->yr;
            $currentYear = now()->year;

            $grouped = $pricesRow->groupBy(function($p) {
                return (int)$p->yr . '-' . (int)$p->mt;
            });

            foreach (range((int)$firstYear, (int)$currentYear) as $year) {
                if ($range && $year < Carbon::parse($range['start'])->year) continue;
                if ($range && $year > Carbon::parse($range['end'])->year) continue;

                $monthlyData = [];
                for ($month = 1; $month <= 12; $month++) {
                    $key = $year . '-' . $month;
                    $item = $grouped->get($key);
                    $monthlyData[] = $item ? (float) round($item->first()->min_p, 2) : null;
                }
                $chartData[$year] = $monthlyData;
            }

        return $chartData;
    }

    /**
     * Get suppliers list for a country.
     */
    public function getSuppliers($countryId)
    {
        return Cache::remember("dashboard_suppliers_country_{$countryId}", 300, function () use ($countryId) {
            $query = Supplier::query()
                ->select('suppliers.id', 'suppliers.name')
                ->join('product_prices', 'product_prices.supplier_id', '=', 'suppliers.id')
                ->join('products', 'products.id', '=', 'product_prices.product_id');

            if ($countryId) {
                $query->where('products.country_id', $countryId);
            }

            return $query
                ->distinct()
                ->orderBy('suppliers.name')
                ->get();
        });
    }
    /**
     * Get all raw prices for continuous chart mode.
     */
    public function getContinuousData($countryId, $productId, $supplierId, ?array $range = null)
    {
        $query = ProductPrice::query()
            ->join('products', 'products.id', '=', 'product_prices.product_id')
            ->where('product_prices.product_id', $productId);

        if ($countryId) {
            $query->where('products.country_id', $countryId);
        }

        if ($supplierId) {
            $query->where('product_prices.supplier_id', $supplierId);
        }

        if ($range) {
            $query->whereBetween('product_prices.date', [$range['start'], $range['end']]);
        }

        return $query->select('product_prices.date', 'product_prices.price', 'products.harvest_month')
            ->orderBy('product_prices.date', 'asc')
            ->get();
    }
}
