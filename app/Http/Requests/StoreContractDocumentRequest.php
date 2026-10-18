<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\ContractDocumentRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreContractDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_master;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:'.ContractDocumentRules::MAX_KILobytes,
                'mimes:'.ContractDocumentRules::mimeTypes(),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Nenhum arquivo enviado.',
            'file.max' => 'O arquivo excede o limite de '.ContractDocumentRules::humanMaxSize().'.',
            'file.mimes' => 'Formato não aceito. Envie vídeo (MP4, MOV, M4V, WEBM, AVI), documento (PDF, DOC, DOCX), planilha (XLS, XLSX) ou imagem (JPG, JPEG, PNG).',
        ];
    }
}
