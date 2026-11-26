<?php

declare(strict_types=1);

namespace App\Services\ContainerTracking\Tracktainer;

use App\Models\Contract;
use Illuminate\Support\Collection;

/**
 * Writes Tracktainer arrival dates onto contracts.
 *
 * A shipment may be tracked by booking / B/L while contracts store the ISO
 * container (and vice versa), and one shipment can carry several containers.
 * Matching therefore uses every reference present on the payload:
 * shipment_number plus each containers[].container_number.
 *
 * eta_date is updated only when the extracted date is present and different —
 * no-op polls write nothing.
 */
final class ContractArrivalSyncService
{
    public function __construct(private readonly ArrivalDateExtractor $extractor) {}

    /**
     * @param  array<string, mixed>  $attributes  data.attributes of an ocean-shipments payload
     * @param  bool  $persist  false = dry-run (same extraction, no writes)
     * @return list<array{contract_id: int, container: string, eta_date: string}>
     *                                                                            Contracts that were updated (or would be, when $persist is false).
     */
    public function syncShipment(array $attributes, bool $persist = true): array
    {
        $references = $this->references($attributes);

        if ($references === []) {
            return [];
        }

        $contracts = $this->contractsFor($references);

        if ($contracts->isEmpty()) {
            return [];
        }

        $updated = [];

        foreach ($contracts as $contract) {
            $arrival = $this->extractor->extract($attributes, $contract->container_number);

            if ($arrival === null) {
                continue;
            }

            $change = $this->applyArrival($contract, $arrival, $persist);

            if ($change !== null) {
                $updated[] = $change;
            }
        }

        return $updated;
    }

    /**
     * Provider-agnostic write path used by TimeToCargo (webhook + polling).
     *
     * @param  list<string>  $references  booking / B/L / container numbers on the shipment
     * @param  array<string, string>  $arrivals  uppercase container number → Y-m-d
     * @param  bool  $persist  false = dry-run
     * @return list<array{contract_id: int, container: string, eta_date: string}>
     */
    public function syncFromNormalized(array $references, array $arrivals, bool $persist = true): array
    {
        $arrivalsByUpper = [];

        foreach ($arrivals as $container => $date) {
            if (! is_string($container) || ! is_string($date) || $date === '') {
                continue;
            }

            $arrivalsByUpper[strtoupper($container)] = $date;
        }

        $allReferences = array_values(array_unique([
            ...array_map(static fn (string $r): string => trim($r), $references),
            ...array_keys($arrivalsByUpper),
        ]));

        $allReferences = array_values(array_filter($allReferences, static fn (string $r): bool => $r !== ''));

        if ($allReferences === []) {
            return [];
        }

        $contracts = $this->contractsFor($allReferences);

        if ($contracts->isEmpty()) {
            return [];
        }

        $updated = [];

        foreach ($contracts as $contract) {
            $arrival = $this->arrivalForContainer($arrivalsByUpper, $contract->container_number);

            if ($arrival === null) {
                continue;
            }

            $change = $this->applyArrival($contract, $arrival, $persist);

            if ($change !== null) {
                $updated[] = $change;
            }
        }

        return $updated;
    }

    /**
     * @param  array<string, string>  $arrivalsByUpper
     */
    private function arrivalForContainer(array $arrivalsByUpper, ?string $containerNumber): ?string
    {
        if ($containerNumber === null || $containerNumber === '') {
            return null;
        }

        return $arrivalsByUpper[strtoupper($containerNumber)] ?? null;
    }

    /**
     * @return array{contract_id: int, container: string, eta_date: string}|null
     */
    private function applyArrival(Contract $contract, string $arrival, bool $persist): ?array
    {
        $current = $contract->eta_date?->toDateString();

        if ($current === $arrival) {
            return null;
        }

        if ($persist) {
            $contract->forceFill(['eta_date' => $arrival])->saveQuietly();
        }

        return [
            'contract_id' => $contract->id,
            'container' => $contract->container_number,
            'eta_date' => $arrival,
        ];
    }

    /**
     * shipment_number (container, booking or B/L) + every container on the
     * shipment, so a booking-tracked payload still matches container contracts.
     *
     * @return list<string>
     */
    private function references(array $attributes): array
    {
        $refs = [];

        $primary = trim((string) ($attributes['shipment_number'] ?? ''));

        if ($primary !== '') {
            $refs[] = $primary;
        }

        foreach ((array) ($attributes['containers'] ?? []) as $container) {
            if (! is_array($container)) {
                continue;
            }

            $number = trim((string) ($container['container_number'] ?? ''));

            if ($number !== '') {
                $refs[] = $number;
            }
        }

        return array_values(array_unique($refs));
    }

    /**
     * Containers are stored with mixed casing in historical imports; match
     * exact, upper and lower forms of every reference.
     *
     * @param  list<string>  $references
     * @return Collection<int, Contract>
     */
    private function contractsFor(array $references): Collection
    {
        $variants = [];

        foreach ($references as $reference) {
            $variants[] = $reference;
            $variants[] = strtoupper($reference);
            $variants[] = strtolower($reference);
        }

        return Contract::query()
            ->whereIn('container_number', array_values(array_unique($variants)))
            ->get(['id', 'container_number', 'eta_date']);
    }
}
