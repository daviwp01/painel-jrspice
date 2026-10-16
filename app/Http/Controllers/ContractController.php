<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContractRequest;
use App\Http\Requests\UpdateContractRequest;
use App\Http\Requests\UpdateContractStatusRequest;
use App\Models\Client;
use App\Models\Contract;
use App\Models\ContractProduct;
use App\Models\User;
use App\Services\Contracts\ContractQueryService;
use App\Services\Contracts\ContractSaver;
use App\Services\Contracts\ContractSummaryService;
use App\Support\ContractStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ContractController extends Controller
{
    public function __construct(
        private readonly ContractQueryService $query,
        private readonly ContractSummaryService $summary,
        private readonly ContractSaver $saver,
    ) {}

    public function index(Request $request)
    {
        $activeFilters = $this->query->activeFilters($request);

        return Inertia::render('Admin/Clients/Index', [
            'rows' => $this->query->paginate($request, perPage: 50),
            'clients' => Client::query()->orderBy('name')->get(['id', 'name', 'type', 'country']),
            'sellers' => User::query()->orderBy('name')->get(['id', 'name']),
            'contractProducts' => ContractProduct::query()->orderBy('name')->get(['id', 'name']),
            'countries' => $this->countries(),
            'users' => $this->linkableUsers(),
            'statusOptions' => ContractStatus::options(),
            'filters' => array_merge($request->only(ContractQueryService::FILTER_KEYS), [
                'active_count' => count($activeFilters),
                'sort_by' => $this->query->sortField($request),
                'sort_dir' => $this->query->sortDirection($request),
            ]),
            'activeFilters' => $activeFilters,
            'summary' => $this->summary->overview(),
        ]);
    }

    public function show(Contract $contract)
    {
        $contract->load(['items', 'users:id']);

        return response()->json([
            'id' => $contract->id,
            'date' => $contract->date?->toDateString(),
            'contract_number' => $contract->contract_number,
            'exporter_id' => $contract->exporter_id,
            'importer_id' => $contract->importer_id,
            'country' => $contract->country,
            'seller_id' => $contract->seller_id,
            'status' => $contract->status,
            'status_date' => $contract->status_date?->toDateString(),
            'dhl_date' => $contract->dhl_date?->toDateString(),
            'dhl_number' => $contract->dhl_number,
            'etd_date' => $contract->etd_date?->toDateString(),
            'eta_date' => $contract->eta_date?->toDateString(),
            'video_sent' => $contract->video_sent,
            'video_reference' => $contract->video_reference,
            'video_date' => $contract->video_date?->toDateString(),
            'incident' => $contract->incident,
            'estimated_receipt_date' => $contract->estimated_receipt_date?->toDateString(),
            'exchange_rate' => $contract->exchange_rate,
            'receipt_date' => $contract->receipt_date?->toDateString(),
            'paid_in_date' => $contract->paid_in_date?->toDateString(),
            'paid_in_brl' => $contract->paid_in_brl,
            'observations' => $contract->observations,
            'shipping_company' => $contract->shipping_company,
            'container_number' => $contract->container_number,
            'user_ids' => $contract->users->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'items' => $contract->items->map(fn ($item) => [
                'id' => $item->id,
                'register_number' => $item->register_number,
                'product_id' => $item->product_id,
                'quantity_tons' => $item->quantity_tons,
                'price_per_ton_usd' => $item->price_per_ton_usd,
                'sales_usd' => $item->sales_usd,
                'commission_rate' => $item->commission_rate,
                'commission_usd' => $item->commission_usd,
                'to_pay_rate' => $item->to_pay_rate,
                'to_pay_usd' => $item->to_pay_usd,
                'etd_date' => $item->etd_date?->toDateString(),
                'eta_date' => $item->eta_date?->toDateString(),
                'dhl_number' => $item->dhl_number,
                'status_date' => $item->status_date?->toDateString(),
                'video_date' => $item->video_date?->toDateString(),
                'video_reference' => $item->video_reference,
                'observations' => $item->observations,
            ])->values(),
        ]);
    }

    public function store(StoreContractRequest $request)
    {
        $contract = $this->saver->create($request->validated(), $request->validated('user_ids', []));

        return back()->with('success', "Contrato {$contract->contract_number} criado com sucesso.");
    }

    public function update(UpdateContractRequest $request, Contract $contract)
    {
        $this->saver->update($contract, $request->validated(), $request->validated('user_ids'));

        return back()->with('success', 'Contrato atualizado com sucesso.');
    }

    public function updateStatus(UpdateContractStatusRequest $request, Contract $contract)
    {
        $this->saver->updateStatus($contract, $request->validated());

        return back()->with('success', 'Status atualizado.');
    }

    public function destroy(Contract $contract)
    {
        $number = $contract->contract_number;
        $contract->delete();

        return back()->with('success', "Contrato {$number} excluído.");
    }

    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:contracts,id'],
        ]);

        $deleted = Contract::query()->whereKey($request->input('ids'))->delete();

        return back()->with('success', "{$deleted} contratos excluídos com sucesso.");
    }

    public function bulkSyncUsers(Request $request)
    {
        $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:contracts,id'],
            'user_ids' => ['array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $userIds = array_values(array_unique(array_map('intval', $request->input('user_ids', []))));

        Contract::query()->whereKey($request->input('ids'))->get()
            ->each(fn (Contract $contract) => $contract->users()->syncWithoutDetaching($userIds));

        return back()->with('success', 'Usuários vinculados aos contratos selecionados.');
    }

    /**
     * Distinct countries present on contracts, for the filter select.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function countries()
    {
        return DB::table('contracts')
            ->whereNotNull('country')
            ->where('country', '!=', '')
            ->distinct()
            ->orderBy('country')
            ->pluck('country')
            ->map(fn (string $country) => (object) ['id' => $country, 'name' => $country])
            ->values();
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    /**
     * Every account can be linked to a contract. Masters are included on
     * purpose: the client portal filters contracts by the pivot, so a master
     * only sees contracts he was explicitly linked to.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function linkableUsers()
    {
        return User::query()
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone', 'company_name', 'client_id', 'is_master'])
            ->map(static fn (User $user) => (object) [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email ?: '',
                'phone' => $user->phone ?: '',
                'company_name' => $user->company_name ?: '',
                'type' => $user->is_master ? 'Master' : 'Usuário',
            ]);
    }
}
