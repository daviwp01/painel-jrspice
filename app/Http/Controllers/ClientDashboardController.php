<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesContractAccess;
use App\Models\Contract;
use App\Models\ContractDocument;
use App\Models\ContractProduct;
use App\Models\ContractReview;
use App\Models\User;
use App\Services\Contracts\ClientContractService;
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
            'documents:id,contract_id,name,file_path,file_type,uploaded_by,created_at',
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

    public function uploadDocument(Request $request, Contract $contract)
    {
        $this->authorizeContractAccess($contract);

        $request->validate([
            'file' => 'required|file|max:20480|mimes:pdf,doc,docx,jpg,jpeg,png,mp4,mov,avi',
        ]);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());

        $type = match (true) {
            in_array($extension, ['jpg', 'jpeg', 'png']) => 'image',
            in_array($extension, ['mp4', 'mov', 'avi']) => 'video',
            default => 'document',
        };

        ContractDocument::create([
            'contract_id' => $contract->id,
            'name' => $file->getClientOriginalName(),
            'file_path' => $file->store("contracts/{$contract->id}"),
            'file_type' => $type,
            'uploaded_by' => auth()->id(),
        ]);

        return redirect()->back()->with('success', 'Documento enviado com sucesso.');
    }

    /**
     * Stream a document inline so it can be previewed in the browser.
     */
    public function previewDocument(ContractDocument $document)
    {
        $this->authorizeContractAccess($document->contract);

        abort_unless(Storage::exists($document->file_path), 404, 'Arquivo não encontrado.');

        return Storage::response($document->file_path, $document->name);
    }

    public function downloadDocument(ContractDocument $document)
    {
        $this->authorizeContractAccess($document->contract);

        abort_unless(Storage::exists($document->file_path), 404, 'Arquivo não encontrado.');

        return Storage::download($document->file_path, $document->name);
    }

    public function deleteDocument(ContractDocument $document)
    {
        $user = auth()->user();

        if ($document->uploaded_by !== $user->id && ! $user->is_master) {
            abort(403, 'Você não tem permissão para excluir este documento.');
        }

        if (Storage::exists($document->file_path)) {
            Storage::delete($document->file_path);
        }

        $document->delete();

        return redirect()->back()->with('success', 'Documento excluído com sucesso.');
    }
}
