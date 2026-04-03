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

        // --- Current Week Logic (Monday to Sunday) ---
        $now = \Carbon\Carbon::now();
        $startOfWeek = $now->copy()->startOfWeek(\Carbon\Carbon::MONDAY);
        $endOfWeek = $now->copy()->endOfWeek(\Carbon\Carbon::SUNDAY);
        // ----------------------------------------------

        // 1. Load Countries prioritized by most recent activity THIS WEEK
        // We use a subquery to calculate the latest update per country to avoid GROUP BY issues
        $latestUpdatesSubquery = ProductPrice::select('products.country_id')
            ->join('products', 'product_prices.product_id', '=', 'products.id')
            ->selectRaw('MAX(product_prices.date) as latest_weekly_update')
            ->whereBetween('product_prices.date', [$startOfWeek->format('Y-m-d'), $endOfWeek->format('Y-m-d')])
            ->groupBy('products.country_id');

        $countries = Country::leftJoinSub($latestUpdatesSubquery, 'latest_updates', function ($join) {
                $join->on('countries.id', '=', 'latest_updates.country_id');
            })
            ->select('countries.*', 'latest_updates.latest_weekly_update')
            ->orderByRaw('latest_weekly_update DESC') // Countries with newest weekly data first
            ->orderBy('countries.name')               // Then alphabetical
            ->get();

        $countryId = $request->query('country_id');
        $supplierId = $request->query('supplier_id');
        $productId = $request->query('product_id');
        $dateRange = $request->query('date_range', 'Todos'); // Default to 'Todos'
        $sortField = $request->query('sort_field', 'name'); // Default sort field
        $sortDir = $request->query('sort_direction', 'asc'); // Default direction

        // --- Default Filter Config (applied only when no explicit filter in URL) ---
        $defaultCountryId  = \App\Models\Setting::get('default_filter_country_id');
        $defaultProductIds = \App\Models\Setting::get('default_filter_product_ids') ?? [];

        $isFirstVisit = !$request->hasAny(['country_id', 'product_id', 'supplier_id', 'date_range']);

        if ($isFirstVisit && $defaultCountryId) {
            $countryId = $defaultCountryId;
        }

        // --- Demo Page Strict Enforcement (Force Defaults) ---
        if ($currentPage->component === 'Dashboard/Demo') {
            $countryId = $defaultCountryId;
            $productId = is_array($defaultProductIds) ? ($defaultProductIds[0] ?? null) : (json_decode($defaultProductIds, true)[0] ?? null);
            $supplierId = null;
            $dateRange = 'Todos';
        }

        // 2. Initial Country Selection (if none provided, fallback to first with data)
        if (!$countryId && $countries->isNotEmpty()) {
            $countryId = $countries->first()->id;
        }

        // --- Smart Prop Filtering for Selectors ---
        
        // 1. Products in the selected country (Sidebar list)
        $productsForSidebarQuery = Product::query();
        if ($countryId) {
            $productsForSidebarQuery->where('country_id', $countryId);
        }
        $productsSidebar = $productsForSidebarQuery->orderBy('name')->get();

        // 2. Validate or Auto-Select productId
        if ($productId) {
            $productExists = Product::where('id', $productId)->exists();
            if (!$productExists) $productId = null;
        }

        // Apply default product if the selected country is the default country 
        // AND no specific product was requested in the URL.
        if (!$productId && $countryId == $defaultCountryId && !empty($defaultProductIds)) {
            $defaultProduct = $productsSidebar->first(fn($p) => in_array($p->id, $defaultProductIds));
            if ($defaultProduct) {
                $productId = $defaultProduct->id;
            }
        }

        // Final fallback: first product in country (alphabetical)
        if (!$productId && $productsSidebar->isNotEmpty()) {
            $productId = $productsSidebar->first()->id;
        }

        // 3. Suppliers list for Sidebar: all that have prices for products in this country
        $suppliersQuery = Supplier::whereHas('productPrices.product', function($q) use ($countryId) {
                if ($countryId) $q->where('country_id', $countryId);
            });
        
        // If on PriceTable, only show suppliers with prices THIS WEEK
        if ($currentPage->component === 'Dashboard/PriceTable') {
            $suppliersQuery->whereHas('productPrices', function($q) use ($startOfWeek, $endOfWeek) {
                $q->whereBetween('date', [$startOfWeek->format('Y-m-d'), $endOfWeek->format('Y-m-d')]);
            });
        }
        
        $suppliers = $suppliersQuery->orderBy('name')->get();

        // Supplier selection: Default to 'All' unless explicitly provided.
        if (!$request->has('supplier_id')) {
            $supplierId = null;
        }
        
        // --- Logic for PriceTable / Main Content ---
        $productsQuery = Product::query();
        
        // Eager load everything needed
        $productsQuery->with(['prices' => function($query) use ($supplierId) {
            $query->with('supplier')->orderBy('date', 'desc')->orderBy('price', 'asc');
            if ($supplierId) {
                $query->where('supplier_id', $supplierId);
            }
        }]);

        if ($countryId) {
            $productsQuery->where('country_id', $countryId);
        }

        // Apply Current Week Filter ONLY for PriceTable component
        if ($currentPage->component === 'Dashboard/PriceTable') {
            $productsQuery->whereHas('prices', function($q) use ($supplierId, $startOfWeek, $endOfWeek) {
                $q->whereBetween('date', [$startOfWeek->format('Y-m-d'), $endOfWeek->format('Y-m-d')]);
                if ($supplierId) {
                    $q->where('supplier_id', $supplierId);
                }
            });
        }
        
        if ($supplierId) {
            $productsQuery->whereHas('prices', function($q) use ($supplierId) {
                $q->where('supplier_id', $supplierId);
            });
        }

        // --- SORTING LOGIC FOR PRICETABLE ---
        if ($sortField === 'latest_price' || $sortField === 'variation') {
            // Complex sorting: join with latest price subquery
            $latestPriceSub = ProductPrice::select('product_id', 'price as l_price')
                ->whereIn('id', function($q) use ($supplierId, $startOfWeek, $endOfWeek) {
                    $q->selectRaw('MAX(id)')
                      ->from('product_prices')
                      ->whereBetween('date', [$startOfWeek->format('Y-m-d'), $endOfWeek->format('Y-m-d')]);
                    if ($supplierId) $q->where('supplier_id', $supplierId);
                    $q->groupBy('product_id');
                });
            
            $productsQuery->leftJoinSub($latestPriceSub, 'lp', 'lp.product_id', '=', 'id');
            $productsQuery->orderBy('l_price', $sortDir);
        } else {
            // Default sort by name
            $productsQuery->orderBy($sortField === 'name' ? 'name' : 'name', $sortDir);
        }

        // Final Products list to be passed (paginated for PriceTable, full for others)
        if ($currentPage->component === 'Dashboard/PriceTable') {
            $products = $productsQuery->select('products.*')->paginate(20)->withQueryString();
        } else {
            $products = $productsQuery->select('products.*')->get();
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

            // JOIN everything needed for sorting
            $hDataQuery->join('countries', 'countries.id', '=', 'products.country_id')
                       ->join('suppliers', 'suppliers.id', '=', 'product_prices.supplier_id');

            // Map sort fields for HistoricalData
            $hMap = [
                'name' => 'products.name',
                'country' => 'countries.name',
                'supplier' => 'suppliers.name',
                'date' => 'date',
                'price' => 'price'
            ];
            $hSort = $hMap[$sortField] ?? 'date';

            $historicalData = $hDataQuery->orderBy($hSort, $sortDir)->paginate(20)->withQueryString();
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
            // NO dateRange filter here, so chart shows full history
            $pricesData = $pricesQuery->orderBy('date')->get();

            // Metrics: also ignoring supplier_id filter
            $metricsQuery = ProductPrice::where('product_id', $productId);
            if ($countryId) {
                $metricsQuery->whereHas('product', fn($q) => $q->where('country_id', $countryId));
            }
            $allPricesForMetrics = $metricsQuery->get();

            // Calculate spreading/metrics based on BEST PRICES (min price per week) 
            // for the HISTORICAL and YEAR cards (to match the chart lines).
            $bestWeeklyPrices = $allPricesForMetrics->groupBy(fn($p) => $p->date->format('Y-W'))
                ->map(fn($group) => (float)$group->min('price'));

            $now = \Carbon\Carbon::now();
            $subWeek = $now->copy()->subWeek();
            $startOfYear = $now->copy()->startOfYear();
            
            $globalFirstYear = $allPricesForMetrics->min('date')?->year ?? now()->year;

            // 1. LATEST WEEK: Should show the actual range of RAW OFFERS (from min to max received)
            $latestPricesRaw = $allPricesForMetrics->filter(fn($p) => $p->date->isAfter($subWeek) || $p->date->isSameDay($subWeek));

            // 2. YEAR BEST: Grouped by week to match the points seen on the chart
            $yearBest = $allPricesForMetrics->filter(fn($p) => $p->date->isAfter($startOfYear) || $p->date->isSameDay($startOfYear))
                ->groupBy(fn($p) => $p->date->format('Y-W'))
                ->map(fn($group) => (float)$group->min('price'));

            $metrics = [
                'latest' => [
                    'label' => 'ÚLTIMA SEMANA',
                    'sub_label' => 'RANGE DAS OFERTAS RECEBIDAS',
                    'min' => (float)$latestPricesRaw->min('price'),
                    'max' => (float)$latestPricesRaw->max('price')
                ],
                'year' => [
                    'label' => 'ANO: ' . $now->year,
                    'sub_label' => 'MENORES E MAIORES PREÇOS',
                    'min' => (float)$yearBest->min(),
                    'max' => (float)$yearBest->max()
                ],
                'all' => [
                    'label' => 'DESDE: ' . $globalFirstYear,
                    'sub_label' => 'MENORES E MAIORES PREÇOS',
                    'min' => (float)$bestWeeklyPrices->min(),
                    'max' => (float)$bestWeeklyPrices->max()
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

            // Chart data: Monthly comparison from the first record until present
            $chartData = [];
            $allPricesForChart = $metricsQuery->get();
            $pricesByYearMonth = $allPricesForChart->groupBy(fn($p) => $p->date->format('Y-n'));
            
            $globalFirstYear = $allPricesForChart->min('date')?->year ?? now()->year;
            $years = range($globalFirstYear, now()->year);
            
            foreach ($years as $y) {
                $monthlyData = [];
                for ($m = 1; $m <= 12; $m++) {
                    $key = "$y-$m";
                    $val = $pricesByYearMonth->get($key)?->min('price');
                    $monthlyData[] = $val ? (float)round($val, 2) : null;
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
                'date_range' => $dateRange,
                'sort_field' => $sortField,
                'sort_direction' => $sortDir
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

    public function exportPricesPdf(HttpRequest $request)
    {
        ini_set('memory_limit', '512M');
        set_time_limit(300);

        try {
            $countryIds = $request->input('country_ids'); // Array of IDs or 'all'
            
            $query = Country::orderBy('name');
            if ($countryIds && $countryIds !== 'all') {
                $ids = is_array($countryIds) ? $countryIds : explode(',', $countryIds);
                $query->whereIn('id', $ids);
            }
            
            $countries = $query->get();
            
            $exportData = [];
            $flagCache = []; // Cache temporário para não baixar a mesma bandeira várias vezes no mesmo PDF
            
            // Contexto seguro unificado para download das bandeiras
            $ctx = stream_context_create([
                'ssl' => ["verify_peer"=>false, "verify_peer_name"=>false],
                'http' => [
                    'timeout' => 3, // 3 segundos max por bandeira para não travar o processo
                    'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36\r\n"
                ]
            ]);

            foreach ($countries as $country) {
                $products = Product::where('country_id', $country->id)
                    ->with(['prices' => function($q) {
                        $q->orderBy('date', 'desc')->orderBy('price', 'asc')->with('supplier');
                    }])
                    ->orderBy('name')
                    ->get()
                    ->map(function($p) {
                        $latest = $p->prices->first();
                        
                        // Busca o primeiro preço que tenha uma DATA (YYYY-MM-DD) diferente da última
                        $latestDateStr = $latest ? $latest->date->format('Y-m-d') : null;
                        $previous = $p->prices->first(function($pr) use ($latestDateStr) {
                            return $pr->date->format('Y-m-d') !== $latestDateStr;
                        });
                        
                        $latestPrice = $latest ? (float)$latest->price : null;
                        $previousPrice = $previous ? (float)$previous->price : $latestPrice;
                        
                        $variation = 0;
                        if ($latestPrice && $previousPrice && $previousPrice > 0) {
                            $variation = (($latestPrice - $previousPrice) / $previousPrice) * 100;
                        }
                        
                        $status = 'none';
                        if ($variation > 0) $status = 'up';
                        else if ($variation < 0) $status = 'down';
                        
                        if (!$previous && $latest) $status = 'new';
                        
                        return (object)[
                            'name' => $p->name,
                            'latestPrice' => $latestPrice,
                            'previousPrice' => $previousPrice,
                            'variation' => $variation,
                            'status' => $status,
                            'supplier' => $latest ? $latest->supplier?->name : 'N/A'
                        ];
                    });
                    
                // Handle Flag base64
                $flagBase64 = null;
                $countryMap = [
                    'brasil' => 'br', 'china' => 'cn', 'india' => 'in', 'indonesia' => 'id', 'vietna' => 'vn', 'vietnam' => 'vn',
                    'madagascar' => 'mg', 'egito' => 'eg', 'egypt' => 'eg', 'espanha' => 'es', 'spain' => 'es', 
                    'argentina' => 'ar', 'paraguai' => 'py', 'paraguay' => 'py', 'uruguai' => 'uy', 'uruguay' => 'uy',
                    'mexico' => 'mx', 'canada' => 'ca', 'franca' => 'fr', 'france' => 'fr', 'alemanha' => 'de', 'germany' => 'de', 
                    'italia' => 'it', 'italy' => 'it', 'reino unido' => 'gb', 'united kingdom' => 'gb', 'uk' => 'gb', 
                    'japao' => 'jp', 'japan' => 'jp', 'turquia' => 'tr', 'turkey' => 'tr', 'russia' => 'ru', 
                    'africa do sul' => 'za', 'south africa' => 'za', 'nigeria' => 'ng', 'marrocos' => 'ma', 'morocco' => 'ma',
                    'peru' => 'pe', 'colombia' => 'co', 'chile' => 'cl', 'bulgaria' => 'bg', 'guatemala' => 'gt', 
                    'paquistao' => 'pk', 'pakistan' => 'pk', 'sri lanka' => 'lk', 'malaysia' => 'my', 'malasia' => 'my',
                    'tailandia' => 'th', 'thailand' => 'th', 'equador' => 'ec', 'equator' => 'ec', 'holanda' => 'nl', 
                    'netherlands' => 'nl', 'portugal' => 'pt', 'grecia' => 'gr', 'greece' => 'gr',
                    'panama' => 'pa', 'costa rica' => 'cr', 'honduras' => 'hn', 'salvador' => 'sv', 'nicaragua' => 'ni',
                    'venezuela' => 've', 'bolivia' => 'bo', 'africa' => 'za', 'emirados' => 'ae', 'dubai' => 'ae',
                    'arabia' => 'sa', 'cambodia' => 'kh', 'catar' => 'qa', 'israel' => 'il',
                    'niger' => 'ne', 'etiopia' => 'et', 'sirialanka' => 'lk', 'sri-lanka' => 'lk'
                ];
                
                // Normalização robusta: mb_strtolower para acentos em maiúsculo
                $rawName = mb_strtolower(trim($country->name), 'UTF-8');
                $parts = explode('/', $rawName);
                $normalizedName = trim($parts[0]);
                
                // Remover acentos básicos
                $normalizedName = str_replace(
                    ['á', 'à', 'â', 'ã', 'é', 'è', 'ê', 'í', 'ì', 'î', 'ó', 'ò', 'ô', 'õ', 'ú', 'ù', 'û', 'ç', 'ñ'], 
                    ['a', 'a', 'a', 'a', 'e', 'e', 'e', 'i', 'i', 'i', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'c', 'n'], 
                    $normalizedName
                );
                
                $code = $countryMap[$normalizedName] ?? null;

                if ($code) {
                    if (isset($flagCache[$code])) {
                        $flagBase64 = $flagCache[$code];
                    } else {
                        try {
                            $url = "https://flagcdn.com/w160/{$code}.png";
                            $imgData = @file_get_contents($url, false, $ctx);
                            if ($imgData) {
                                $flagBase64 = 'data:image/png;base64,' . base64_encode($imgData);
                                $flagCache[$code] = $flagBase64;
                            }
                        } catch (\Exception $e) {
                            \Illuminate\Support\Facades\Log::error("PDF Flag Export Error: " . $e->getMessage());
                            // Fallback silencioso: se a bandeira falhar, o PDF continua sem ela
                        }
                    }
                }

                $exportData[] = (object)[
                    'name' => $country->name,
                    'flag' => $flagBase64,
                    'products' => $products
                ];
            }

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.price-table', [
                'exportData' => $exportData,
                'date' => now()->format('d/m/Y')
            ]);
            
            $filename = 'tabela-de-preco-jrspice-' . now()->format('d-m-Y') . '.pdf';
            return $pdf->download($filename);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("PDF Export Fatal Error: " . $e->getMessage() . " in " . $e->getFile() . " line " . $e->getLine());
            return response()->json([
                'is_error' => true,
                'error' => 'Falha técnica capturada.',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 200);
        }
    }
}
