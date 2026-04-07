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
                'sort_field' => $request->query('sort_field'),
                'sort_direction' => $request->query('sort_direction')
            ]
        ];

        $technicalDashboards = ['Dashboard/Show', 'Dashboard/PriceTable', 'Dashboard/HistoricalData', 'Dashboard/Demo'];
        if (in_array($currentPage->component, $technicalDashboards)) {
            $this->loadDashboardData($request, $currentPage, $viewData);
        }

        // Ensure settings are loaded for ALL pages (like Contact)
        if (!isset($viewData['settings'])) {
            $viewData['settings'] = \App\Models\Setting::all()->pluck('value', 'key');
        }

        return Inertia::render($currentPage->component, $viewData);
    }

    private function loadDashboardData(HttpRequest $request, $currentPage, &$viewData)
    {
        $user = auth()->user();
        $now = Carbon::now();
        $startOfWeek = $now->copy()->startOfWeek(Carbon::MONDAY);
        $endOfWeek = $now->copy()->endOfWeek(Carbon::SUNDAY);

        // Optimized Countries retrieval
        $latestSub = ProductPrice::select('products.country_id')
            ->join('products', 'product_prices.product_id', '=', 'products.id')
            ->selectRaw('MAX(product_prices.date) as latest_weekly_update')
            ->whereBetween('product_prices.date', [$startOfWeek->format('Y-m-d'), $endOfWeek->format('Y-m-d')])
            ->groupBy('products.country_id');

        $countries = Country::leftJoinSub($latestSub, 'latest_updates', fn($j) => $j->on('countries.id', '=', 'latest_updates.country_id'))
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
        }
        if (!$countryId && $countries->isNotEmpty()) $countryId = $countries->first()->id;

        $productsSidebar = Product::where('country_id', $countryId)->select('id', 'name', 'country_id', 'harvest_month')->orderBy('name')->get();

        if ($productId && !Product::where('id', $productId)->exists()) $productId = null;
        if (!$productId && $countryId == $defaultCountryId && !empty($defaultProductIds)) {
            $decodedIds = is_array($defaultProductIds) ? $defaultProductIds : json_decode($defaultProductIds, true);
            $target = $productsSidebar->first(fn($p) => in_array($p->id, $decodedIds));
            if ($target) $productId = $target->id;
        }
        if (!$productId && $productsSidebar->isNotEmpty()) $productId = $productsSidebar->first()->id;

        $suppliers = Supplier::whereHas('productPrices.product', fn($q) => $countryId ? $q->where('country_id', $countryId) : $q)
            ->select('id', 'name')->orderBy('name')->get();

        if ($currentPage->component === 'Dashboard/PriceTable') {
            $productsQuery = Product::query()->where('country_id', $countryId);
            
            // For PriceTable date filtering
            $pStart = $startOfWeek; $pEnd = $endOfWeek;
            $useManualRange = false;
            if ($dateRange && $dateRange !== 'Todos') {
                $parts = explode('-', $dateRange);
                if (count($parts) === 2) {
                    $pStart = Carbon::now()->setISODate($parts[0], $parts[1])->startOfWeek(Carbon::MONDAY);
                    $pEnd = $pStart->copy()->endOfWeek(Carbon::SUNDAY);
                    $useManualRange = true;
                } elseif (count($parts) === 1) {
                    $pStart = Carbon::create($parts[0], 1, 1)->startOfYear();
                    $pEnd = $pStart->copy()->endOfYear();
                    $useManualRange = true;
                }
            }

            $productsQuery->whereHas('prices', function($q) use ($supplierId, $pStart, $pEnd, $useManualRange) {
                if ($useManualRange) $q->whereBetween('date', [$pStart->format('Y-m-d'), $pEnd->format('Y-m-d')]);
                else $q->whereBetween('date', [$pStart->format('Y-m-d'), $pEnd->format('Y-m-d')]);
                if ($supplierId) $q->where('supplier_id', $supplierId);
            });
            $productsQuery->with(['prices' => function($q) use ($supplierId, $pStart, $pEnd, $useManualRange) {
                if ($supplierId) $q->where('supplier_id', $supplierId);
                if ($useManualRange) $q->whereBetween('date', [$pStart->format('Y-m-d'), $pEnd->format('Y-m-d')]);
                $q->with('supplier:id,name')->orderBy('date', 'desc')->orderBy('price', 'asc');
            }]);
            
            $sortField = $viewData['filters']['sort_field']; 
            $sortDir = $viewData['filters']['sort_direction'] ?? 'asc';
            
            if ($sortField) {
                if ($sortField === 'latest_price' || $sortField === 'variation') {
                    $lpSub = ProductPrice::select('product_id', 'price as l_price')
                        ->whereIn('id', function($q) use ($supplierId, $pStart, $pEnd) {
                            $q->selectRaw('MAX(id)') 
                              ->from('product_prices')
                              ->whereBetween('date', [$pStart->format('Y-m-d'), $pEnd->format('Y-m-d')])
                              ->when($supplierId, fn($qx) => $qx->where('supplier_id', $supplierId))
                              ->groupBy('product_id');
                        });
                    $productsQuery->leftJoinSub($lpSub, 'lp', 'lp.product_id', '=', 'products.id')
                        ->orderBy('l_price', $sortDir);
                } else { 
                    $productsQuery->orderBy('products.name', $sortDir); 
                }
            } else {
                // Default original order: Name ASC
                $productsQuery->orderBy('products.name', 'asc');
            }
            
            $productsProp = $productsQuery->select('products.*')->paginate(20)->withQueryString();
        } else {
            $productsProp = $productsSidebar;
        }

        if ($currentPage->component === 'Dashboard/HistoricalData') {
            $hQuery = ProductPrice::with(['product:id,name,country_id', 'product.country:id,name', 'supplier:id,name'])
                ->join('products', 'products.id', '=', 'product_prices.product_id')
                ->leftJoin('suppliers', 'suppliers.id', '=', 'product_prices.supplier_id')
                ->select(\DB::raw('product_prices.*, products.name as product_name, suppliers.name as supplier_name'));
            
            if ($countryId) $hQuery->where('products.country_id', $countryId);
            if ($productId) $hQuery->where('product_id', $productId);
            if ($supplierId) $hQuery->where('supplier_id', $supplierId);
            if ($dateRange && $dateRange !== 'Todos') {
                $parts = explode('-', $dateRange);
                if (count($parts) === 2) $hQuery->whereRaw('YEAR(product_prices.date) = ? AND WEEK(product_prices.date, 1) = ?', [$parts[0], $parts[1]]);
                else if (count($parts) === 1) $hQuery->whereRaw('YEAR(product_prices.date) = ?', [$parts[0]]);
            }
            
            $sF = $viewData['filters']['sort_field'];
            $sD = $viewData['filters']['sort_direction'] ?? 'desc';
            
            if ($sF) {
                $mF = [
                    'name' => 'products.name',
                    'country' => 'countries.name',
                    'supplier' => 'suppliers.name',
                    'date' => 'product_prices.date',
                    'price' => 'product_prices.price'
                ];
                $hSort = $mF[$sF] ?? 'product_prices.date';
                
                $viewData['historicalData'] = $hQuery->leftJoin('countries', 'countries.id', '=', 'products.country_id')
                    ->orderBy($hSort, $sD)
                    ->orderBy('product_prices.id', 'desc')
                    ->paginate(50)->withQueryString();
            } else {
                // Default original order: Date DESC, then Supplier ASC
                $viewData['historicalData'] = $hQuery->orderBy('product_prices.date', 'desc')
                    ->orderBy('suppliers.name', 'asc')
                    ->paginate(50)->withQueryString();
            }
        }

        $viewData['availableDates'] = ProductPrice::when($countryId, fn($q) => $q->whereHas('product', fn($p) => $p->where('country_id', $countryId)))
            ->selectRaw('YEAR(date) as year, WEEK(date, 1) as week')->distinct()->orderBy('year', 'desc')->orderBy('week', 'desc')
            ->get()->groupBy('year')->map(fn($ws, $y) => ['year' => $y, 'weeks' => $ws])->values();

        if ($currentPage->component === 'Dashboard/Show' || $currentPage->component === 'Dashboard/Demo') {
            if ($productId) {
                $pricesQuery = ProductPrice::where('product_id', $productId)->with('supplier:id,name');
                if ($dateRange && $dateRange !== 'Todos') {
                    $parts = explode('-', $dateRange);
                    if (count($parts) === 2) $pricesQuery->whereRaw('YEAR(date) = ? AND WEEK(date, 1) = ?', [$parts[0], $parts[1]]);
                    elseif (count($parts) === 1) $pricesQuery->whereRaw('YEAR(date) = ?', [$parts[0]]);
                }
                $prices = $pricesQuery->orderBy('date', 'desc')->orderBy('price', 'asc')->get();
                $viewData['pricesData'] = $prices;
                
                if ($dateRange === 'Todos') {
                    $allPrices = ProductPrice::where('product_id', $productId)->orderBy('date', 'desc')->get();
                    $viewData['metrics'] = $this->calculateMetrics($allPrices);
                    $viewData['chartData'] = $this->calculateChartHistorical($allPrices);
                } else {
                    $viewData['metrics'] = $this->calculateMetrics($prices);
                    $allPrices = ProductPrice::where('product_id', $productId)->orderBy('date', 'desc')->get();
                    $viewData['chartData'] = $this->calculateChartHistorical($allPrices);
                }
            }
        }

        $viewData['countries'] = $countries;
        $viewData['suppliers'] = $suppliers;
        $viewData['products'] = $productsProp;
        $viewData['filters']['country_id'] = $countryId;
        $viewData['filters']['product_id'] = $productId;
        $viewData['settings'] = \App\Models\Setting::all()->pluck('value', 'key');
    }

    private function calculateMetrics($prices) {
        if ($prices->isEmpty()) return [
            'latest' => ['label' => 'SEM DADOS', 'min' => 0, 'max' => 0, 'spread' => 0],
            'year' => ['label' => 'ANO', 'min' => 0, 'max' => 0, 'spread' => 0],
            'all' => ['label' => 'GERAL', 'min' => 0, 'max' => 0, 'spread' => 0]
        ];
        $now = Carbon::now();
        $latestDate = $prices->first()->date;
        $lat = $prices->filter(fn($p) => $p->date->isSameDay($latestDate));
        $startY = $latestDate->copy()->startOfYear();
        $yB = $prices->filter(fn($p) => $p->date->isAfter($startY) || $p->date->isSameDay($startY))->groupBy(fn($p) => $p->date->format('Y-W'))->map(fn($g) => (float)$g->min('price'));
        $aB = $prices->groupBy(fn($p) => $p->date->format('Y-W'))->map(fn($g) => (float)$g->min('price'));
        $m = [
            'latest' => ['label' => 'ÚLTIMA SEMANA', 'min' => (float)$lat->min('price'), 'max' => (float)$lat->max('price')],
            'year' => ['label' => 'ANO: ' . $latestDate->year, 'min' => (float)$yB->min(), 'max' => (float)$yB->max()],
            'all' => ['label' => 'DESDE: ' . ($prices->min('date')?->year ?? $now->year), 'min' => (float)$aB->min(), 'max' => (float)$aB->max()]
        ];
        foreach ($m as $k => $v) { $m[$k]['spread'] = ($v['min'] > 0) ? (($v['max'] - $v['min']) / $v['min']) * 100 : 0; }
        return $m;
    }

    private function calculateChartHistorical($prices) {
        $cD = []; $pYM = $prices->groupBy(fn($p) => $p->date->format('Y-n')); $fY = $prices->min('date')?->year ?? now()->year;
        foreach (range($fY, now()->year) as $y) {
            $mDt = []; for ($m=1;$m<=12;$m++) { $val = $pYM->get("$y-$m")?->min('price'); $mDt[] = $val ? (float)round($val, 2) : null; }
            $cD[$y] = $mDt;
        }
        return $cD;
    }

    public function sendContactEmail(HttpRequest $request) {
        $v = $request->validate(['subject' => 'required', 'message' => 'required']);
        $u = auth()->user(); $recipient = \App\Models\Setting::get('contact_email', config('mail.from.address'));
        \Illuminate\Support\Facades\Mail::to($recipient)->send(new \App\Mail\DirectContact(['name'=>$u->name, 'email'=>$u->email, 'phone'=>$u->phone, 'company'=>$u->company_name, 'subject'=>$v['subject'], 'message'=>$v['message']]));
        return redirect()->back()->with('success', 'Mensagem enviada!');
    }

    public function exportPricesPdf(HttpRequest $request) {
        ini_set('memory_limit', '512M'); set_time_limit(300);
        try {
            $cIds = $request->input('country_ids'); $q = Country::select('id', 'name')->orderBy('name');
            if ($cIds && $cIds !== 'all') $q->whereIn('id', is_array($cIds)?$cIds:explode(',',$cIds));
            $countries = $q->get(); if ($countries->isEmpty()) throw new \Exception('Nenhum país selecionado.');
            $exportData = []; $fC = [];
            $ctx = stream_context_create(['ssl'=>["verify_peer"=>false,"verify_peer_name"=>false],'http'=>['timeout'=>7]]);
            foreach ($countries as $country) {
                $fB64 = null; 
                $cM = [
                    'brasil'=>'br','brazil'=>'br','china'=>'cn','india'=>'in','indonesia'=>'id','vietna'=>'vn','vietnam'=>'vn','madagascar'=>'mg','egito'=>'eg','egypt'=>'eg','espanha'=>'es','spain'=>'es',
                    'argentina'=>'ar','mexico'=>'mx','paraguai'=>'py','paraguay'=>'py','uruguai'=>'uy','uruguay'=>'uy','canada'=>'ca','franca'=>'fr','france'=>'fr','alemanha'=>'de','germany'=>'de',
                    'italia'=>'it','italy'=>'it','japao'=>'jp','japan'=>'jp','turquia'=>'tr','turkey'=>'tr','russia'=>'ru','malaysia'=>'my','malasia'=>'my','sri lanka'=>'lk','sri-lanka'=>'lk',
                    'bulgaria'=>'bg','chile'=>'cl','guatemala'=>'gt','paquistao'=>'pk','pakistan'=>'pk','peru'=>'pe'
                ];
                $rawName = trim($country->name); if (str_contains($rawName, '/')) { $rawName = explode('/', $rawName)[0]; }
                $rN = str_replace(['á','ã','é','í','ó','õ','ú','ç'], ['a','a','e','i','o','o','u','c'], mb_strtolower(trim($rawName), 'UTF-8'));
                $code = $cM[$rN] ?? null;
                if ($code) {
                    if (isset($fC[$code])) $fB64 = $fC[$code];
                    else { try { $iD = @file_get_contents("https://flagcdn.com/w160/{$code}.png", false, $ctx); if($iD){$fB64='data:image/png;base64,'.base64_encode($iD);$fC[$code]=$fB64;} } catch(\Exception $e){} }
                }
                $prods = Product::where('country_id', $country->id)->with(['prices' => fn($q) => $q->orderBy('date', 'desc')->orderBy('price', 'asc')->with('supplier:id,name')])->orderBy('name')->get()->map(function($p) {
                    $l = $p->prices->first(); $pr = $p->prices->first(fn($x) => $x->date->format('Y-m-d') !== ($l?$l->date->format('Y-m-d'):null));
                    $lp = $l ? (float)$l->price : null; $pp = $pr ? (float)$pr->price : $lp;
                    $v = ($lp && $pp && $pp > 0) ? (($lp - $pp) / $pp) * 100 : 0;
                    return (object)[
                        'name' => $p->name, 'latestPrice' => $lp, 'previousPrice' => $pp, 'variation' => $v, 
                        'status' => $v > 0 ? 'up' : ($v < 0 ? 'down' : (!$pr && $l ? 'new' : 'none')),
                        'supplier' => $l ? ($l->supplier->name ?? 'N/A') : 'N/A'
                    ];
                });
                $exportData[] = (object)[ 'name' => $country->name, 'flag' => $fB64, 'products' => $prods ];
            }
            return \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.price-table', ['exportData' => $exportData, 'date' => now()->format('d/m/Y')])->download('tabela-de-preco-jrspice-'.now()->format('d-m-Y').'.pdf');
        } catch (\Exception $e) { 
            return response()->json(['is_error' => true, 'message' => $e->getMessage()], 200, ['Content-Type' => 'application/json']);
        }
    }
}
