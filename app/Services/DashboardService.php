<?php

namespace App\Services;

use App\Models\Country;
use App\Models\DashboardPage;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class DashboardService
{
    /**
     * Get all active dashboard settings.
     */
    public function getSettings(): array
    {
        $settings = Cache::remember('dashboard_settings_all', 300, function () {
            return Setting::all()->pluck('value', 'key')->toArray();
        });

        return is_array($settings) ? $settings : $settings->toArray();
    }

    /**
     * Resolve date range string into Carbon start/end dates.
     */
    public function resolveDateRange(?string $dateRange, Carbon $defaultStart, Carbon $defaultEnd): array
    {
        if (!$dateRange || $dateRange === 'Todos') {
            return [$defaultStart, $defaultEnd];
        }

        $start = $defaultStart->copy();
        $end = $defaultEnd->copy();

        $parts = explode('-', $dateRange);

        if (count($parts) === 2) {
            $year = (int) $parts[0];
            $week = (int) $parts[1];

            $start = Carbon::now()->setISODate($year, $week)->startOfWeek(Carbon::MONDAY);
            $end = $start->copy()->endOfWeek(Carbon::SUNDAY);
        } elseif (count($parts) === 1) {
            $year = (int) $parts[0];

            $start = Carbon::create($year, 1, 1)->startOfYear();
            $end = $start->copy()->endOfYear();
        }

        return [$start, $end];
    }

    /**
     * Get countries that had updates in the specified week.
     */
    public function getCountriesWithLatestUpdate(Carbon $startOfWeek, Carbon $endOfWeek)
    {
        return Cache::remember(
            'dashboard_countries_latest_' . $startOfWeek->format('Ymd'),
            300,
            function () use ($startOfWeek, $endOfWeek) {
                $latestSub = ProductPrice::query()
                    ->join('products', 'product_prices.product_id', '=', 'products.id')
                    ->whereBetween('product_prices.date', [
                        $startOfWeek->toDateString(),
                        $endOfWeek->toDateString()
                    ])
                    ->groupBy('products.country_id')
                    ->selectRaw('products.country_id, MAX(product_prices.date) as latest_weekly_update');

                return Country::query()
                    ->leftJoinSub($latestSub, 'latest_updates', function ($join) {
                        $join->on('countries.id', '=', 'latest_updates.country_id');
                    })
                    ->select('countries.id', 'countries.name', 'latest_updates.latest_weekly_update')
                    ->orderByRaw('latest_updates.latest_weekly_update IS NULL, latest_updates.latest_weekly_update DESC')
                    ->orderBy('countries.name')
                    ->get();
            }
        );
    }

    /**
     * Get products for the sidebar filtered by country.
     */
    public function getProductsSidebar($countryId)
    {
        if (!$countryId) {
            return collect();
        }

        return Cache::remember("dashboard_products_sidebar_{$countryId}", 300, function () use ($countryId) {
            return Product::query()
                ->where('country_id', $countryId)
                ->select('id', 'name', 'country_id', 'harvest_month')
                ->orderBy('name')
                ->get();
        });
    }

    /**
     * Get available dates (years and weeks) for a country.
     */
    public function getAvailableDates($countryId): array
    {
        $dates = Cache::remember("dashboard_available_dates_{$countryId}", 600, function () use ($countryId) {
            $query = ProductPrice::query()
                ->join('products', 'products.id', '=', 'product_prices.product_id');

            if ($countryId) {
                $query->where('products.country_id', $countryId);
            }

            return $query
                ->selectRaw('YEAR(product_prices.date) as year, WEEK(product_prices.date, 1) as week')
                ->distinct()
                ->orderBy('year', 'desc')
                ->orderBy('week', 'desc')
                ->get()
                ->groupBy('year')
                ->map(fn($weeks, $year) => [
                    'year' => $year,
                    'weeks' => $weeks->values(),
                ])
                ->values()
                ->toArray();
        });

        return is_array($dates) ? $dates : $dates->toArray();
    }
}
