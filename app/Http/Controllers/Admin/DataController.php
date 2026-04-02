<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Inertia\Inertia;
use App\Models\DashboardPage;
use App\Models\Country;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Supplier;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\DataImport;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class DataController extends Controller
{
    public function index(Request $request)
    {
        $products_search = $request->input('products_search');
        $countries_search = $request->input('countries_search');
        $suppliers_search = $request->input('suppliers_search');
        $prices_search = $request->input('prices_search');
        $pages_search = $request->input('pages_search');

        return Inertia::render('Admin/Data/Index', [
            'pages' => DashboardPage::orderBy('order')
                ->when($pages_search, function($q, $search) {
                    $q->where('title', 'like', "%{$search}%");
                })
                ->paginate(15, ['*'], 'pages_page')
                ->withQueryString(),
            
            'countries' => Country::withCount('products')
                ->when($countries_search, function($q, $search) {
                    $q->where('name', 'like', "%{$search}%");
                })
                ->orderBy('id', 'desc')
                ->paginate(15, ['*'], 'countries_page')
                ->withQueryString(),
            
            'products' => Product::with('country')
                ->when($products_search, function($q, $search) {
                    $q->where(function($qq) use ($search) {
                        $qq->where('name', 'like', "%{$search}%")
                          ->orWhere('harvest_month', 'like', "%{$search}%")
                          ->orWhereHas('country', function($sq) use ($search) {
                              $sq->where('name', 'like', "%{$search}%");
                          });
                    });
                })
                ->orderBy('id', 'desc')
                ->paginate(15, ['*'], 'products_page')
                ->withQueryString(),
            
            'suppliers' => Supplier::when($suppliers_search, function($q, $search) {
                    $q->where('name', 'like', "%{$search}%");
                })
                ->orderBy('id', 'desc')
                ->paginate(15, ['*'], 'suppliers_page')
                ->withQueryString(),
            
            'prices' => ProductPrice::with(['product.country', 'supplier'])
                ->when($prices_search, function($q, $search) {
                    $q->where(function($qq) use ($search) {
                        $qq->whereHas('product', function($sq) use ($search) {
                            $sq->where('name', 'like', "%{$search}%")
                               ->orWhereHas('country', function($ssq) use ($search) {
                                   $ssq->where('name', 'like', "%{$search}%");
                               });
                        })
                        ->orWhereHas('supplier', function($sq) use ($search) {
                            $sq->where('name', 'like', "%{$search}%");
                        })
                        ->orWhere('date', 'like', "%{$search}%");
                    });
                })
                ->orderBy('date', 'desc')
                ->orderBy('id', 'desc')
                ->paginate(15, ['*'], 'prices_page')
                ->withQueryString(),
                
            'settings' => \App\Models\Setting::all()->pluck('value', 'key'),
            'all_countries' => Country::orderBy('name')->get(),
            'all_products' => Product::with('country')->orderBy('name')->get(),
            'all_suppliers' => Supplier::orderBy('name')->get(),
            'filters' => $request->only(['products_search', 'countries_search', 'suppliers_search', 'prices_search', 'tab']),
            'default_filter_config' => [
                'country_id'  => \App\Models\Setting::get('default_filter_country_id'),
                'product_ids' => \App\Models\Setting::get('default_filter_product_ids') ?? [],
            ],
        ]);
    }


    public function storePage(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'component' => 'required|string',
            'order' => 'integer',
        ]);

        $slug = Str::slug($validated['title']);
        $validated['slug'] = $slug;

        DashboardPage::create($validated);
        return redirect()->back();
    }

    public function storeCountry(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:countries,name',
        ]);

        Country::create($validated);
        return redirect()->back();
    }

    public function storeProduct(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'country_id' => 'required|exists:countries,id',
            'harvest_month' => 'nullable|string',
        ]);

        Product::create($validated);
        return redirect()->back();
    }

    public function storePrice(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'date' => 'required|date',
            'price' => 'required|numeric',
        ]);

        ProductPrice::updateOrCreate(
            ['product_id' => $validated['product_id'], 'date' => $validated['date'], 'supplier_id' => $validated['supplier_id'] ?? null],
            [
                'price' => $validated['price']
            ]
        );

        return redirect()->back()->with('success', 'Preço registrado com sucesso.');
    }

    // UPDATE METHODS
    public function updatePage(Request $request, DashboardPage $page)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'component' => 'required|string',
            'order' => 'integer',
        ]);
        $validated['slug'] = Str::slug($validated['title']);
        $page->update($validated);
        return redirect()->back()->with('success', 'Página atualizada.');
    }

    public function updateCountry(Request $request, Country $country)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:countries,name,' . $country->id,
        ]);
        $country->update($validated);
        return redirect()->back()->with('success', 'País atualizado.');
    }

    public function updateProduct(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'country_id' => 'required|exists:countries,id',
            'harvest_month' => 'nullable|string',
        ]);
        $product->update($validated);
        return redirect()->back()->with('success', 'Produto atualizado.');
    }

    public function updatePrice(Request $request, ProductPrice $price)
    {
        $validated = $request->validate([
            'supplier_id' => 'nullable|exists:suppliers,id',
            'price' => 'required|numeric',
        ]);
        $price->update($validated);
        return redirect()->back()->with('success', 'Preço atualizado.');
    }

    public function storeSupplier(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:suppliers,name',
        ]);
        Supplier::create($validated);
        return redirect()->back()->with('success', 'Fornecedor criado.');
    }

    public function updateSupplier(Request $request, Supplier $supplier)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:suppliers,name,' . $supplier->id,
        ]);
        $supplier->update($validated);
        return redirect()->back()->with('success', 'Fornecedor atualizado.');
    }

    public function destroySupplier(Supplier $supplier)
    {
        $supplier->delete();
        return redirect()->back()->with('success', 'Fornecedor removido.');
    }

    // DESTROY METHODS
    public function destroyPage(DashboardPage $page)
    {
        $page->delete();
        return redirect()->back()->with('success', 'Página removida.');
    }

    public function destroyCountry(Country $country)
    {
        $country->delete();
        return redirect()->back()->with('success', 'País removido.');
    }

    public function destroyProduct(Product $product)
    {
        $product->delete();
        return redirect()->back()->with('success', 'Produto removido.');
    }

    public function updateSettings(Request $request)
    {
        $settings = $request->all();
        foreach ($settings as $key => $value) {
            \App\Models\SystemSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }
        return redirect()->back()->with('success', 'Configurações atualizadas com sucesso.');
    }

    public function destroyPrice(ProductPrice $price)
    {
        $price->delete();
        return redirect()->back()->with('success', 'Preço removido.');
    }

    /**
     * Handle the spreadsheet import.
     */
    public function importData(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv'
        ]);

        $jobId = Str::uuid()->toString();
        
        // Inicializa o progresso no cache
        Cache::put("import_progress_{$jobId}", [
            'current' => 0,
            'total' => 0,
            'status' => 'queued',
            'percentage' => 0
        ], 300);

        // Caminho do arquivo
        $path = $request->file('file')->store('imports');

        // Dispara o import (Background)
        \App\Jobs\ProcessDataImport::dispatch(Storage::disk('local')->path($path), $jobId);

        return response()->json([
            'jobId' => $jobId,
            'message' => __('Import started in background.')
        ]);
    }

    /**
     * Get the current status of an import job.
     */
    public function getImportStatus($jobId)
    {
        $status = Cache::get("import_progress_{$jobId}");

        if (!$status) {
            return response()->json([
                'status' => 'not_found'
            ], 404);
        }

        return response()->json($status);
    }

    /**
     * Save the "Default Filter" configuration: a country + list of products
     * that will be pre-selected when the user opens the dashboard.
     */
    public function saveDefaultFilters(Request $request)
    {
        $validated = $request->validate([
            'country_id'  => 'nullable|exists:countries,id',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'exists:products,id',
        ]);

        \App\Models\Setting::set('default_filter_country_id', $validated['country_id'] ?? null);
        \App\Models\Setting::set('default_filter_product_ids', $validated['product_ids'] ?? []);

        return redirect()->back()->with('success', 'Filtro padrão salvo com sucesso.');
    }
}
