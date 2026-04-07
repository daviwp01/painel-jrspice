<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request as HttpRequest;
use Inertia\Inertia;
use App\Models\DashboardPage;
use App\Models\Country;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Supplier;
use Carbon\Carbon;

class InternalDashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $pages = DashboardPage::where('is_active', true)->orderBy('order')->get();
        $allowedPages = $pages->filter(fn($page) => $user->canAccessPage($page->slug));

        if ($allowedPages->isEmpty()) {
            return Inertia::render('Dashboard/Empty');
        }

        return redirect()->route('dashboard.page', ['slug' => $allowedPages->first()->slug]);
    }

    public function show(HttpRequest $request, $slug)
    {
        $user = auth()->user();
        $currentPage = DashboardPage::where('slug', $slug)->firstOrFail();

        if (!$user->canAccessPage($currentPage->slug)) {
            abort(403);
        }

        $viewData = [
            'currentPage' => $currentPage,
            'filters' => [
                'country_id' => $request->query('country_id'),
                'product_id' => $request->query('product_id'),
                'supplier_id' => $request->query('supplier_id'),
                'date_range' => $request->query('date_range', 'Todos'),
                'sort_field' => $request->query('sort_field', 'name'),
                'sort_direction' => $request->query('sort_direction', 'asc')
            ]
        ];

        $technicalDashboards = ['Dashboard/Show', 'Dashboard/PriceTable', 'Dashboard/HistoricalData', 'Dashboard/Demo'];
        if (in_array($currentPage->component, $technicalDashboards)) {
            $this->loadDashboardData($request, $currentPage, $viewData);
        }

        return Inertia::render($currentPage->component, $viewData);
    }

    private function loadDashboardData(HttpRequest $request, $currentPage, &$viewData)
    {
        $user = auth()->user();
        $now = Carbon::now();
        $startOfWeek = $now->copy()->startOfWeek(Carbon::MONDAY);
        $endOfWeek = $now->copy()->endOfWeek(Carbon::SUNDAY);

        // 1. Countries - Using select() for optimization but keeping logic
        $latestUpdatesSubquery = ProductPrice::select('products.country_id')
            ->join('products', 'product_prices.product_id', '=', 'products.id')
            ->selectRaw('MAX(product_prices.date) as latest_weekly_update')
            ->whereBetween('product_prices.date', [$startOfWeek->format('Y-m-d'), $endOfWeek->format('Y-m-d')])
            ->groupBy('products.country_id');

        $countries = Country::leftJoinSub($latestUpdatesSubquery, 'latest_updates', fn($join) => $join->on('countries.id', '=', 'latest_updates.country_id'))
            ->select('countries.id', 'countries.name', 'latest_updates.latest_weekly_update')
            ->orderByRaw('latest_weekly_update DESC')->orderBy('name')->get();

        $defaultCountryId = \App\Models\Setting::get('default_filter_country_id');
        $defaultProductIds = \App\Models\Setting::get('default_filter_product_ids') ?? [];
        $isFirstVisit = !$request->hasAny(['country_id', 'product_id', 'supplier_id', 'date_range']);
        
        $countryId = $viewData['filters']['country_id'];
        $productId = $viewData['filters']['product_id'];
        $supplierId = $viewData['filters']['supplier_id'];
        $dateRange = $viewData['filters']['date_range'];

        if ($isFirstVisit && $defaultCountryId) $countryId = $defaultCountryId;
        if ($currentPage->component === 'Dashboard/Demo') {
            $countryId = $defaultCountryId;
            $decodedIds = is_array($defaultProductIds) ? $defaultProductIds : json_decode($defaultProductIds, true);
            $productId = $decodedIds[0] ?? null;
            $supplierId = null; $dateRange = 'Todos';
        }
        if (!$countryId && $countries->isNotEmpty()) $countryId = $countries->first()->id;

        // 2. Sidebar Products - Minimal columns
        $productsSidebar = Product::where('country_id', $countryId)->select('id', 'name', 'country_id', 'harvest_month')->orderBy('name')->get();

        if ($productId && !Product::where('id', $productId)->exists()) $productId = null;
        if (!$productId && $countryId == $defaultCountryId && !empty($defaultProductIds)) {
            $decodedIds = is_array($defaultProductIds) ? $defaultProductIds : json_decode($defaultProductIds, true);
            $defaultProduct = $productsSidebar->first(fn($p) => in_array($p->id, $decodedIds));
            if ($defaultProduct) $productId = $defaultProduct->id;
        }
        if (!$productId && $productsSidebar->isNotEmpty()) $productId = $productsSidebar->first()->id;

        // 3. Suppliers
        $suppliers = Supplier::whereHas('productPrices.product', function($q) use ($countryId) {
            if ($countryId) $q->where('country_id', $countryId);
        })->select('id', 'name')->orderBy('name')->get();

        // 4. Main Content Logic
        if ($currentPage->component === 'Dashboard/PriceTable') {
            $productsQuery = Product::query()->where('country_id', $countryId);
            $productsQuery->whereHas('prices', function($q) use ($supplierId, $startOfWeek, $endOfWeek) {
                $q->whereBetween('date', [$startOfWeek->format('Y-m-d'), $endOfWeek->format('Y-m-d')]);
                if ($supplierId) $q->where('supplier_id', $supplierId);
            });
            
            // Reverting to with('prices') but selecting specific columns
            $productsQuery->with(['prices' => function($query) use ($supplierId) {
                if ($supplierId) $query->where('supplier_id', $supplierId);
                $query->with('supplier:id,name')->orderBy('date', 'desc')->orderBy('price', 'asc');
            }]);

            $sortField = $viewData['filters']['sort_field']; $sortDir = $viewData['filters']['sort_direction'];
            if ($sortField === 'latest_price' || $sortField === 'variation') {
                $latestPriceSub = ProductPrice::select('product_id', 'price as l_price')->whereIn('id', function($q) use ($supplierId, $startOfWeek, $endOfWeek) {
                    $q->selectRaw('MAX(id)')->from('product_prices')->whereBetween('date', [$startOfWeek->format('Y-m-d'), $endOfWeek->format('Y-m-d')]);
                    if ($supplierId) $q->where('supplier_id', $supplierId); $q->groupBy('product_id');
                });
                $productsQuery->leftJoinSub($latestPriceSub, 'lp', 'lp.product_id', '=', 'id')->orderBy('l_price', $sortDir);
            } else { $productsQuery->orderBy('name', $sortDir); }
            
            $productsProp = $productsQuery->paginate(20)->withQueryString();
        } else {
            $productsProp = $productsSidebar;
        }

        if ($currentPage->component === 'Dashboard/HistoricalData') {
            $hDataQuery = ProductPrice::with(['product:id,name,country_id', 'product.country:id,name', 'supplier:id,name'])
                ->join('products', 'products.id', '=', 'product_prices.product_id')
                ->select('product_prices.*');
            if ($countryId) $hDataQuery->where('products.country_id', $countryId);
            if ($productId) $hDataQuery->where('product_id', $productId);
            if ($supplierId) $hDataQuery->where('supplier_id', $supplierId);
            if ($dateRange && $dateRange !== 'Todos') {
                $parts = explode('-', $dateRange);
                if (count($parts) === 2) $hDataQuery->whereRaw('YEAR(product_prices.date) = ? AND WEEK(product_prices.date, 1) = ?', [$parts[0], $parts[1]]);
            }
            $viewData['historicalData'] = $hDataQuery->orderBy('product_prices.date', $viewData['filters']['sort_direction'])->paginate(20)->withQueryString();
            
            $viewData['availableDates'] = ProductPrice::when($countryId, fn($q) => $q->whereHas('product', fn($p) => $p->where('country_id', $countryId)))
                ->selectRaw('YEAR(date) as year, WEEK(date, 1) as week')->distinct()->orderBy('year', 'desc')->orderBy('week', 'desc')
                ->get()->groupBy('year')->map(fn($weeks, $year) => ['year' => $year, 'weeks' => $weeks])->values();
        }

        if ($currentPage->component === 'Dashboard/Show' || $currentPage->component === 'Dashboard/Demo') {
            if ($productId) {
                // Fetching pricesData like it was originally, but keeping it concise
                $prices = ProductPrice::where('product_id', $productId)->with('supplier:id,name')->orderBy('date', 'desc')->get();
                $viewData['pricesData'] = $prices;
                $viewData['metrics'] = $this->calculateMetrics($prices);
                $viewData['chartData'] = $this->calculateChartHistorical($prices);
            }
        }

        $viewData['countries'] = $countries;
        $viewData['suppliers'] = $suppliers;
        $viewData['products'] = $productsProp;
        $viewData['filters'] = ['country_id'=>$countryId, 'product_id'=>$productId, 'supplier_id'=>$supplierId, 'date_range'=>$dateRange, 'sort_field'=>$viewData['filters']['sort_field'], 'sort_direction'=>$viewData['filters']['sort_direction']];
        $viewData['settings'] = \App\Models\Setting::all()->pluck('value', 'key');
    }

    private function calculateMetrics($prices) {
        $now = Carbon::now(); $subWeek = $now->copy()->subWeek(); $startOfYear = $now->copy()->startOfYear();
        $latest = $prices->filter(fn($p) => $p->date->isAfter($subWeek) || $p->date->isSameDay($subWeek));
        $yearBest = $prices->filter(fn($p) => $p->date->isAfter($startOfYear) || $p->date->isSameDay($startOfYear))->groupBy(fn($p) => $p->date->format('Y-W'))->map(fn($group) => (float)$group->min('price'));
        $bestWeekly = $prices->groupBy(fn($p) => $p->date->format('Y-W'))->map(fn($group) => (float)$group->min('price'));
        
        $metrics = [
            'latest' => [
                'label' => 'ÚLTIMA SEMANA',
                'min' => (float)$latest->min('price'),
                'max' => (float)$latest->max('price')
            ],
            'year' => [
                'label' => 'ANO: ' . $now->year,
                'min' => (float)$yearBest->min(),
                'max' => (float)$yearBest->max()
            ],
            'all' => [
                'label' => 'DESDE: ' . ($prices->min('date')?->year ?? $now->year),
                'min' => (float)$bestWeekly->min(),
                'max' => (float)$bestWeekly->max()
            ]
        ];
        foreach ($metrics as $key => $m) {
            $metrics[$key]['spread'] = ($m['min'] > 0) ? (($m['max'] - $m['min']) / $m['min']) * 100 : 0;
        }
        return $metrics;
    }

    private function calculateChartHistorical($prices) {
        $chartData = [];
        $pricesByYM = $prices->groupBy(fn($p) => $p->date->format('Y-n'));
        $firstY = $prices->min('date')?->year ?? now()->year;
        foreach (range($firstY, now()->year) as $y) {
            $mData = [];
            for ($m = 1; $m <= 12; $m++) {
                $val = $pricesByYM->get("$y-$m")?->min('price');
                $mData[] = $val ? (float)round($val, 2) : null;
            }
            $chartData[$y] = $mData;
        }
        return $chartData;
    }

    public function sendContactEmail(HttpRequest $request) {
        $v = $request->validate(['subject' => 'required', 'message' => 'required']);
        $u = auth()->user(); $recipient = \App\Models\Setting::get('contact_email', config('mail.from.address'));
        \Illuminate\Support\Facades\Mail::to($recipient)->send(new \App\Mail\DirectContact(['name'=>$u->name, 'email'=>$u->email, 'phone'=>$u->phone, 'company'=>$u->company_name, 'subject'=>$v['subject'], 'message'=>$v['message']]));
        return redirect()->back()->with('success', 'Mensagem enviada!');
    }

    public function exportPricesPdf(HttpRequest $request) {
        ini_set('memory_limit', '512M');
        try {
            $countryIds = $request->input('country_ids');
            $countriesQuery = Country::orderBy('name');
            if ($countryIds && $countryIds !== 'all') {
                $ids = is_array($countryIds) ? $countryIds : explode(',', $countryIds);
                $countriesQuery->whereIn('id', $ids);
            }
            $countries = $countriesQuery->get();
            $exportData = [];
            $flagCache = [];
            $context = stream_context_create(['ssl'=>["verify_peer"=>false,"verify_peer_name"=>false],'http'=>['timeout'=>5]]);
            
            foreach ($countries as $country) {
                $flagBase64 = null;
                $countryMap = [
                    'brasil' => 'br', 'china' => 'cn', 'india' => 'in', 'indonesia' => 'id', 'vietna' => 'vn', 'vietnam' => 'vn',
                    'madagascar' => 'mg', 'egito' => 'eg', 'egypt' => 'eg', 'espanha' => 'es', 'argentina' => 'ar', 'mexico' => 'mx'
                ];
                $normalizedName = str_replace(['á','ã','é','í','ó','õ','ú','ç'], ['a','a','e','i','o','o','u','c'], mb_strtolower(trim($country->name), 'UTF-8'));
                $countryCode = $countryMap[$normalizedName] ?? null;
                if ($countryCode) {
                    if (isset($flagCache[$countryCode])) {
                        $flagBase64 = $flagCache[$countryCode];
                    } else {
                        try {
                            $imgData = @file_get_contents("https://flagcdn.com/w160/{$countryCode}.png", false, $context);
                            if ($imgData) {
                                $flagBase64 = 'data:image/png;base64,' . base64_encode($imgData);
                                $flagCache[$countryCode] = $flagBase64;
                            }
                        } catch (\Exception $e) {}
                    }
                }
                $products = Product::where('country_id', $country->id)->with(['prices' => fn($q) => $q->orderBy('date', 'desc')->with('supplier:id,name')])->orderBy('name')->get()->map(function($product) {
                    $latest = $product->prices->first();
                    $previous = $product->prices->first(fn($p) => $p->date->format('Y-m-d') !== ($latest ? $latest->date->format('Y-m-d') : null));
                    $latestPrice = $latest ? (float)$latest->price : null;
                    $previousPrice = $previous ? (float)$previous->price : $latestPrice;
                    $variation = ($latestPrice && $previousPrice && $previousPrice > 0) ? (($latestPrice - $previousPrice) / $previousPrice) * 100 : 0;
                    return (object)[
                        'name' => $product->name, 'latestPrice' => $latestPrice, 'previousPrice' => $previousPrice, 
                        'variation' => $variation, 'status' => $variation > 0 ? 'up' : ($variation < 0 ? 'down' : (!$previous && $latest ? 'new' : 'none')),
                        'supplier' => $latest ? $latest->supplier->name : 'N/A'
                    ];
                });
                $exportData[] = (object)[ 'name' => $country->name, 'flag' => $flagBase64, 'products' => $products ];
            }
            return \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.price-table', ['exportData' => $exportData, 'date' => now()->format('d/m/Y')])->download('precos.pdf');
        } catch (\Exception $e) { return response()->json(['error' => $e->getMessage()], 200); }
    }
}
