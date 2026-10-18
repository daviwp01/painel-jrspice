<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreContractDocumentRequest;
use App\Models\Contract;
use App\Models\ContractDocument;
use App\Services\Contracts\ContractDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Documents attached to a contract.
 *
 * Only masters upload: the client portal is read-only and only lists what was
 * attached here.
 */
class ContractDocumentController extends Controller
{
    public function __construct(private readonly ContractDocumentService $documents)
    {
    }

    public function store(StoreContractDocumentRequest $request, Contract $contract): JsonResponse|RedirectResponse
    {
        $document = $this->documents->upload($contract, $request->file('file'), $request->user());

        $message = sprintf('"%s" enviado com sucesso.', $document->name);

        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'document' => $document->toApiArray()]);
        }

        return back()->with('success', $message);
    }

    public function destroy(Request $request, ContractDocument $document): JsonResponse|RedirectResponse
    {
        $name = $document->name;
        $this->documents->delete($document);

        $message = sprintf('"%s" excluído.', $name);

        if ($request->expectsJson()) {
            return response()->json(['message' => $message]);
        }

        return back()->with('success', $message);
    }
}
