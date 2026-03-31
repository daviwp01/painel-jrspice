<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request as HttpRequest;
use Inertia\Inertia;
use App\Models\DashboardPage;
use App\Models\Country;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Supplier;

class InternalDashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $pages = DashboardPage::where('is_active', true)->orderBy('order')->get();
        
        $allowedPages = $pages->filter(function ($page) use ($user) {
            return $user->canAccessPage($page->slug);
        });

        if ($allowedPages->isEmpty()) {
            return Inertia::render('Dashboard/Empty');
        }

        // Redirect to the first available authorized dashboard page
        return redirect()->route('dashboard.page', ['slug' => $allowedPages->first()->slug]);
    }

    public function show(HttpRequest $request, $slug)
    {
        $user = auth()->user();
        $currentPage = DashboardPage::where('slug', $slug)->firstOrFail();

        // Check permission
        if (!$user->canAccessPage($currentPage->slug)) {
            abort(403);
        }

        $pages = DashboardPage::where('is_active', true)
            ->orderBy('order')
            ->get()
            ->filter(fn($p) => $user->canAccessPage($p->slug))
            ->values();

        $countries = Country::orderBy('name')->get();
        $countryId = $request->query('country_id');
        $supplierId = $request->query('supplier_id');
        $productId = $request->query('product_id');
        $dateRange = $request->query('date_range');

        if (!$countryId && $countries->isNotEmpty()) {
            $countryId = $countries->first()->id;
        }

        // --- Smart Prop Filtering for Selectors ---
        // Suppliers that have prices for products in the selected country
        $suppliers = Supplier::whereHas('productPrices.product', function($q) use ($countryId) {
                if ($countryId) $q->where('country_id', $countryId);
            })
            ->orderBy('name')
            ->get();
        
        // Products in the selected country
        $productsForSidebarQuery = Product::query();
        if ($countryId) {
            $productsForSidebarQuery->where('country_id', $countryId);
        }
        $productsSidebar = $productsForSidebarQuery->orderBy('name')->get();

        // --- Logic for PriceTable / Main Content ---
        $productsQuery = Product::with(['prices' => function($query) use ($supplierId) {
            $query->with('supplier')->orderBy('date', 'desc');
            if ($supplierId) {
                $query->where('supplier_id', $supplierId);
            }
        }]);
        
        if ($countryId) {
            $productsQuery->where('country_id', $countryId);
        }
        if ($supplierId) {
            $productsQuery->whereHas('prices', function($q) use ($supplierId) {
                $q->where('supplier_id', $supplierId);
            });
        }
        
        // Final Products list to be passed (paginated for PriceTable, full for others)
        if ($currentPage->component === 'Dashboard/PriceTable') {
            $products = $productsQuery->orderBy('name')->paginate(20)->withQueryString();
        } else {
            $products = $productsQuery->orderBy('name')->get();
        }

        // Validate productId
        if ($productId) {
            $productExists = Product::where('id', $productId)->exists();
            if (!$productExists) {
                $productId = null;
            }
        }

        if (!$productId && $productsSidebar->isNotEmpty()) {
            $productId = $productsSidebar->first()->id;
        }
        
        // For PriceTable we need the paginated list, but for Sidebar filters we need the full list from that country
        $productsProp = ($currentPage->component === 'Dashboard/PriceTable') ? $products : $productsSidebar;

        // --- Logic for HistoricalData ---
        $historicalData = null;
        if ($currentPage->component === 'Dashboard/HistoricalData') {
            $hDataQuery = ProductPrice::with(['product.country', 'supplier'])
                ->join('products', 'products.id', '=', 'product_prices.product_id')
                ->select('product_prices.*'); // Avoid column collision

            if ($countryId) {
                $hDataQuery->where('products.country_id', $countryId);
            }
            if ($productId) {
                $hDataQuery->where('product_id', $productId);
            }
            if ($supplierId) {
                $hDataQuery->where('supplier_id', $supplierId);
            }
            if ($dateRange && $dateRange !== 'Todos') {
                $parts = explode('-', $dateRange);
                if (count($parts) === 2) {
                    $hDataQuery->whereRaw('YEAR(date) = ? AND WEEK(date, 1) = ?', [$parts[0], $parts[1]]);
                }
            }
            $historicalData = $hDataQuery->orderBy('date', 'desc')->paginate(20)->withQueryString();
        }

        // Metrics (Only for Dashboard/Show or specific needs)
        $pricesData = [];
        $metrics = [];
        if ($productId) {
            // Chart data: now ignoring supplier_id filter as requested
            $pricesQuery = ProductPrice::with('supplier')->where('product_id', $productId);
            // Always respect country_id even in charts
            if ($countryId) {
                $pricesQuery->whereHas('product', fn($q) => $q->where('country_id', $countryId));
            }
            if ($dateRange && $dateRange !== 'Todos') {
                $parts = explode('-', $dateRange);
                if (count($parts) === 2) {
                    $pricesQuery->whereRaw('YEAR(date) = ? AND WEEK(date, 1) = ?', [$parts[0], $parts[1]]);
                }
            }
            $pricesData = $pricesQuery->orderBy('date')->get();

            // Metrics: also ignoring supplier_id filter
            $metricsQuery = ProductPrice::where('product_id', $productId);
            if ($countryId) {
                $metricsQuery->whereHas('product', fn($q) => $q->where('country_id', $countryId));
            }
            $allPricesForMetrics = $metricsQuery->get();

            $now = \Carbon\Carbon::now();
            $globalFirstYear = \App\Models\ProductPrice::min('date')?->year ?? 2023;
            $firstYear = $globalFirstYear;
            
            // Metrics: calculating the real absolute benchmarks for the product and country
            $metrics = [
                'latest' => [
                    'label' => 'ÚLTIMA SEMANA',
                    'sub_label' => 'RANGE DAS OFERTAS RECEBIDAS',
                    'min' => $allPricesForMetrics->where('date', '>=', $now->copy()->subWeek())->where('date', '<=', $now->copy()->endOfDay())->min('min_price'),
                    'max' => $allPricesForMetrics->where('date', '>=', $now->copy()->subWeek())->where('date', '<=', $now->copy()->endOfDay())->max('max_price')
                ],
                'year' => [
                    'label' => 'ANO: ' . $now->year,
                    'sub_label' => 'MENORES E MAIORES PREÇOS',
                    'min' => $allPricesForMetrics->where('date', '>=', $now->copy()->startOfYear())->where('date', '<=', $now->copy()->endOfDay())->min('min_price'),
                    'max' => $allPricesForMetrics->where('date', '>=', $now->copy()->startOfYear())->where('date', '<=', $now->copy()->endOfDay())->max('max_price')
                ],
                'all' => [
                    'label' => 'DESDE: ' . $firstYear,
                    'sub_label' => 'MENORES E MAIORES PREÇOS',
                    'min' => $allPricesForMetrics->where('date', '<=', $now->copy()->endOfDay())->min('min_price'),
                    'max' => $allPricesForMetrics->where('date', '<=', $now->copy()->endOfDay())->max('max_price')
                ]
            ];

            // Calculate spread % for each metric card
            foreach ($metrics as $key => $m) {
                $min = $m['min'] ?? 0;
                $max = $m['max'] ?? 0;
                $spread = 0;
                if ($min > 0) {
                    $spread = (($max - $min) / $min) * 100;
                }
                $metrics[$key]['spread'] = $spread;
            }

            // Chart data: Monthly comparison from 2023 to present
            $chartData = [];
            $allPricesForChart = $metricsQuery->get();
            $years = range(2023, now()->year);
            foreach ($years as $y) {
                $monthlyData = [];
                for ($m = 1; $m <= 12; $m++) {
                    $avg = $allPricesForChart
                        ->where('date', '>=', \Carbon\Carbon::create($y, $m, 1)->startOfMonth())
                        ->where('date', '<=', \Carbon\Carbon::create($y, $m, 1)->endOfMonth())
                        ->avg('min_price');
                    $monthlyData[] = $avg ? round($avg, 2) : null;
                }
                $chartData[$y] = $monthlyData;
            }
        }

        return Inertia::render($currentPage->component, [
            'pages' => $pages,
            'currentPage' => $currentPage,
            'countries' => $countries,
            'suppliers' => $suppliers,
            'products' => $productsProp,
            'historicalData' => $historicalData,
            'pricesData' => $pricesData,
            'chartData' => $chartData ?? [],
            'metrics' => $metrics,
            'availableDates' => ProductPrice::when($countryId, function($q) use ($countryId) {
                    $q->whereHas('product', fn($p) => $p->where('country_id', $countryId));
                })
                ->when($supplierId, fn($q) => $q->where('supplier_id', $supplierId))
                ->when($productId, fn($q) => $q->where('product_id', $productId))
                ->selectRaw('YEAR(date) as year, WEEK(date, 1) as week')
                ->distinct()
                ->orderBy('year', 'desc')
                ->orderBy('week', 'desc')
                ->get()
                ->groupBy('year')
                ->map(fn($weeks, $year) => [
                    'year' => $year,
                    'weeks' => $weeks
                ])
                ->values(),
            'filters' => [
                'country_id' => $countryId,
                'product_id' => $productId,
                'supplier_id' => $supplierId,
                'date_range' => $dateRange
            ],
            'settings' => \App\Models\Setting::all()->pluck('value', 'key'),
        ]);
    }

    public function sendContactEmail(HttpRequest $request)
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        $user = auth()->user();
        $recipient = \App\Models\Setting::get('contact_email', config('mail.from.address'));

        $data = [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'company' => $user->company_name,
            'subject' => $validated['subject'],
            'message' => $validated['message'],
        ];

        \Illuminate\Support\Facades\Mail::to($recipient)->send(new \App\Mail\DirectContact($data));

        return redirect()->back()->with('success', 'Sua mensagem foi enviada com sucesso!');
    }
}
