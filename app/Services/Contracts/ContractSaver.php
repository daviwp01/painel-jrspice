<?php

namespace App\Services\Contracts;

use App\Models\Contract;
use App\Models\ContractItem;
use Illuminate\Support\Facades\DB;

final class ContractSaver
{
    public function __construct(private readonly ContractProductCatalog $catalog) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, int|string>  $userIds
     */
    public function create(array $data, array $userIds = []): Contract
    {
        return DB::transaction(function () use ($data, $userIds) {
            $contract = Contract::create($this->contractAttributes($data));
            $this->persistItems($contract, $data['items'] ?? []);
            $contract->users()->sync($this->filterUserIds($userIds));

            return $contract->fresh(['items.product', 'exporter', 'importer', 'seller', 'users']);
        });
    }

    /**
     * Passing a null user list keeps the current links untouched.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, int|string>|null  $userIds
     */
    public function update(Contract $contract, array $data, ?array $userIds): Contract
    {
        return DB::transaction(function () use ($contract, $data, $userIds) {
            $contract->update($this->contractAttributes($data));
            $this->persistItems($contract, $data['items'] ?? []);

            if ($userIds !== null) {
                $contract->users()->sync($this->filterUserIds($userIds));
            }

            return $contract->fresh(['items.product', 'exporter', 'importer', 'seller', 'users']);
        });
    }

