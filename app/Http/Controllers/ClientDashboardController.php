<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesContractAccess;
use App\Models\Contract;
use App\Models\ContractDocument;
use App\Models\ContractProduct;
use App\Models\ContractReview;
use App\Models\User;
use App\Services\Contracts\ClientContractService;
use App\Services\Contracts\ContractDocumentService;
use App\Support\ContractStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ClientDashboardController extends Controller
{
    use AuthorizesContractAccess;

    public function __construct(private readonly ClientContractService $contracts) {}

    public function myProducts(Request $request)
    {
        $user = auth()->user();
        $activeFilters = $this->contracts->activeFilters($request);

        return Inertia::render('MyProducts/Index', [
            'exportProcesses' => $this->contracts->paginateForUser($user, $request),
            'filters' => array_merge($request->only(ClientContractService::FILTER_KEYS), [
                'active_count' => count($activeFilters),
            ]),
            'activeFilters' => $activeFilters,
            'summary' => $this->contracts->summaryForUser($user),
            'statusOptions' => ContractStatus::options(),
            'contractProducts' => $this->contractProducts($user),
            'countries' => $this->countries($user),
        ]);
    }

    /**
     * Products and countries actually used by this client's contracts.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function contractProducts(User $user)
    {
        return ContractProduct::query()
            ->whereHas('items.contract', fn ($q) => $q->whereHas('users', fn ($u) => $u->where('users.id', $user->id)))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function countries(User $user)
    {
        return Contract::query()
            ->whereHas('users', fn ($q) => $q->where('users.id', $user->id))
            ->whereNotNull('country')
            ->where('country', '!=', '')
            ->distinct()
            ->orderBy('country')
            ->pluck('country')
            ->map(fn (string $country) => (object) ['id' => $country, 'name' => $country])
            ->values();
    }

    public function showContract(Contract $contract)
    {
        $this->authorizeContractAccess($contract);

        $contract->load([
            'exporter:id,name,country',
            'importer:id,name,country',
            'seller:id,name',
            'items:id,contract_id,position,register_number,product_id,quantity_tons,price_per_ton_usd,sales_usd,commission_usd,estimated_euro,etd_date,eta_date,dhl_number',
            'items.product:id,name',
            'documents:id,contract_id,name,file_path,file_type,size_bytes,uploaded_by,created_at',
            'documents.uploader:id,name',
        ]);

        return Inertia::render('MyProducts/Show', [
            'process' => $this->contracts->present($contract, withItems: true),
            'userReview' => ContractReview::with('repliedBy:id,name')
                ->where('contract_id', $contract->id)
                ->where('user_id', auth()->id())
                ->first(),
        ]);
    }

    public function previewDocument(ContractDocument $document)
    {
        $this->authorizeContractAccess($document->contract);

        abort_unless(Storage::disk(ContractDocumentService::DISK)->exists($document->file_path), 404, 'Arquivo não encontrado.');

        return Storage::disk(ContractDocumentService::DISK)->response($document->file_path, $document->name);
    }

    public function downloadDocument(ContractDocument $document)
    {
        $this->authorizeContractAccess($document->contract);

        abort_unless(Storage::disk(ContractDocumentService::DISK)->exists($document->file_path), 404, 'Arquivo não encontrado.');

        return Storage::disk(ContractDocumentService::DISK)->download($document->file_path, $document->name);
    }

}
