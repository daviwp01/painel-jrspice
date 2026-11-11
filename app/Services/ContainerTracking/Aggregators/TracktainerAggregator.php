<?php

declare(strict_types=1);

namespace App\Services\ContainerTracking\Aggregators;

use App\DataTransferObjects\TrackingDataDTO;
use App\Exceptions\Tracking\CarrierApiException;
use App\Exceptions\Tracking\ContainerNotFoundException;
use App\Services\ContainerTracking\Tracktainer\TracktainerClient;

/**
 * Tracktainer integration (https://docs.tracktainer.com).
 *
 * Contract validated against the live API + OpenAPI 3.1 (v1.4.0) on 2026-11-11.
 * HTTP/auth/errors live in TracktainerClient; this class only orchestrates the
 * list-first lookup (free, includes COMPLETED) and maps JSON:API → TrackingDataDTO.
 *
 * Lookup order:
 *   1. GET list?filter[shipment_number]  -> existing id (free)
 *   2. POST /ocean/shipments            -> id (1 credit; 409 = already tracked, free)
 *   3. GET /ocean/shipments/{id}        -> full payload (free)
 */
final class TracktainerAggregator
{
    /**
     * Tracktainer shipment_status → the five statuses the portal already renders
     * via getTrackingStatusLabel() in resources/js/Utils/contracts.js.
     *
     * @var array<string, string>
     */
    private const STATUS_MAP = [
        'PENDING' => 'PENDING',
        'PLANNED' => 'PENDING',
        'IN_TRANSIT' => 'IN_TRANSIT',
        'ARRIVED' => 'DISCHARGED',
        'COMPLETED' => 'DELIVERED',
        'CANCELLED' => 'UNKNOWN',
    ];

    /**
     * DCSA event codes → human labels. Unknown codes fall back to a title-cased
     * form of the code itself (same behaviour as the Terminal49 adapter).
     *
     * @var array<string, string>
     */
    private const EVENT_LABELS = [
        'EMSH' => 'Empty shipped',
        'TAIN' => 'Empty in',
        'GTIN' => 'Gate in',
        'LOAD' => 'Loaded',
        'DEPA' => 'Departed',
        'ARRI' => 'Arrived',
        'DISCH' => 'Discharged',
        'GTOR' => 'Gate out',
        'EMRT' => 'Empty returned',
        'ESTI' => 'Estimated',
        'ACTI' => 'Actual',
    ];

    public function __construct(
        private readonly TracktainerClient $client,
        private readonly string $carrierName,
        private readonly string $carrierCode,
    ) {}

    /**
     * @param  array<string, mixed>  $config  the `tracking.aggregator` block
     */
    public static function make(array $config, string $carrierName, string $carrierCode): self
    {
        return new self(new TracktainerClient($config), $carrierName, $carrierCode);
    }

    public function track(string $container): TrackingDataDTO
    {
        $shipmentId = $this->client->findShipmentId($container)
            ?? $this->client->createShipment($container, $this->numberType($container));

        $detail = $this->client->getShipment($shipmentId);

        return $this->toDto($container, $detail);
    }

