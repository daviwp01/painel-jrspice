<?php

declare(strict_types=1);

namespace App\Services\ContainerTracking\Tracktainer;

/**
 * Extracts the arrival date from a Tracktainer shipment payload.
 *
 * Priority:
 *   1. Latest ACT movement with an arrival code (ARRI / DISCH) — the real date
 *      the vessel arrived / was discharged at destination.
 *   2. The shipment `eta` attribute when status is ARRIVED or COMPLETED —
 *      carrier-confirmed arrival date when movement detail is missing.
 *   3. null — still in transit (or no arrival signal at all).
 *
 * Pure function of the payload: no HTTP, no persistence.
 */
final class ArrivalDateExtractor
{
    private const ARRIVAL_EVENTS = ['ARRI', 'DISCH'];

    /**
     * @param  array<string, mixed>  $attributes  data.attributes of an ocean-shipments payload
     * @param  string|null  $container  when set, prefer this container's movements
     *                                  (multi-container / booking shipments)
     */
    public function extract(array $attributes, ?string $container = null): ?string
    {
        $status = strtoupper(trim((string) ($attributes['shipment_status'] ?? '')));

        if (! in_array($status, ['ARRIVED', 'COMPLETED', 'IN_TRANSIT'], true)) {
            // PENDING / PLANNED / CANCELLED carry no trustworthy arrival.
            return null;
        }

        $actual = $this->actualArrivalFromMovements($attributes, $container);

        if ($actual !== null) {
            return $actual;
        }

        if (in_array($status, ['ARRIVED', 'COMPLETED'], true)) {
            $eta = $attributes['eta'] ?? null;

            if (is_string($eta) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $eta) === 1) {
                return $eta;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  string|null  $container  restrict movements to this container when present
     */
    private function actualArrivalFromMovements(array $attributes, ?string $container = null): ?string
    {
        $rows = array_reverse((array) ($attributes['containers'] ?? []), true);

        if ($container !== null) {
            $needle = strtoupper($container);
            $filtered = [];

            foreach ($rows as $row) {
                if (is_array($row) && strtoupper((string) ($row['container_number'] ?? '')) === $needle) {
                    $filtered[] = $row;
                }
            }

            // Fall back to every container when the number is only on the shipment.
            $rows = $filtered !== [] ? $filtered : $rows;
        }

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $movements = array_reverse(array_values((array) ($row['movements'] ?? [])));

            foreach ($movements as $movement) {
                if (! is_array($movement)) {
                    continue;
                }

                $code = strtoupper((string) ($movement['event'] ?? ''));
                $classifier = strtoupper((string) ($movement['classifier'] ?? ''));

                if (! in_array($code, self::ARRIVAL_EVENTS, true)) {
                    continue;
                }

                // Only confirmed facts: ACT beats EST/PLN forecasts.
                if ($classifier !== '' && $classifier !== 'ACT') {
                    continue;
                }

                $date = $movement['date'] ?? null;

                if (is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1) {
                    return $date;
                }
            }
        }

        return null;
    }
}
