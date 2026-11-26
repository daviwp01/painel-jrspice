<?php

declare(strict_types=1);

namespace App\Services\ContainerTracking\TimeToCargo;

/**
 * Extracts arrival dates and references from a TimeToCargo `data` payload
 * (`GET /v2/tracking` body.data, or nested under shipments list
 * `tracked_item.data.data`).
 *
 * Arrival priority:
 *   1. Event with status_code VAD or CDD and actual=true — real arrival/discharge.
 *   2. summary.pod.date when shipment_status is DELIVERED or ARRIVED —
 *      carrier-confirmed destination date when event detail is missing.
 *   3. null — still in transit (or no arrival signal).
 *
 * Pure function of the payload: no HTTP, no persistence.
 */
final class TimeToCargoArrivalExtractor
{
    private const ARRIVAL_CODES = ['VAD', 'CDD'];

    private const ARRIVED_STATUSES = ['DELIVERED', 'ARRIVED', 'COMPLETED'];

    /**
     * @param  array<string, mixed>  $data  TimeToCargo tracking `data` object
     * @return array{references: list<string>, arrivals: array<string, string>}
     *                                                                   arrivals keyed by uppercase container number → Y-m-d.
     */
    public function extract(array $data): array
    {
        $references = $this->references($data);
        $arrivals = [];

        $status = strtoupper(trim((string) ($data['shipment_status'] ?? '')));

        foreach ((array) ($data['containers'] ?? []) as $container) {
            if (! is_array($container)) {
                continue;
            }

            $number = strtoupper(trim((string) ($container['number'] ?? '')));

            if ($number === '') {
                continue;
            }

            $references[] = $number;

            $arrival = $this->actualArrivalFromEvents($container)
                ?? $this->podFallback($data, $status);

            if ($arrival !== null) {
                $arrivals[$number] = $arrival;
            }
        }

        // Shipment-level fallback when no containers array but status confirms arrival.
        if ($arrivals === [] && $status !== '') {
            $arrival = $this->podFallback($data, $status);

            if ($arrival !== null) {
                foreach ($references as $reference) {
                    $key = strtoupper($reference);

                    if (preg_match('/^[A-Z]{4}[0-9]{7}$/', $key) === 1) {
                        $arrivals[$key] = $arrival;
                    }
                }
            }
        }

        return [
            'references' => array_values(array_unique($references)),
            'arrivals' => $arrivals,
        ];
    }

    /**
     * @param  array<string, mixed>  $container
     */
    private function actualArrivalFromEvents(array $container): ?string
    {
        $events = array_values((array) ($container['events'] ?? []));

        // Newest first when fully dated; scan actual arrival codes either way.
        foreach ($events as $event) {
            if (! is_array($event)) {
                continue;
            }

            $code = strtoupper((string) ($event['status_code'] ?? ''));

            if (! in_array($code, self::ARRIVAL_CODES, true)) {
                continue;
            }

            if (($event['actual'] ?? false) !== true) {
                continue;
            }

            $date = $this->dateOnly($event['date'] ?? null);

            if ($date !== null) {
                return $date;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function podFallback(array $data, string $status): ?string
    {
        if (! in_array($status, self::ARRIVED_STATUSES, true)) {
            return null;
        }

        return $this->dateOnly(data_get($data, 'summary.pod.date'));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function references(array $data): array
    {
        $refs = [];

        foreach ((array) data_get($data, 'references.booking_numbers', []) as $booking) {
            if (is_string($booking) && trim($booking) !== '') {
                $refs[] = trim($booking);
            }
        }

        foreach ((array) data_get($data, 'references.bill_of_lading_numbers', []) as $bl) {
            if (is_string($bl) && trim($bl) !== '') {
                $refs[] = trim($bl);
            }
        }

        return $refs;
    }

    private function dateOnly(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $value, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }
}
