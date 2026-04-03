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
use App\Imports\DataImport;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Bus;
use App\Services\BackupService;
use Illuminate\Bus\Batch;

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
            'active_import_batch' => $this->getActiveBatchStatus(),
            'backups' => BackupService::list(),
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

    public function importData(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls']);
        
        $file = $request->file('file');
        
        if (!$file->isValid()) {
            return response()->json(['status' => 'error', 'message' => 'Upload do arquivo falhou.'], 422);
        }

        $filePath = $file->getPathname();
        if (!file_exists($filePath) || filesize($filePath) === 0) {
            return response()->json(['status' => 'error', 'message' => 'O arquivo enviado parece estar vazio ou não foi processado corretamente.'], 422);
        }

        // 1. VALIDAÇÃO RIGOROSA DE CABEÇALHOS (Fidelidade Backend)
        try {
            // Usa o motor do Laravel Excel que é mais robusto para arquivos temporários
            $data = \Maatwebsite\Excel\Facades\Excel::toArray([], $file);
            $rows = $data[0] ?? [];
            $headers = $rows[0] ?? [];

            if (empty($headers)) {
                return response()->json(['status' => 'error', 'message' => 'Planilha vazia ou ilegível.'], 422);
            }
            
            $normalize = function($str) {
                // Se o cabeçalho for nulo ou não string, vira vazio
                if (!is_string($str)) return '';
                
                $str = trim(mb_strtolower($str));
                $str = preg_replace('/[áàâãä]/u', 'a', $str);
                $str = preg_replace('/[éèêë]/u', 'e', $str);
                $str = preg_replace('/[íìîï]/u', 'i', $str);
                $str = preg_replace('/[óòôõö]/u', 'o', $str);
                $str = preg_replace('/[úùûü]/u', 'u', $str);
                $str = preg_replace('/[ç]/u', 'c', $str);
                
                // Normaliza barras e espaços: "ano/mes" ou "ano  /  mes" -> "ano / mes"
                $str = preg_replace('/\s*\/\s*/', ' / ', $str);
                return $str;
            };

            $headers = array_map($normalize, array_values($headers));
            
            // LISTA OFICIAL JRSPICE (Sem acentos para comparação)
            $requiredMap = [
                'produto' => 'PRODUTO',
                'safra' => 'SAFRA',
                'pais' => 'PAÍS',
                'fornecedor' => 'FORNECEDOR',
                'data registro' => 'DATA REGISTRO',
                'ano / mes' => 'ANO / MES',
                'semana' => 'SEMANA',
                'preco' => 'PREÇO'
            ];

            $missing = [];
            foreach($requiredMap as $key => $label) {
                // Checagem especial para Preço/Valor e Mes/Mês
                $found = false;
                if ($key === 'preco') {
                    if (in_array('preco', $headers) || in_array('valor', $headers)) $found = true;
                } elseif ($key === 'ano / mes') {
                    if (in_array('ano / mes', $headers) || in_array('mes', $headers)) $found = true;
                } else {
                    if (in_array($key, $headers)) $found = true;
                }

                if (!$found) $missing[] = $label;
            }

            if (!empty($missing)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Planilha Inválida. Faltam as colunas obrigatórias: ' . implode(', ', $missing) . '. Certifique-se de que os cabeçalhos estão na PRIMEIRA LINHA.'
                ], 422);
            }

        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => 'Erro ao ler cabeçalhos da planilha: ' . $e->getMessage()], 422);
        }

        // 2. GERAR BACKUP DE SEGURANÇA (Snapshot Pré-Importação Ativo)
        BackupService::generate('importacao');

        $jobId = Str::uuid()->toString();
        $path = $file->store('imports');
        $fullPath = Storage::disk('local')->path($path);

        $batch = Bus::batch([
            new \App\Jobs\ProcessDataImport($fullPath, $jobId)
        ])->name("Importação de Dados - {$jobId}")->dispatch();

        // Persiste esse ID para que possamos recuperar no refresh da página
        \App\Models\Setting::set('active_import_batch_id', $batch->id);
        \App\Models\Setting::set('active_import_job_id', $jobId);

        return response()->json([
            'jobId' => $jobId,
            'batchId' => $batch->id,
            'message' => __('Import started.')
        ]);
    }

    public function getImportStatus($jobId)
    {
        try {
            $batchId = request()->query('batchId') ?? \App\Models\Setting::get('active_import_batch_id');
            
            // 1. Tentar pegar o progresso detalhado do Cache primeiro (fino-grão)
            $cachedStatus = Cache::get("import_progress_{$jobId}");
            
            // 2. Tentar pegar o status do Batch (Lote)
            if ($batchId) {
                $batch = Bus::findBatch($batchId);
                if ($batch) {
                    $status = ($batch->progress() >= 100) ? 'completed' : 'processing';
                    if ($batch->cancelled()) $status = 'cancelled';
                    if ($batch->hasFailures()) $status = 'failed';

                    // Se temos um status de lote (cancelado/falhou), isso manda
                    if ($status === 'cancelled' || $status === 'failed') {
                        return response()->json([
                            'status' => $status,
                            'percentage' => $cachedStatus['percentage'] ?? $batch->progress(),
                            'total' => $cachedStatus['total'] ?? $batch->totalJobs,
                            'current' => $cachedStatus['current'] ?? $batch->processedJobs(),
                            'id' => $batch->id
                        ]);
                    }

                    // Se está processando, retorna o híbrido (progresso do cache + status do batch)
                    return response()->json([
                        'status' => $status,
                        'percentage' => $cachedStatus['percentage'] ?? $batch->progress(),
                        'total' => $cachedStatus['total'] ?? $batch->totalJobs,
                        'current' => $cachedStatus['current'] ?? $batch->processedJobs(),
                        'id' => $batch->id
                    ]);
                }
            }

            // Fallback total se não houver batch ou se batchId não devolver nada
            if ($cachedStatus) return response()->json($cachedStatus);

            return response()->json(['status' => 'not_found'], 404);

        } catch (\Throwable $e) {
            Log::error('Erro no Status da Importação: ' . $e->getMessage(), [
                'jobId' => $jobId,
                'batchId' => request()->query('batchId'),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Erro interno ao consultar status.',
                'debug' => $e->getMessage()
            ], 500);
        }
    }

    public function cancelImport(Request $request)
    {
        try {
            $batchId = $request->input('batchId') ?? \App\Models\Setting::get('active_import_batch_id');
            if ($batchId) {
                $batch = Bus::findBatch($batchId);
                if ($batch) {
                    $batch->cancel();
                    // Opcional: Limpar cache de progresso para "zerar" se necessário
                    return response()->json(['message' => 'Importação cancelada com sucesso.']);
                }
            }
            return response()->json(['message' => 'Nenhuma importação ativa encontrada para cancelamento.'], 404);
        } catch (\Throwable $e) {
            Log::error('Erro ao cancelar importação: ' . $e->getMessage());
            return response()->json(['message' => 'Erro ao processar cancelamento.'], 500);
        }
    }

    public function downloadBackup(Request $request)
    {
        $path = $request->query('path');
        if (!$path || !Storage::disk('local')->exists($path)) {
            abort(404, 'Backup não encontrado.');
        }

        return Storage::disk('local')->download($path);
    }

    public function createManualBackup()
    {
        BackupService::generate('manual');
        return redirect()->back()->with('success', 'Backup manual gerado com sucesso!');
    }

    /**
     * Gera e disponibiliza para download um modelo padrão de planilha Excel
     * com os cabeçalhos necessários para a importação.
     */
    public function downloadTemplate()
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Modelo de Importação');

        // Cabeçalhos Oficiais JRSPICE
        $headers = [
            'PRODUTO',
            'SAFRA',
            'PAÍS',
            'FORNECEDOR',
            'DATA REGISTRO',
            'ANO / MES',
            'SEMANA',
            'PREÇO'
        ];

        // Populando a primeira linha
        reset($headers);
        foreach ($headers as $index => $label) {
            $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1);
            $sheet->setCellValue($column . '1', $label);
            
            // Estilização Premium: Negrito e Auto-Width
            $sheet->getStyle($column . '1')->getFont()->setBold(true);
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        // Criando uma linha de exemplo invisível/fictícia (opcional)
        // $sheet->setCellValue('A2', 'Exemplo de Produto');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        
        $fileName = 'jrspice_modelo_importacao.xlsx';
        $tempPath = storage_path('app/public/' . $fileName);
        $writer->save($tempPath);

        return response()->download($tempPath)->deleteFileAfterSend(true);
    }

    public function restoreBackup(Request $request)
    {
        $path = $request->input('path');
        if (!$path || !Storage::disk('local')->exists($path)) {
            return response()->json(['message' => 'Backup não encontrado.'], 404);
        }

        // 1. Snapshot de segurança antes da restauração
        BackupService::generate('pre_restauracao');

        // 2. Preparação: O Job deleta o arquivo fonte no final, então precisamos de uma cópia
        $jobId = Str::uuid()->toString();
        $importDir = storage_path('app/private/imports');
        if (!file_exists($importDir)) mkdir($importDir, 0755, true);
        
        $tempImportPath = 'imports/restore_' . $jobId . '.xlsx';
        Storage::disk('local')->copy($path, $tempImportPath);
        
        $fullPath = Storage::disk('local')->path($tempImportPath);

        // 3. Disparar restauração (é essencialmente uma importação do backup)
        $batch = Bus::batch([
            new \App\Jobs\ProcessDataImport($fullPath, $jobId)
        ])->name("Restauração de Dados - {$jobId}")->dispatch();

        \App\Models\Setting::set('active_import_batch_id', $batch->id);
        \App\Models\Setting::set('active_import_job_id', $jobId);

        return response()->json([
            'jobId' => $jobId,
            'batchId' => $batch->id,
            'message' => 'Restauração iniciada com sucesso.'
        ]);
    }

    private function getActiveBatchStatus()
    {
        try {
            $batchId = \App\Models\Setting::get('active_import_batch_id');
            $jobId = \App\Models\Setting::get('active_import_job_id');

            if ($batchId) {
                $batch = Bus::findBatch($batchId);
                if ($batch && !$batch->finished() && !$batch->cancelled()) {
                    return [
                        'id' => $batch->id,
                        'jobId' => $jobId,
                        'progress' => $batch->progress(),
                        'status' => 'processing'
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Erro ao recuperar status de lote ativo: ' . $e->getMessage());
        }
        return null;
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
