<?php

namespace App\Http\Controllers;

use App\Models\ExportProcess;
use App\Models\ExportProcessDocument;
use App\Models\ProcessReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ClientDashboardController extends Controller
{
    /**
     * Display a list of contracts associated with the logged-in client.
     */
    public function myProducts(Request $request)
    {
        $user = auth()->user();
        $userId = $user->id;
        $userClientId = $user->client_id;

        $applyUserFilter = function ($q) use ($userId) {
            $q->whereHas('users', function ($uq) use ($userId) {
                $uq->where('users.id', $userId);
            });
        };

        $query = ExportProcess::with(['exporter', 'importer', 'product', 'seller', 'users'])
            ->where($applyUserFilter)
            ->orderBy('date', 'desc');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('contract_number', 'like', "%{$search}%")
                  ->orWhere('register_number', 'like', "%{$search}%")
                  ->orWhereHas('product', function ($pq) use ($search) {
                      $pq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $processes = $query->paginate(20)->withQueryString();

        $totalTons = ExportProcess::where($applyUserFilter)->sum('quantity_tons');
        $totalContracts = ExportProcess::where($applyUserFilter)->count();
        $activeShipments = ExportProcess::where($applyUserFilter)
            ->whereNotNull('container_number')
            ->whereNotIn('status', ['Processo FINALIZADO'])
            ->count();

        return Inertia::render('MyProducts/Index', [
            'exportProcesses' => $processes,
            'filters' => $request->only(['search']),
            'summary' => [
                'total_tons' => $totalTons,
                'total_contracts' => $totalContracts,
                'active_shipments' => $activeShipments
            ]
        ]);
    }

    /**
     * Show details of a specific contract/order.
     */
    public function showContract(ExportProcess $process)
    {
        $this->authorizeAccess($process);

        $process->load(['exporter', 'importer', 'product', 'seller', 'users', 'documents.uploader']);

        // Load this user's review (if any)
        $user = auth()->user();
        $userReview = ProcessReview::with('repliedBy:id,name')
            ->where('export_process_id', $process->id)
            ->where('user_id', $user->id)
            ->first();

        return Inertia::render('MyProducts/Show', [
            'process'    => $process,
            'userReview' => $userReview,
        ]);
    }

    /**
     * Upload a document for a specific contract.
     */
    public function uploadDocument(Request $request, ExportProcess $process)
    {
        $this->authorizeAccess($process);

        $request->validate([
            'file' => 'required|file|max:20480|mimes:pdf,doc,docx,jpg,jpeg,png,mp4,mov,avi',
        ]);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $originalName = $file->getClientOriginalName();
            $extension = strtolower($file->getClientOriginalExtension());
            
            // Determine type
            $type = 'document';
            if (in_array($extension, ['jpg', 'jpeg', 'png'])) {
                $type = 'image';
            } elseif (in_array($extension, ['mp4', 'mov', 'avi'])) {
                $type = 'video';
            }

            // Save file securely (in private storage)
            $path = $file->store("contracts/{$process->id}");

            ExportProcessDocument::create([
                'export_process_id' => $process->id,
                'name' => $originalName,
                'file_path' => $path,
                'file_type' => $type,
                'uploaded_by' => $user->id,
            ]);

            return redirect()->back()->with('success', 'Documento enviado com sucesso.');
        }

        return redirect()->back()->with('error', 'Nenhum arquivo enviado.');
    }

    /**
     * Download a contract document.
     */
    public function downloadDocument(ExportProcessDocument $document)
    {
        $user = auth()->user();
        $process = $document->exportProcess;

        // Security check
        if ($process->exporter_id !== $user->client_id && $process->importer_id !== $user->client_id) {
            abort(403, 'Você não tem permissão para baixar este documento.');
        }

        if (!Storage::exists($document->file_path)) {
            abort(404, 'Arquivo não encontrado.');
        }

        return Storage::download($document->file_path, $document->name);
    }

    /**
     * Delete a contract document.
     */
    public function deleteDocument(ExportProcessDocument $document)
    {
        $user = auth()->user();
        $process = $document->exportProcess;

        // Security check - only allow if user uploaded it OR they are master
        if ($document->uploaded_by !== $user->id && !$user->is_master) {
            abort(403, 'Você não tem permissão para excluir este documento.');
        }

        // Delete from storage
        if (Storage::exists($document->file_path)) {
            Storage::delete($document->file_path);
        }

        // Delete from database
        $document->delete();

        return redirect()->back()->with('success', 'Documento excluído com sucesso.');
    }

    private function authorizeAccess(ExportProcess $process)
    {
        $user = auth()->user();
        if ($user->is_master) return;

        $isLinkedUser = $process->users()->where('users.id', $user->id)->exists();

        if (!$isLinkedUser) {
            abort(403, 'Você não tem permissão para acessar este contrato.');
        }
    }
}