    /**
     * ISO 6346 containers are typed explicitly; anything else (booking or B/L)
     * goes as BOOKING so Tracktainer runs its own reference detection.
     */
    private function numberType(string $container): string
    {
        return preg_match('/^[A-Z]{4}[0-9]{7}$/', $container) === 1 ? 'CONTAINER' : 'BOOKING';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function toDto(string $container, array $payload): TrackingDataDTO
    {
        $attributes = (array) data_get($payload, 'data.attributes', []);
        $failure = $attributes['failure_reason'] ?? null;

        if ($failure === 'NOT_FOUND') {
            throw new ContainerNotFoundException($container);
        }

        if (in_array($failure, ['CARRIER_NOT_SUPPORTED', 'CARRIER_NOT_DETECTED'], true)) {
            throw new CarrierApiException(
                sprintf('Tracktainer não identificou a armadora para %s.', $container),
                TrackingDataDTO::STATE_UNSUPPORTED,
            );
        }

        $carrier = (array) ($attributes['carrier'] ?? []);
        $movements = $this->movementsFor($container, $attributes);

        $message = null;
        if ($failure === 'CANCELLED') {
            $message = 'Shipment cancelled by the carrier.';
        }

        return new TrackingDataDTO(
            containerNumber: $container,
            carrierName: (string) ($carrier['name'] ?? $this->carrierName),
            carrierCode: (string) ($carrier['scac'] ?? $this->carrierCode),
            status: $this->mapStatus((string) ($attributes['shipment_status'] ?? ''), $movements),
            state: TrackingDataDTO::STATE_OK,
            origin: $this->section([
                'port' => data_get($attributes, 'port_of_loading.name') ?? data_get($attributes, 'origin.name'),
                'country' => data_get($attributes, 'port_of_loading.country.name') ?? data_get($attributes, 'origin.country.name'),
                'departure' => $attributes['etd'] ?? null,
            ]),
            destination: $this->section([
                'port' => data_get($attributes, 'port_of_discharge.name') ?? data_get($attributes, 'destination.name'),
                'country' => data_get($attributes, 'port_of_discharge.country.name') ?? data_get($attributes, 'destination.country.name'),
                'eta' => $attributes['eta'] ?? null,
            ]),
            vessel: $this->vesselFrom($movements),
            events: $this->mapEvents($movements),
            lastUpdated: $this->stringOrNull($attributes['last_checked_at'] ?? $attributes['updated_at'] ?? null),
            message: $message,
            provider: 'tracktainer',
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return list<array<string, mixed>>
     */
    private function movementsFor(string $container, array $attributes): array
    {
        $containers = array_values((array) ($attributes['containers'] ?? []));

        $row = null;
        foreach ($containers as $candidate) {
            $number = strtoupper((string) data_get($candidate, 'container_number', ''));

            if ($number === $container) {
                $row = $candidate;
                break;
            }
        }

        $row ??= $containers[0] ?? null;

        if (! is_array($row)) {
            return [];
        }

        return array_values((array) ($row['movements'] ?? []));
    }

    /**
     * @param  list<array<string, mixed>>  $movements
     * @return list<array{date: ?string, location: ?string, description: ?string, status_code: ?string, transport: ?string, voyage: ?string}>
     */
    private function mapEvents(array $movements): array
    {
        $events = [];

        foreach ($movements as $movement) {
            if (! is_array($movement)) {
                continue;
            }

            $code = $this->stringOrNull($movement['event'] ?? null);

            $events[] = [
                'date' => $this->stringOrNull($movement['date'] ?? null),
                'location' => $this->stringOrNull(data_get($movement, 'location.name')),
                'description' => $this->describeEvent($code),
                'status_code' => $code,
                'transport' => $this->stringOrNull($movement['transport_mode'] ?? null),
                'voyage' => $this->stringOrNull($movement['voyage'] ?? null),
            ];
        }

        return $events;
    }

    /**
     * @param  list<array<string, mixed>>  $movements
     * @return array<string, string>
     */
    private function vesselFrom(array $movements): array
    {
        foreach (array_reverse($movements) as $movement) {
            $vessel = data_get($movement, 'vessel.name');

            if (is_string($vessel) && $vessel !== '') {
                return $this->section([
                    'name' => $vessel,
                    'voyage' => data_get($movement, 'voyage'),
                ]);
            }
        }

        return [];
    }

    /**
     * @param  list<array<string, mixed>>  $movements
     */
    private function mapStatus(string $shipmentStatus, array $movements): string
    {
        $normalized = strtoupper(trim($shipmentStatus));

        if ($normalized === 'COMPLETED') {
            foreach (array_reverse($movements) as $movement) {
                if (strtoupper((string) ($movement['event'] ?? '')) === 'EMRT') {
                    return 'DELIVERED';
                }
            }
        }

        return self::STATUS_MAP[$normalized] ?? 'UNKNOWN';
    }

    private function describeEvent(?string $code): ?string
    {
        if ($code === null || $code === '') {
            return null;
        }

        $upper = strtoupper($code);

        return self::EVENT_LABELS[$upper]
            ?? ucwords(str_replace(['_', '-'], ' ', strtolower($upper)));
    }

    /**
     * @param  array<string, mixed|null>  $values
     * @return array<string, string>
     */
    private function section(array $values): array
    {
        $section = [];

        foreach ($values as $key => $value) {
            $string = $this->stringOrNull($value);

            if ($string !== null) {
                $section[(string) $key] = $string;
            }
        }

        return $section;
    }

    private function stringOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '' || is_array($value)) {
            return null;
        }

        if (is_bool($value) || is_int($value) || is_float($value)) {
            return (string) $value;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
