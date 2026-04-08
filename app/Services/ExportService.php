<?php

namespace App\Services;

use App\Models\Country;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Supplier;
use Illuminate\Support\Facades\Http;
use Barryvdh\DomPDF\Facade\Pdf;

class ExportService
{
    /**
     * Export all country prices to PDF.
     */
    public function exportPricesToPdf($countryIds)
    {
        $countriesQuery = Country::query()
            ->orderBy('name');

        if ($countryIds) {
            $countriesQuery->whereIn('id', $countryIds);
        }

        $countries = $countriesQuery->get();
        $exportData = [];

        foreach ($countries as $country) {
            $products = Product::query()
                ->where('country_id', $country->id)
                ->addSelect([
                    'latest_price' => ProductPrice::select('price')
                        ->whereColumn('product_id', 'products.id')
                        ->orderBy('date', 'desc')
                        ->orderBy('price', 'asc')
                        ->limit(1),
                    'previous_price' => ProductPrice::select('price')
                        ->whereColumn('product_id', 'products.id')
                        ->where('date', '<', function ($q) {
                            $q->select('date')->from('product_prices')
                                ->whereColumn('product_id', 'products.id')
                                ->orderBy('date', 'desc')
                                ->limit(1);
                        })
                        ->orderBy('date', 'desc')
                        ->orderBy('price', 'asc')
                        ->limit(1),
                    'latest_supplier' => Supplier::select('name')
                        ->join('product_prices', 'product_prices.supplier_id', '=', 'suppliers.id')
                        ->whereColumn('product_prices.product_id', 'products.id')
                        ->orderBy('product_prices.date', 'desc')
                        ->orderBy('product_prices.price', 'asc')
                        ->limit(1),
                ])
                ->orderBy('name')
                ->get()
                ->map(function ($product) {
                    $latest = $product->latest_price ? (float) $product->latest_price : null;
                    $previous = $product->previous_price ? (float) $product->previous_price : $latest;

                    $variation = ($latest && $previous && $previous > 0)
                        ? (($latest - $previous) / $previous) * 100
                        : 0.0;

                    return (object) [
                        'name' => $product->name,
                        'latestPrice' => $latest,
                        'previousPrice' => $previous,
                        'variation' => round($variation, 2),
                        'status' => $variation > 0 ? 'up' : ($variation < 0 ? 'down' : (!$product->previous_price && $product->latest_price ? 'new' : 'none')),
                        'supplier' => $product->latest_supplier ?? 'N/A',
                    ];
                });

            $exportData[] = (object) [
                'country' => $country,
                'flag' => $this->resolveCountryFlag($country->name),
                'products' => $products,
            ];
        }

        return Pdf::loadView('exports.price-table', [
            'exportData' => $exportData,
            'date' => now()->format('d/m/Y H:i'),
        ])->setPaper('a4', 'portrait');
    }

    /**
     * Resolve flag image for a country name.
     */
    private function resolveCountryFlag($countryName)
    {
        static $flagCache = [];

        if (isset($flagCache[$countryName])) {
            return $flagCache[$countryName];
        }

        try {
            $name = strtolower($countryName);
            $url = "https://flagcdn.com/w80/" . $this->getCountryCode($name) . ".png";
            $imageData = @file_get_contents($url);

            if ($imageData) {
                $base64 = base64_encode($imageData);
                $flagCache[$countryName] = 'data:image/png;base64,' . $base64;
                return $flagCache[$countryName];
            }
        } catch (\Exception $e) {
            // Log or fallback
        }

        return null;
    }

    /**
     * Helper to map names to flagcdn codes.
     */
    private function getCountryCode($name)
    {
        $map = [
            'brasil' => 'br',
            'china' => 'cn',
            'índia' => 'in',
            'canadá' => 'ca',
            'egito' => 'eg',
            'argentina' => 'ar',
            'peru' => 'pe',
            'vietnã' => 'vn',
            'indonésia' => 'id',
            'méxico' => 'mx',
            'turquia' => 'tr',
            'paquistão' => 'pk',
            'guatemala' => 'gt',
            'bulgária' => 'bg',
            'chile' => 'cl',
            'malaysia' => 'my',
            'sri-lanka' => 'lk',
        ];

        foreach ($map as $key => $code) {
            if (str_contains($name, $key)) return $code;
        }

        return 'us';
    }
}
