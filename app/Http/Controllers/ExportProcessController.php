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
        $query = ExportProcess::with(['exporter', 'importer', 'product', 'seller', 'users'])
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

        $users = User::orderBy('name')->get();
        $clients = Client::orderBy('name')->get();

        $usersListData = $users->map(function ($u) {
            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email ?: '',
                'phone' => $u->phone ?: '',
                'company_name' => $u->company_name ?: '',
                'country' => '',
                'type' => 'Usuário',
            ];
        })->values()->all();

        return Inertia::render('Admin/Clients/Index', [
            'exportProcesses' => $processes,
            'clients' => $clients,
            'users' => $usersListData,
            'usersList' => $usersListData,
            'users_list' => $usersListData,
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
        $process = ExportProcess::create($validated);
        $this->syncContractUsers($process, $request->input('client_ids', []));
        return redirect()->back()->with('success', 'Processo criado com sucesso.');
    }

    public function update(Request $request, ExportProcess $exportProcess)
    {
        $validated = $this->validateProcess($request);
        $exportProcess->update($validated);
        $this->syncContractUsers($exportProcess, $request->input('client_ids', []));
        return redirect()->back()->with('success', 'Processo atualizado com sucesso.');
    }

    public function destroy(ExportProcess $exportProcess)
    {
        $exportProcess->delete();
        return redirect()->back()->with('success', 'Processo excluído.');
    }

    public function bulkDestroy(Request $request)
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'exists:export_processes,id']);
        ExportProcess::whereIn('id', $request->ids)->delete();
        return redirect()->back()->with('success', count($request->ids) . ' processos excluídos com sucesso.');
    }

    public function bulkSyncClients(Request $request)
    {
        $request->validate([
            'process_ids' => 'required|array',
            'process_ids.*' => 'exists:export_processes,id',
            'client_ids' => 'nullable|array',
        ]);

        $selected = $request->input('client_ids', []);
        $processes = ExportProcess::whereIn('id', $request->process_ids)->get();

        foreach ($processes as $process) {
            $this->syncContractUsers($process, $selected, true);
        }

        return redirect()->back()->with('success', 'Usuários vinculados com sucesso aos contratos selecionados.');
    }

    private function syncContractUsers(ExportProcess $process, array $selected, bool $withoutDetaching = false)
    {
        $userIds = array_map('intval', array_filter($selected, 'is_numeric'));

        if ($withoutDetaching) {
            $process->users()->syncWithoutDetaching($userIds);
        } else {
            $process->users()->sync($userIds);
        }
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
            'shipping_company' => 'nullable|string|max:255',
            'container_number' => 'nullable|string|max:255',
            'observations' => 'nullable|string',
            'client_ids' => 'nullable|array',
            'client_ids.*' => 'exists:users,id',
        ]);
    }
}
