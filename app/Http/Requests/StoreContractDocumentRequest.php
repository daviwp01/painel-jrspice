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
            'file.required' => __('No file uploaded.'),
            'file.max' => __('The file exceeds the limit of :size.', ['size' => ContractDocumentRules::humanMaxSize()]),
            'file.mimes' => __('Unsupported format. Send video (MP4, MOV, M4V, WEBM, AVI), document (PDF, DOC, DOCX), spreadsheet (XLS, XLSX) or image (JPG, JPEG, PNG).'),
        ];
    }
}
