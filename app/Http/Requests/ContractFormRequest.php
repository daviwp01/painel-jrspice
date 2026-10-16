<?php

namespace App\Http\Requests;

use App\Support\ContractStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class ContractFormRequest extends FormRequest
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
            'date' => ['nullable', 'date'],
            'contract_number' => ['nullable', 'string', 'max:50'],
            'exporter_id' => ['nullable', 'integer', Rule::exists('clients', 'id')],
            'importer_id' => ['nullable', 'integer', Rule::exists('clients', 'id')],
            'country' => ['nullable', 'string', 'max:100'],
            'seller_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'status' => ['nullable', 'string', Rule::in(ContractStatus::ALL)],
            'status_date' => ['nullable', 'date'],
            'dhl_date' => ['nullable', 'date'],
            'dhl_number' => ['nullable', 'string', 'max:255'],
            'etd_date' => ['nullable', 'date'],
            'eta_date' => ['nullable', 'date'],
            'video_sent' => ['sometimes', 'boolean'],
            'video_reference' => ['nullable', 'string', 'max:255'],
            'video_date' => ['nullable', 'date'],
            'incident' => ['nullable', 'string', 'max:5000'],
            'estimated_receipt_date' => ['nullable', 'date'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'receipt_date' => ['nullable', 'date'],
            'paid_in_date' => ['nullable', 'date'],
            'paid_in_brl' => ['nullable', 'numeric', 'min:0'],
            'observations' => ['nullable', 'string', 'max:10000'],
            'shipping_company' => ['nullable', 'string', 'max:100'],
            'container_number' => ['nullable', 'string', 'max:100'],

            'user_ids' => ['sometimes', 'array'],
            'user_ids.*' => ['integer', Rule::exists('users', 'id')],

            'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*.id' => ['sometimes', 'integer'],
            'items.*.register_number' => ['nullable', 'string', 'max:50'],
            'items.*.product_id' => ['nullable', 'integer', Rule::exists('contract_products', 'id')],
            'items.*.product_name' => ['nullable', 'string', 'max:255'],
            'items.*.quantity_tons' => ['nullable', 'numeric', 'min:0'],
            'items.*.price_per_ton_usd' => ['nullable', 'numeric', 'min:0'],
            'items.*.commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.commission_usd' => ['nullable', 'numeric', 'min:0'],
            'items.*.to_pay_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.to_pay_usd' => ['nullable', 'numeric', 'min:0'],
            'items.*.exchange_rate' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'items.*.estimated_euro' => ['nullable', 'numeric'],
            'items.*.etd_date' => ['nullable', 'date'],
            'items.*.eta_date' => ['nullable', 'date'],
            'items.*.status_date' => ['nullable', 'date'],
            'items.*.video_date' => ['nullable', 'date'],
            'items.*.dhl_number' => ['nullable', 'string', 'max:255'],
            'items.*.video_reference' => ['nullable', 'string', 'max:255'],
            'items.*.observations' => ['nullable', 'string', 'max:10000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.in' => 'O status informado não é reconhecido.',
            'items.required' => 'O contrato precisa de ao menos um item.',
            'items.min' => 'O contrato precisa de ao menos um item.',
            'user_ids.*.exists' => 'Um dos usuários selecionados não existe.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'items' => $this->sanitizeItems($this->input('items', [])),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function sanitizeItems(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return array_map(static function (mixed $item): array {
            if (! is_array($item)) {
                return [];
            }

            return array_filter($item, static fn ($value) => $value !== '' && $value !== null);
        }, array_values($items));
    }
}