    /**
     * Status changes must never touch the linked users of the contract.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateStatus(Contract $contract, array $data): Contract
    {
        $contract->update([
            'status' => $data['status'],
            'status_date' => $data['status_date'] ?? $contract->status_date,
        ]);

        return $contract;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function persistItems(Contract $contract, array $items): void
    {
        $exchangeRate = $this->toFloat($contract->exchange_rate);
        $existing = $contract->items()->get()->keyBy('id');

        // Park the current items so positions can be reassigned without
        // colliding with the (contract_id, position) unique index.
        $parked = 60000;
        foreach ($existing as $item) {
            $item->forceFill(['position' => $parked++])->saveQuietly();
        }

        $keptIds = [];

        foreach (array_values($items) as $index => $payload) {
            $sales = $this->calculateSales($payload);
            $commission = $this->calculateCommission($payload, $sales);
            $toPay = $this->calculateToPay($payload, $sales);
            $estimatedEuro = ($commission === null || $exchangeRate === null)
                ? $this->toFloat($payload['estimated_euro'] ?? null)
                : round($commission * $exchangeRate, 3);

            $attributes = [
                'position' => $index + 1,
                'register_number' => $this->nullableString($payload['register_number'] ?? null),
                'product_id' => $this->resolveProductId($payload),
                'quantity_tons' => $this->toFloat($payload['quantity_tons'] ?? null),
                'price_per_ton_usd' => $this->toFloat($payload['price_per_ton_usd'] ?? null),
                'sales_usd' => $sales,
                'commission_rate' => $this->toFloat($payload['commission_rate'] ?? null),
                'commission_usd' => $commission,
                'exchange_rate' => $this->toFloat($payload['exchange_rate'] ?? null) ?? $contract->exchange_rate,
                'estimated_euro' => $estimatedEuro,
                'to_pay_rate' => $this->toFloat($payload['to_pay_rate'] ?? null),
                'to_pay_usd' => $toPay,
                'etd_date' => $this->nullableDate($payload['etd_date'] ?? null),
                'eta_date' => $this->nullableDate($payload['eta_date'] ?? null),
                'dhl_number' => $this->nullableString($payload['dhl_number'] ?? null),
                'status_date' => $this->nullableDate($payload['status_date'] ?? null),
                'video_date' => $this->nullableDate($payload['video_date'] ?? null),
                'video_reference' => $this->nullableString($payload['video_reference'] ?? null),
                'observations' => $this->nullableString($payload['observations'] ?? null),
            ];

            $target = (! empty($payload['id']) && isset($existing[$payload['id']]))
                ? $existing[$payload['id']]
                : null;

            if ($target !== null) {
                $target->update($attributes);
                $keptIds[] = $target->getKey();

                continue;
            }

            $keptIds[] = ContractItem::create($attributes + ['contract_id' => $contract->id])->getKey();
        }

        $contract->items()->whereNotIn('id', $keptIds)->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function contractAttributes(array $data): array
    {
        $incident = $this->nullableString($data['incident'] ?? null);

        return [
            'date' => $this->nullableDate($data['date'] ?? null),
            'contract_number' => $this->nullableString($data['contract_number'] ?? null),
            'exporter_id' => $this->nullableId($data['exporter_id'] ?? null),
            'importer_id' => $this->nullableId($data['importer_id'] ?? null),
            'country' => $this->nullableString($data['country'] ?? null),
            'seller_id' => $this->nullableId($data['seller_id'] ?? null),
            'status' => $this->nullableString($data['status'] ?? null),
            'status_date' => $this->nullableDate($data['status_date'] ?? null),
            'dhl_date' => $this->nullableDate($data['dhl_date'] ?? null),
            'dhl_number' => $this->nullableString($data['dhl_number'] ?? null),
            'etd_date' => $this->nullableDate($data['etd_date'] ?? null),
            'eta_date' => $this->nullableDate($data['eta_date'] ?? null),
            'video_sent' => (bool) ($data['video_sent'] ?? false),
            'video_reference' => $this->nullableString($data['video_reference'] ?? null),
            'video_date' => $this->nullableDate($data['video_date'] ?? null),
            'incident' => $incident,
            'has_incident' => $incident !== null,
            'estimated_receipt_date' => $this->nullableDate($data['estimated_receipt_date'] ?? null),
            'exchange_rate' => $this->toFloat($data['exchange_rate'] ?? null),
            'receipt_date' => $this->nullableDate($data['receipt_date'] ?? null),
            'paid_in_date' => $this->nullableDate($data['paid_in_date'] ?? null),
            'paid_in_brl' => $this->toFloat($data['paid_in_brl'] ?? null),
            'observations' => $this->nullableString($data['observations'] ?? null),
            'shipping_company' => $this->nullableString($data['shipping_company'] ?? null),
            'container_number' => $this->nullableString($data['container_number'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function calculateSales(array $payload): ?float
    {
        $quantity = $this->toFloat($payload['quantity_tons'] ?? null);
        $price = $this->toFloat($payload['price_per_ton_usd'] ?? null);

        if ($quantity === null && $price === null) {
            return $this->toFloat($payload['sales_usd'] ?? null);
        }

        return round((float) ($quantity ?? 0) * (float) ($price ?? 0), 2);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function calculateCommission(array $payload, ?float $sales): ?float
    {
        $rate = $this->toFloat($payload['commission_rate'] ?? null);
        if ($rate !== null && $sales !== null) {
            return round($sales * $rate / 100, 2);
        }

        return $this->toFloat($payload['commission_usd'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function calculateToPay(array $payload, ?float $sales): ?float
    {
        $rate = $this->toFloat($payload['to_pay_rate'] ?? null);
        if ($rate !== null && $sales !== null) {
            return round($sales * $rate / 100, 2);
        }

        return $this->toFloat($payload['to_pay_usd'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveProductId(array $payload): ?int
    {
        if (! empty($payload['product_id'])) {
            return (int) $payload['product_id'];
        }

        if (! empty($payload['product_name'])) {
            return $this->catalog->resolve((string) $payload['product_name'])?->id;
        }

        return null;
    }

    /**
     * @param  array<int, int|string>  $userIds
     * @return array<int, int>
     */
    private function filterUserIds(array $userIds): array
    {
        return array_values(array_unique(array_map('intval', array_filter($userIds, 'is_numeric'))));
    }

    private function toFloat(mixed $value): ?float
    {
        if ($value === null || $value === '' || $value === false) {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function nullableDate(mixed $value): ?string
    {
        if ($value === null || $value === '' || $value === '0000-00-00') {
            return null;
        }

        return strtotime((string) $value) === false ? null : date('Y-m-d', strtotime((string) $value));
    }

    private function nullableId(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }
}
