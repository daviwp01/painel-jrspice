<?php

namespace App\Services;

use App\Models\Country;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Supplier;
use Illuminate\Support\Facades\Http;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;


class ExportService
{
    /**
     * Export all country prices to PDF.
     */
    public function exportPricesToPdf($countryIds)
    {
        $now = now();
        $startOfWeek = $now->copy()->startOfWeek(Carbon::MONDAY)->toDateString();
        $endOfWeek = $now->copy()->endOfWeek(Carbon::SUNDAY)->toDateString();

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
                ->whereHas('prices', function ($q) use ($startOfWeek, $endOfWeek) {
                    $q->whereBetween('date', [$startOfWeek, $endOfWeek]);
                })
                ->addSelect([
                    'latest_price' => ProductPrice::select('price')
                        ->whereColumn('product_id', 'products.id')
                        ->where('date', '<=', $endOfWeek)
                        ->orderBy('date', 'desc')
                        ->orderBy('price', 'asc')
                        ->limit(1),
                    'previous_price' => ProductPrice::select('price')
                        ->whereColumn('product_id', 'products.id')
                        ->where('date', '<', function ($q) use ($endOfWeek) {
                            $q->select('date')->from('product_prices')
                                ->whereColumn('product_id', 'products.id')
                                ->where('date', '<=', $endOfWeek)
                                ->orderBy('date', 'desc')
                                ->limit(1);
                        })
                        ->orderBy('date', 'desc')
                        ->orderBy('price', 'asc')
                        ->limit(1),
                    'latest_supplier' => Supplier::select('name')
                        ->join('product_prices', 'product_prices.supplier_id', '=', 'suppliers.id')
                        ->whereColumn('product_prices.product_id', 'products.id')
                        ->where('product_prices.date', '<=', $endOfWeek)
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

            if ($products->isNotEmpty()) {
                $exportData[] = (object) [
                    'name' => $country->name,
                    'flag' => $this->resolveCountryFlag($country->name),
                    'products' => $products,
                ];
            }
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
            $name = mb_strtolower($countryName, 'UTF-8');
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
            'brasil' => 'br', 'brazil' => 'br',
            'china' => 'cn',
            'índia' => 'in', 'india' => 'in',
            'canadá' => 'ca', 'canada' => 'ca',
            'egito' => 'eg', 'egypt' => 'eg',
            'argentina' => 'ar',
            'peru' => 'pe',
            'vietnã' => 'vn', 'vietna' => 'vn', 'vietnam' => 'vn',
            'indonésia' => 'id', 'indonesia' => 'id',
            'méxico' => 'mx', 'mexico' => 'mx',
            'turquia' => 'tr', 'turkey' => 'tr',
            'paquistão' => 'pk', 'pakistan' => 'pk',
            'guatemala' => 'gt',
            'bulgária' => 'bg', 'bulgaria' => 'bg',
            'chile' => 'cl',
            'malaysia' => 'my', 'malásia' => 'my',
            'sri-lanka' => 'lk', 'sri lanka' => 'lk',
            'estados unidos' => 'us', 'usa' => 'us', 'united states' => 'us',
            'espanha' => 'es', 'spain' => 'es',
            'frança' => 'fr', 'france' => 'fr',
            'itália' => 'it', 'italy' => 'it',
            'alemanha' => 'de', 'germany' => 'de',
            'reino unido' => 'gb', 'united kingdom' => 'gb', 'uk' => 'gb',
            'portugal' => 'pt',
            'colômbia' => 'co', 'colombia' => 'co',
            'uruguai' => 'uy', 'uruguay' => 'uy',
            'paraguai' => 'py', 'paraguay' => 'py',
            'bolívia' => 'bo', 'bolivia' => 'bo',
            'equador' => 'ec', 'ecuador' => 'ec',
            'venezuela' => 've',
            'tailândia' => 'th', 'thailand' => 'th',
            'japão' => 'jp', 'japan' => 'jp',
            'coréia' => 'kr', 'korea' => 'kr',
            'áfrica do sul' => 'za', 'south africa' => 'za',
            'nigéria' => 'ng', 'nigeria' => 'ng',
            'marrocos' => 'ma', 'morocco' => 'ma',
            'emirados árabes' => 'ae', 'uae' => 'ae',
            'arábia saudita' => 'sa', 'saudi arabia' => 'sa',
            'irã' => 'ir', 'iran' => 'ir',
            'rússia' => 'ru', 'russia' => 'ru',
            'ucrânia' => 'ua', 'ukraine' => 'ua',
            'holanda' => 'nl', 'netherlands' => 'nl',
            'bélgica' => 'be', 'belgium' => 'be',
            'suíça' => 'ch', 'switzerland' => 'ch',
            'suécia' => 'se', 'sweden' => 'se',
            'noruega' => 'no', 'norway' => 'no',
            'dinamarca' => 'dk', 'denmark' => 'dk',
            'polônia' => 'pl', 'poland' => 'pl',
            'austrália' => 'au', 'australia' => 'au',
            'nova zelândia' => 'nz', 'new zealand' => 'nz',
        ];

        foreach ($map as $key => $code) {
            if (str_contains($name, $key)) return $code;
        }

        return 'un'; // Unknown flag fallback
    }
}
