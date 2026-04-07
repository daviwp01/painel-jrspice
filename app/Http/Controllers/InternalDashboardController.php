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
        
        $allowedPages = $pages->filter(function ($page) use ($user) {
            return $user->canAccessPage($page->slug);
        });

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

        // 1. Basic properties
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

        // 2. Load technical data IF needed
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

        // a. Countries Activity
        $latestUpdatesSubquery = ProductPrice::select('products.country_id')
            ->join('products', 'product_prices.product_id', '=', 'products.id')
            ->selectRaw('MAX(product_prices.date) as latest_weekly_update')
            ->whereBetween('product_prices.date', [$startOfWeek->format('Y-m-d'), $endOfWeek->format('Y-m-d')])
            ->groupBy('products.country_id');

        $countries = Country::leftJoinSub($latestUpdatesSubquery, 'latest_updates', function ($join) {
                $join->on('countries.id', '=', 'latest_updates.country_id');
            })
            ->select('countries.*', 'latest_updates.latest_weekly_update')
            ->orderByRaw('latest_weekly_update DESC')
            ->orderBy('countries.name')
            ->get();

        // b. Settings for Defaults
        $defaultCountryId  = \App\Models\Setting::get('default_filter_country_id');
        $defaultProductIds = \App\Models\Setting::get('default_filter_product_ids') ?? [];

        // c. Check if it's first visit to apply default filters
        $isFirstVisit = !$request->hasAny(['country_id', 'product_id', 'supplier_id', 'date_range']);
        
        $countryId = $viewData['filters']['country_id'];
        $productId = $viewData['filters']['product_id'];
        $supplierId = $viewData['filters']['supplier_id'];
        $dateRange = $viewData['filters']['date_range'];
        $sortField = $viewData['filters']['sort_field'];
        $sortDir = $viewData['filters']['sort_direction'];

        if ($isFirstVisit && $defaultCountryId) {
            $countryId = $defaultCountryId;
        }

        // Forced Defaults for Demo (Overwrites everything)
        if ($currentPage->component === 'Dashboard/Demo') {
            $countryId = $defaultCountryId;
            $productId = is_array($defaultProductIds) ? ($defaultProductIds[0] ?? null) : (json_decode($defaultProductIds, true)[0] ?? null);
            $supplierId = null;
            $dateRange = 'Todos';
        }

        if (!$countryId && $countries->isNotEmpty()) {
            $countryId = $countries->first()->id;
        }

        // d. Products for Sidebar
        $productsSidebar = Product::where('country_id', $countryId)->orderBy('name')->get();

        // Product Auto-Selection logic
        if ($productId && !Product::where('id', $productId)->exists()) $productId = null;
        if (!$productId && $countryId == $defaultCountryId && !empty($defaultProductIds)) {
            $decodedIds = is_array($defaultProductIds) ? $defaultProductIds : json_decode($defaultProductIds, true);
            $defaultProduct = $productsSidebar->first(fn($p) => in_array($p->id, $decodedIds));
            if ($defaultProduct) $productId = $defaultProduct->id;
        }
        if (!$productId && $productsSidebar->isNotEmpty()) {
            $productId = $productsSidebar->first()->id;
        }

        // e. Suppliers for Sidebar
        $suppliersQuery = Supplier::whereHas('productPrices.product', function($q) use ($countryId) {
            if ($countryId) $q->where('country_id', $countryId);
        });
        if ($currentPage->component === 'Dashboard/PriceTable') {
            $suppliersQuery->whereHas('productPrices', function($q) use ($startOfWeek, $endOfWeek) {
                $q->whereBetween('date', [$startOfWeek->format('Y-m-d'), $endOfWeek->format('Y-m-d')]);
            });
        }
        $suppliers = $suppliersQuery->orderBy('name')->get();

        // f. Main Content logic
        $productsQuery = Product::query();
        $productsQuery->with(['prices' => function($query) use ($supplierId) {
            $query->with('supplier')->orderBy('date', 'desc')->orderBy('price', 'asc');
            if ($supplierId) $query->where('supplier_id', $supplierId);
        }]);

        if ($countryId) $productsQuery->where('country_id', $countryId);

        if ($currentPage->component === 'Dashboard/PriceTable') {
            $productsQuery->whereHas('prices', function($q) use ($supplierId, $startOfWeek, $endOfWeek) {
                $q->whereBetween('date', [$startOfWeek->format('Y-m-d'), $endOfWeek->format('Y-m-d')]);
                if ($supplierId) $q->where('supplier_id', $supplierId);
            });
            
            if ($sortField === 'latest_price' || $sortField === 'variation') {
                $latestPriceSub = ProductPrice::select('product_id', 'price as l_price')
                    ->whereIn('id', function($q) use ($supplierId, $startOfWeek, $endOfWeek) {
                        $q->selectRaw('MAX(id)')->from('product_prices')->whereBetween('date', [$startOfWeek->format('Y-m-d'), $endOfWeek->format('Y-m-d')]);
                        if ($supplierId) $q->where('supplier_id', $supplierId);
                        $q->groupBy('product_id');
                    });
                $productsQuery->leftJoinSub($latestPriceSub, 'lp', 'lp.product_id', '=', 'id')->orderBy('l_price', $sortDir);
            } else {
                $productsQuery->orderBy('name', $sortDir);
            }
            $productsProp = $productsQuery->select('products.*')->paginate(20)->withQueryString();
        } else {
            $productsProp = $productsSidebar;
        }

        // g. Component Specific Data
        if ($currentPage->component === 'Dashboard/HistoricalData') {
            $hDataQuery = ProductPrice::with(['product.country', 'supplier'])
                ->join('products', 'products.id', '=', 'product_prices.product_id')
                ->join('countries', 'countries.id', '=', 'products.country_id')
                ->join('suppliers', 'suppliers.id', '=', 'product_prices.supplier_id')
                ->select('product_prices.*');

            if ($countryId) $hDataQuery->where('products.country_id', $countryId);
            if ($productId) $hDataQuery->where('product_id', $productId);
            if ($supplierId) $hDataQuery->where('supplier_id', $supplierId);
            if ($dateRange && $dateRange !== 'Todos') {
                $parts = explode('-', $dateRange);
                if (count($parts) === 2) $hDataQuery->whereRaw('YEAR(date) = ? AND WEEK(date, 1) = ?', [$parts[0], $parts[1]]);
            }

            $hMap = ['name' => 'products.name', 'country' => 'countries.name', 'supplier' => 'suppliers.name', 'date' => 'date', 'price' => 'price'];
            $hSort = $hMap[strtolower($sortField)] ?? 'date';
            $viewData['historicalData'] = $hDataQuery->orderBy($hSort, $sortDir)->paginate(20)->withQueryString();
            
            $viewData['availableDates'] = ProductPrice::when($countryId, fn($q) => $q->whereHas('product', fn($p) => $p->where('country_id', $countryId)))
                ->when($supplierId, fn($q) => $q->where('supplier_id', $supplierId))
                ->when($productId, fn($q) => $q->where('product_id', $productId))
                ->selectRaw('YEAR(date) as year, WEEK(date, 1) as week')->distinct()->orderBy('year', 'desc')->orderBy('week', 'desc')
                ->get()->groupBy('year')->map(fn($weeks, $year) => ['year' => $year, 'weeks' => $weeks])->values();
        }

        if ($currentPage->component === 'Dashboard/Show' || $currentPage->component === 'Dashboard/Demo') {
            if ($productId) {
                $allPrices = ProductPrice::where('product_id', $productId)
                    ->when($countryId, fn($q) => $q->whereHas('product', fn($px) => $px->where('country_id', $countryId)))
                    ->get();

                $viewData['pricesData'] = $allPrices->load('supplier')->sortBy('date')->values();
                $viewData['metrics'] = $this->calculateMetrics($allPrices);
                $viewData['chartData'] = $this->calculateChartHistorical($allPrices);
            }
        }

        // Final view data update
        $viewData['countries'] = $countries;
        $viewData['suppliers'] = $suppliers;
        $viewData['products'] = $productsProp;
        $viewData['settings'] = \App\Models\Setting::all()->pluck('value', 'key');
        $viewData['filters'] = [
            'country_id' => $countryId,
            'product_id' => $productId,
            'supplier_id' => $supplierId,
            'date_range' => $dateRange,
            'sort_field' => $sortField,
            'sort_direction' => $sortDir
        ];
    }

    private function calculateMetrics($prices)
    {
        $now = Carbon::now();
        $subWeek = $now->copy()->subWeek();
        $startOfYear = $now->copy()->startOfYear();
        
        $latestPricesRaw = $prices->filter(fn($p) => $p->date->isAfter($subWeek) || $p->date->isSameDay($subWeek));
        $yearBest = $prices->filter(fn($p) => $p->date->isAfter($startOfYear) || $p->date->isSameDay($startOfYear))
            ->groupBy(fn($p) => $p->date->format('Y-W'))->map(fn($group) => (float)$group->min('price'));
        $bestWeeklyPrices = $prices->groupBy(fn($p) => $p->date->format('Y-W'))->map(fn($group) => (float)$group->min('price'));

        $metrics = [
            'latest' => ['label' => 'ÚLTIMA SEMANA', 'min' => (float)$latestPricesRaw->min('price'), 'max' => (float)$latestPricesRaw->max('price')],
            'year' => ['label' => 'ANO: ' . $now->year, 'min' => (float)$yearBest->min(), 'max' => (float)$yearBest->max()],
            'all' => ['label' => 'DESDE: ' . ($prices->min('date')?->year ?? $now->year), 'min' => (float)$bestWeeklyPrices->min(), 'max' => (float)$bestWeeklyPrices->max()]
        ];

        foreach ($metrics as $key => $m) {
            $spread = 0;
            if ($m['min'] > 0) $spread = (($m['max'] - $m['min']) / $m['min']) * 100;
            $metrics[$key]['spread'] = $spread;
        }
        return $metrics;
    }

    private function calculateChartHistorical($prices)
    {
        $chartData = [];
        $pricesByYearMonth = $prices->groupBy(fn($p) => $p->date->format('Y-n'));
        $globalFirstYear = $prices->min('date')?->year ?? now()->year;
        
        foreach (range($globalFirstYear, now()->year) as $y) {
            $monthly = [];
            for ($m = 1; $m <= 12; $m++) {
                $val = $pricesByYearMonth->get("$y-$m")?->min('price');
                $monthly[] = $val ? (float)round($val, 2) : null;
            }
            $chartData[$y] = $monthly;
        }
        return $chartData;
    }

    public function sendContactEmail(HttpRequest $request)
    {
        $validated = $request->validate(['subject' => 'required|string|max:255', 'message' => 'required|string']);
        $user = auth()->user();
        $recipient = \App\Models\Setting::get('contact_email', config('mail.from.address'));
        \Illuminate\Support\Facades\Mail::to($recipient)->send(new \App\Mail\DirectContact([
            'name' => $user->name, 'email' => $user->email, 'phone' => $user->phone, 'company' => $user->company_name, 'subject' => $validated['subject'], 'message' => $validated['message']
        ]));
        return redirect()->back()->with('success', 'Sua mensagem foi enviada com sucesso!');
    }

    public function exportPricesPdf(HttpRequest $request)
    {
        ini_set('memory_limit', '512M');
        set_time_limit(300);
        try {
            $countryIds = $request->input('country_ids');
            $query = Country::orderBy('name');
            if ($countryIds && $countryIds !== 'all') $query->whereIn('id', is_array($countryIds) ? $countryIds : explode(',', $countryIds));
            $countries = $query->get();
            
            $exportData = [];
            foreach ($countries as $country) {
                $products = Product::where('country_id', $country->id)->with(['prices' => fn($q) => $q->orderBy('date', 'desc')->with('supplier')])->orderBy('name')->get()->map(function($p) {
                    $latest = $p->prices->first();
                    $prev = $p->prices->first(fn($pr) => $pr->date->format('Y-m-d') !== ($latest ? $latest->date->format('Y-m-d') : null));
                    $lp = $latest ? (float)$latest->price : null;
                    $pp = $prev ? (float)$prev->price : $lp;
                    $variation = ($lp && $pp && $pp > 0) ? (($lp - $pp) / $pp) * 100 : 0;
                    return (object)['name' => $p->name, 'latestPrice' => $lp, 'previousPrice' => $pp, 'variation' => $variation, 'status' => $variation > 0 ? 'up' : ($variation < 0 ? 'down' : (!$prev && $latest ? 'new' : 'none')), 'supplier' => $latest ? $latest->supplier?->name : 'N/A'];
                });
                
                $exportData[] = (object)['name' => $country->name, 'products' => $products];
            }
            return \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.price-table', ['exportData' => $exportData, 'date' => now()->format('d/m/Y')])->download('tabela-de-preco-jrspice-' . now()->format('d-m-Y') . '.pdf');
        } catch (\Exception $e) {
            return response()->json(['error' => 'Falha técnica.', 'message' => $e->getMessage()], 200);
        }
    }
}
