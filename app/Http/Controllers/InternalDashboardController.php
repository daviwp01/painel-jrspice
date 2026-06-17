<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use App\Services\PriceDataService;
use App\Services\ExportService;
use App\Services\ContactService;
use App\Services\UserActivityService;
use App\Models\DashboardPage;
use Carbon\Carbon;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class InternalDashboardController extends Controller
{
    private const TECHNICAL_DASHBOARDS = [
        'Dashboard/Show',
        'Dashboard/PriceTable',
        'Dashboard/HistoricalData',
        'Dashboard/Demo',
    ];

    public function __construct(
        protected DashboardService $dashboardService,
        protected PriceDataService $priceDataService,
        protected ExportService $exportService,
        protected ContactService $contactService,
        protected UserActivityService $activityService
    ) {}

    public function index()
    {
        $user = auth()->user();

        $pages = Cache::remember('dashboard_pages_active', 300, function () {
            return DashboardPage::where('is_active', true)
                ->orderBy('order')
                ->get();
        });

        $allowedPages = $pages->filter(fn($page) => $user->canAccessPage($page->slug));

        if ($allowedPages->isEmpty()) {
            return Inertia::render('Dashboard/Empty');
        }

        return redirect()->route('dashboard.page', [
            'slug' => $allowedPages->first()->slug
        ]);
    }

    public function show(HttpRequest $request, string $slug)
    {
        $user = auth()->user();

        $currentPage = Cache::remember("dashboard_page_slug_{$slug}", 300, function () use ($slug) {
            return DashboardPage::where('slug', $slug)->firstOrFail();
        });

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
                'sort_direction' => $request->query('sort_direction'),
            ],
            'settings' => $this->dashboardService->getSettings(),
        ];

        if (in_array($currentPage->component, self::TECHNICAL_DASHBOARDS, true)) {
            $this->loadDashboardData($request, $currentPage, $viewData);
        }

        // Always log page view explicitly (throttled internally by UserActivityService)
        $this->activityService->logSearch(
            $user,
            'page',
            $currentPage->title,
            $currentPage->component
        );

        // Sempre registrar os filtros que o usuário está visualizando (incluindo defaults)
        if (in_array($currentPage->component, self::TECHNICAL_DASHBOARDS, true)) {
            $this->logFilters($viewData['filters'], $currentPage->component);
        }

        return Inertia::render($currentPage->component, $viewData);
    }

    private function loadDashboardData(HttpRequest $request, $currentPage, array &$viewData): void
    {
        $now = now();
        $currentWeekStart = $now->copy()->startOfWeek(Carbon::MONDAY);
        $currentWeekEnd = $now->copy()->endOfWeek(Carbon::SUNDAY);

        $settings = $viewData['settings'];
        $defaultCountryId = $settings['default_filter_country_id'] ?? null;
        $defaultProductIds = is_array($settings['default_filter_product_ids'] ?? [])
            ? ($settings['default_filter_product_ids'] ?? [])
            : (json_decode($settings['default_filter_product_ids'] ?? '[]', true) ?: []);

        $countries = $this->dashboardService->getCountriesWithLatestUpdate($currentWeekStart, $currentWeekEnd);
        $isFirstVisit = !$request->hasAny(['country_id', 'product_id', 'supplier_id', 'date_range']);

        $countryId = $viewData['filters']['country_id'];
        $productId = $viewData['filters']['product_id'];
        $supplierId = $viewData['filters']['supplier_id'];
        $dateRange = $viewData['filters']['date_range'];

        if ($isFirstVisit && $defaultCountryId) {
            $countryId = $defaultCountryId;
        }

        if ($currentPage->component === 'Dashboard/Demo') {
            $countryId = $defaultCountryId;
            $productId = $defaultProductIds[0] ?? null;
        }

        if (!$countryId && $countries->isNotEmpty()) {
            $countryId = $countries->first()->id;
        }

        $productsSidebar = $this->dashboardService->getProductsSidebar($countryId);

        if ($productId && !$productsSidebar->contains('id', (int) $productId)) {
            $productId = null;
        }

        if (!$productId && (string) $countryId === (string) $defaultCountryId && !empty($defaultProductIds)) {
            $target = $productsSidebar->first(fn($p) => in_array($p->id, $defaultProductIds));
            if ($target) {
                $productId = $target->id;
            }
        }

        if (!$productId && $productsSidebar->isNotEmpty()) {
            $productId = $productsSidebar->first()->id;
        }

        [$rangeStart, $rangeEnd] = $this->dashboardService->resolveDateRange($dateRange, $currentWeekStart, $currentWeekEnd);

        $suppliers = $this->priceDataService->getSuppliers($countryId);

        if ($currentPage->component === 'Dashboard/PriceTable') {
            $productsProp = $this->priceDataService->getPriceTableProducts(
                $countryId,
                $supplierId,
                $rangeStart,
                $rangeEnd,
                $viewData['filters']['sort_field'],
                $viewData['filters']['sort_direction'] ?? 'asc'
            );
        } else {
            $productsProp = $productsSidebar;
        }

        if ($currentPage->component === 'Dashboard/HistoricalData') {
            // Se houver filtro de data range, passamos para filtrar o histórico
            $range = null;
            if ($dateRange && $dateRange !== 'Todos') {
                $range = ['start' => $rangeStart->toDateString(), 'end' => $rangeEnd->toDateString()];
            }

            $viewData['historicalData'] = $this->priceDataService->getHistoricalData(
                $countryId,
                $productId,
                $supplierId,
                $dateRange,
                $viewData['filters']['sort_field'],
                $viewData['filters']['sort_direction'] ?? 'desc',
                $range
            );
        }

        $viewData['availableDates'] = $this->dashboardService->getAvailableDates($countryId);

        if (in_array($currentPage->component, ['Dashboard/Show', 'Dashboard/Demo'], true) && $productId) {
            $range = null;
            if ($dateRange && $dateRange !== 'Todos') {
                $range = ['start' => $rangeStart->toDateString(), 'end' => $rangeEnd->toDateString()];
            }

            $viewData['metrics'] = $this->priceDataService->calculateMetrics($productId, $range);
            $viewData['chartData'] = $this->priceDataService->calculateChartHistorical($productId, $range);
            $viewData['chartWeeklyData'] = $this->priceDataService->calculateChartWeekly($productId, $range);
            $viewData['pricesData'] = $this->priceDataService->getContinuousData(
                $countryId, $productId, $supplierId, $range
            );
        }

        $viewData['countries'] = $countries;
        $viewData['suppliers'] = $suppliers;
        $viewData['products'] = $productsProp;
        $viewData['filters']['country_id'] = $countryId;
        $viewData['filters']['product_id'] = $productId;
    }

    /**
     * Log filter selections as search behaviour events.
     * Uses the resolved model names via DB for readable values.
     */
    private function logFilters(array $filters, string $pageContext): void
    {
        $user = auth()->user();
        if (!$user) return;

        $filterMap = [
            'country_id'  => ['type' => 'country',  'model' => \App\Models\Country::class,  'field' => 'name'],
            'supplier_id' => ['type' => 'supplier', 'model' => \App\Models\Supplier::class, 'field' => 'name'],
        ];

        // Apenas páginas de "Detalhes por Produto" usam e exibem o filtro de produto.
        // A Tabela de Preços mostra todos os produtos do país, então não deve registrar o produto como "pesquisado".
        if (in_array($pageContext, ['Dashboard/Show', 'Dashboard/Demo'], true)) {
            $filterMap['product_id'] = ['type' => 'product',  'model' => \App\Models\Product::class,  'field' => 'name'];
        }

        foreach ($filterMap as $param => $config) {
            $id = $filters[$param] ?? null;
            if (!$id) continue;

            $record = $config['model']::find($id);
            if (!$record) continue;

            $this->activityService->logSearch(
                $user,
                $config['type'],
                $record->{$config['field']},
                $pageContext
            );
        }

        // Log date range as-is (it's already a human-readable string)
        $dateRange = $filters['date_range'] ?? null;
        if ($dateRange && $dateRange !== 'Todos') {
            $this->activityService->logSearch($user, 'date_range', $dateRange, $pageContext);
        }
    }

    public function sendContactEmail(HttpRequest $request)
    {
        $validated = $request->validate([
            'subject' => 'required',
            'message' => 'required',
        ]);

        $user = auth()->user();
        $settings = $this->dashboardService->getSettings();
        $recipient = $settings['contact_email'] ?? config('mail.from.address');

        $this->contactService->sendContactEmail([
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'company' => $user->company_name,
            'subject' => $validated['subject'],
            'message' => $validated['message'],
        ], $recipient);

        return redirect()->back()->with('success', 'Mensagem enviada!');
    }

    public function exportPricesPdf(HttpRequest $request)
    {
        try {
            $countryIds = $request->input('country_ids');
            
            if ($countryIds && $countryIds !== 'all') {
                $countryIds = is_array($countryIds) ? $countryIds : explode(',', $countryIds);
            } else {
                $countryIds = null;
            }

            $pdf = $this->exportService->exportPricesToPdf($countryIds);

            // Log de engajamento (Exportação de Dados)
            if (auth()->check()) {
                $exportLabel = 'Todos os Países';
                if ($countryIds) {
                    $names = \App\Models\Country::whereIn('id', $countryIds)->pluck('name')->implode(', ');
                    if ($names) $exportLabel = $names;
                }

                $this->activityService->logSearch(
                    auth()->user(),
                    'export',
                    'PDF: ' . $exportLabel,
                    'Tabela de Preços'
                );
            }

            return $pdf->download('tabela-de-precos-jrspice-' . now()->format('d-m-Y') . '.pdf');
        } catch (\Exception $e) {
            return response()->json([
                'is_error' => true,
                'message' => $e->getMessage()
            ], 200, ['Content-Type' => 'application/json']);
        }
    }
}