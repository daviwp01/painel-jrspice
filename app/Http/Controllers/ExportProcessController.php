<?php

namespace App\Http\Controllers;

use App\Models\ExportProcess;
use App\Models\Client;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ExportProcessController extends Controller
{
    public function index(Request $request)
    {
        $query = ExportProcess::with(['exporter', 'importer', 'product', 'seller'])
            ->orderBy('date', 'desc');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where('contract_number', 'like', "%{$search}%")
                  ->orWhere('register_number', 'like', "%{$search}%")
                  ->orWhereHas('exporter', function($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  });
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $processes = $query->paginate(50)->withQueryString();

        return Inertia::render('ExportProcesses/Index', [
            'exportProcesses' => $processes,
            'clients' => Client::orderBy('name')->get(),
            'products' => Product::orderBy('name')->get(),
            'sellers' => User::orderBy('name')->get(),
            'filters' => $request->only(['search', 'status']),
            'summary' => [
                'total_tons' => ExportProcess::sum('quantity_tons'),
                'pending_commissions' => ExportProcess::whereNull('paid_in_date')->sum('commission_usd'),
                'delayed_logistics' => ExportProcess::whereNull('dhl_number')->whereNotNull('eta_date')->where('eta_date', '<', now()->addDays(7))->count(),
            ]
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateProcess($request);
        ExportProcess::create($validated);
        return redirect()->back()->with('success', 'Processo criado com sucesso.');
    }

    public function update(Request $request, ExportProcess $exportProcess)
    {
        $validated = $this->validateProcess($request);
        $exportProcess->update($validated);
        return redirect()->back()->with('success', 'Processo atualizado com sucesso.');
    }

    public function destroy(ExportProcess $exportProcess)
    {
        $exportProcess->delete();
        return redirect()->back()->with('success', 'Processo excluído.');
    }

    private function validateProcess(Request $request)
    {
        return $request->validate([
            'date' => 'nullable|date',
            'contract_number' => 'nullable|string|max:255',
            'register_number' => 'nullable|string|max:255',
            'exporter_id' => 'nullable|exists:clients,id',
            'importer_id' => 'nullable|exists:clients,id',
            'product_id' => 'nullable|exists:products,id',
            'quantity_tons' => 'nullable|numeric|min:0',
            'price_per_ton_usd' => 'nullable|numeric|min:0',
            'sales_usd' => 'nullable|numeric|min:0',
            'annual_sales_usd' => 'nullable|numeric|min:0',
            'commission_usd' => 'nullable|numeric|min:0',
            'total_commission_usd' => 'nullable|numeric|min:0',
            'exchange_rate' => 'nullable|numeric|min:0',
            'estimated_euro' => 'nullable|numeric|min:0',
            'estimated_receipt_date' => 'nullable|date',
            'seller_id' => 'nullable|exists:users,id',
            'to_pay_usd' => 'nullable|numeric|min:0',
            'receipt_date' => 'nullable|date',
            'paid_in_date' => 'nullable|date',
            'paid_in_brl' => 'nullable|numeric|min:0',
            'incident' => 'nullable|string',
            'video_sent' => 'boolean',
            'video_date' => 'nullable|date',
            'status' => 'nullable|string|max:255',
            'status_date' => 'nullable|date',
            'dhl_date' => 'nullable|date',
            'dhl_number' => 'nullable|string|max:255',
            'etd_date' => 'nullable|date',
            'eta_date' => 'nullable|date',
            'observations' => 'nullable|string',
        ]);
    }
}
