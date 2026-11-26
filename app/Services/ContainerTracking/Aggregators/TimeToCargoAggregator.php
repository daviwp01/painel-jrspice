<?php

declare(strict_types=1);

namespace App\Services\ContainerTracking\Aggregators;

use App\DataTransferObjects\TrackingDataDTO;
use App\Exceptions\Tracking\CarrierApiException;
use App\Exceptions\Tracking\ContainerNotFoundException;
use App\Services\ContainerTracking\TimeToCargo\TimeToCargoClient;

/**
 * TimeToCargo integration (https://timetocargo.com/docs/tracking).
 *
 * Contract validated against the live demo API on 2026-11-26:
 *   GET https://tracking.timetocargo.com/v2/tracking?api_key=&company=AUTO&value=&type=
 *   body: { success, status: OK|INFO_NOT_FOUND|..., data: { summary, locations,
 *           containers[], shipment_status, tracking_metadata } }
 *
 * HTTP/auth/errors live in TimeToCargoClient; this class only orchestrates the
 * single GET and maps the payload → TrackingDataDTO (same shape the portal
 * already renders for tracktainer / terminal49).
 */
final class TimeToCargoAggregator
{
    /**
     * TimeToCargo shipment_status → the five statuses the portal already
     * renders via getTrackingStatusLabel() in resources/js/Utils/contracts.js.
     *
     * @var array<string, string>
     */
    private const STATUS_MAP = [
        'PENDING' => 'PENDING',
        'PLANNED' => 'PENDING',
        'BOOKED' => 'PENDING',
        'CREATED' => 'PENDING',
        'IN_TRANSIT' => 'IN_TRANSIT',
        'ON_BOARD' => 'IN_TRANSIT',
        'ARRIVED' => 'DISCHARGED',
        'DISCHARGED' => 'DISCHARGED',
        'DELIVERED' => 'DELIVERED',
        'COMPLETED' => 'DELIVERED',
        'CANCELLED' => 'UNKNOWN',
        'STUCK' => 'IN_TRANSIT',
    ];

    /**
     * Fallback labels for status_code when the API omits `status`.
     *
     * @var array<string, string>
     */
    private const EVENT_LABELS = [
        'CLL' => 'Loaded at first POL',
        'CLT' => 'Loaded at transshipment',
        'VDL' => 'Vessel departure from first POL',
        'VDT' => 'Vessel departure from transshipment',
        'VAD' => 'Vessel arrival at final POD',
        'VAT' => 'Vessel arrival at transit port',
        'CDD' => 'Container discharge at destination',
        'CDT' => 'Container discharge at transshipment',
        'GTIN' => 'Gate in',
        'GTOT' => 'Gate out',
        'EMRT' => 'Empty returned',
    ];

    /**
     * SCAC → the carrier_code keys the portal already knows (CARRIERS in
     * resources/js/Utils/contracts.js). Unknown SCACs fall back to the driver
     * identity so the UI still renders a logo slot.
     *
     * @var array<string, string>
     */
    private const SCAC_TO_CODE = [
        'MAEU' => 'maersk', 'MSKU' => 'maersk', 'MRSU' => 'maersk', 'MRKU' => 'maersk',
        'MCIU' => 'maersk', 'MSDU' => 'maersk', 'MCSU' => 'maersk',
        'MSCU' => 'msc', 'MEDU' => 'msc', 'MEGU' => 'msc',
        'CMDU' => 'cma_cgm', 'CMAU' => 'cma_cgm', 'AMCU' => 'cma_cgm', 'CGMU' => 'cma_cgm', 'CLHU' => 'cma_cgm',
        'HLCU' => 'hapag_lloyd', 'HLXU' => 'hapag_lloyd', 'HCAU' => 'hapag_lloyd',
        'OOLU' => 'oocl', 'OOCU' => 'oocl',
        'COSU' => 'cosco', 'CSNU' => 'cosco', 'CCLU' => 'cosco', 'CBHU' => 'cosco', 'CNCU' => 'cosco',
        'ZIMU' => 'zim', 'ZAXU' => 'zim', 'ZCSU' => 'zim',
        'HDMU' => 'hmm', 'HMMU' => 'hmm',
        'EGLV' => 'evergreen', 'EGHU' => 'evergreen', 'EGSU' => 'evergreen',
        'ONEY' => 'maersk', // ONE has no dedicated brand card yet; name still shows
    ];

    public function __construct(
        private readonly TimeToCargoClient $client,
        private readonly string $carrierName,
        private readonly string $carrierCode,
    ) {}

    /**
     * @param  array<string, mixed>  $config  the `tracking.aggregator` block
     */
    public static function make(array $config, string $carrierName, string $carrierCode): self
    {
        return new self(new TimeToCargoClient($config), $carrierName, $carrierCode);
    }

    public function track(string $container): TrackingDataDTO
    {
        $isIsoContainer = preg_match('/^[A-Z]{4}[0-9]{7}$/', $container) === 1;

        // ISO 6346 → explicit type; booking / B/L omit `type` for auto-detect.
        $body = $this->client->track(
            value: $container,
            type: $isIsoContainer ? 'container' : null,
        );

        return $this->toDto($container, $body);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function toDto(string $container, array $body): TrackingDataDTO
    {
        $status = (string) ($body['status'] ?? '');
        $success = (bool) ($body['success'] ?? false);
        $data = (array) ($body['data'] ?? []);

        if ($status === 'INFO_NOT_FOUND' || ($success && $data === [])) {
            throw new ContainerNotFoundException($container);
        }

        if (! $success || $status !== 'OK') {
            $description = (string) ($body['status_description'] ?? $status);

            throw new CarrierApiException(
                sprintf('TimeToCargo: %s', $description !== '' ? $description : 'resposta sem sucesso.'),
                TrackingDataDTO::STATE_ERROR,
            );
        }

        $summary = (array) ($data['summary'] ?? []);
        $locations = $this->indexLocations((array) ($data['locations'] ?? []));
        $eventsRaw = $this->eventsFor($container, (array) ($data['containers'] ?? []));

        $company = (array) ($summary['company'] ?? []);
        $scacs = array_values(array_filter((array) ($company['scacs'] ?? [])));
        $carrierCode = $scacs[0] ?? $this->carrierCode;
        $carrierName = (string) ($company['full_name'] ?? $company['name'] ?? $this->carrierName);

        $pol = (array) ($summary['pol'] ?? []);
        $pod = (array) ($summary['pod'] ?? []);
        $originLocation = $this->locationAt($locations, $pol['location'] ?? null);
        $destinationLocation = $this->locationAt($locations, $pod['location'] ?? null);

        $events = $this->mapEvents($eventsRaw, $locations);

        return new TrackingDataDTO(
            containerNumber: $container,
            carrierName: $carrierName,
            carrierCode: $this->portalCarrierCode($carrierCode),
            status: $this->mapStatus((string) ($data['shipment_status'] ?? ''), $events),
            state: TrackingDataDTO::STATE_OK,
            origin: $this->section([
                'port' => $originLocation['name'] ?? null,
                'country' => $originLocation['country'] ?? null,
                'departure' => $pol['date'] ?? null,
            ]),
            destination: $this->section([
                'port' => $destinationLocation['name'] ?? null,
                'country' => $destinationLocation['country'] ?? null,
                'eta' => $pod['date'] ?? null,
            ]),
            vessel: $this->vesselFrom($eventsRaw),
            events: $events,
            lastUpdated: $this->stringOrNull(
                data_get($data, 'tracking_metadata.updated_at')
                ?? data_get($data, 'route_info.last_updated')
            ),
            message: null,
            provider: 'timetocargo',
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $containers
     * @return list<array<string, mixed>>
     */
    private function eventsFor(string $container, array $containers): array
    {
        foreach ($containers as $row) {
            $number = strtoupper((string) ($row['number'] ?? ''));

            if ($number === $container || $number === '') {
                return array_values((array) ($row['events'] ?? []));
            }
        }

        $first = $containers[0] ?? null;

        return is_array($first) ? array_values((array) ($first['events'] ?? [])) : [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $locations
     * @return array<int|string, array<string, mixed>>
     */
    private function indexLocations(array $locations): array
    {
        $indexed = [];

        foreach ($locations as $location) {
            if (! is_array($location)) {
                continue;
            }

            $id = $location['id'] ?? null;

            if ($id === null) {
                continue;
            }

            $indexed[$id] = $location;
        }

        return $indexed;
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $locations
     * @return array<string, mixed>|null
     */
    private function locationAt(array $locations, mixed $id): ?array
    {
        if ($id === null) {
            return null;
        }

        return $locations[$id] ?? $locations[(string) $id] ?? null;
    }

    /**
     * @param  list<array<string, mixed>>  $raw
     * @param  array<int|string, array<string, mixed>>  $locations
     * @return list<array{date: ?string, location: ?string, description: ?string, status_code: ?string, transport: ?string, voyage: ?string}>
     */
    private function mapEvents(array $raw, array $locations): array
    {
        $events = [];

        foreach ($raw as $event) {
            if (! is_array($event)) {
                continue;
            }

            $location = $this->locationAt($locations, $event['location'] ?? null);
            $code = $this->stringOrNull($event['status_code'] ?? null);
            $description = $this->stringOrNull($event['status'] ?? null)
                ?? $this->describeEvent($code);

            $events[] = [
                'date' => $this->stringOrNull($event['date'] ?? null),
                'location' => $this->stringOrNull($location['name'] ?? null),
                'description' => $description,
                'status_code' => $code,
                'transport' => $this->stringOrNull($event['vessel'] ?? null) ? 'VESSEL' : null,
                'voyage' => $this->stringOrNull($event['voyage'] ?? null),
            ];
        }

        // Portal renders chronologically with future events pending.
        usort($events, static function (array $a, array $b): int {
            return strcmp((string) ($a['date'] ?? ''), (string) ($b['date'] ?? ''));
        });

        return $events;
    }

    /**
     * @param  list<array<string, mixed>>  $raw
     * @return array<string, string>
     */
    private function vesselFrom(array $raw): array
    {
        foreach (array_reverse($raw) as $event) {
            if (! is_array($event)) {
                continue;
            }

            $vessel = $this->stringOrNull($event['vessel'] ?? null);

            if ($vessel !== null) {
                return $this->section([
                    'name' => $vessel,
                    'voyage' => $event['voyage'] ?? null,
                ]);
            }
        }

        return [];
    }

    /**
     * @param  list<array{date?: ?string, status_code?: ?string, description?: ?string}>  $events
     */
    private function mapStatus(string $shipmentStatus, array $events): string
    {
        $normalized = strtoupper(trim($shipmentStatus));

        if (in_array($normalized, ['DELIVERED', 'COMPLETED'], true)) {
            return 'DELIVERED';
        }

        if ($normalized === '') {
            foreach ($events as $event) {
                if (in_array($event['status_code'] ?? null, ['CDD', 'VAD'], true)) {
                    return 'DISCHARGED';
                }
            }

            return 'UNKNOWN';
        }

        return self::STATUS_MAP[$normalized] ?? $this->fallbackStatus($normalized);
    }

    private function fallbackStatus(string $normalized): string
    {
        if (str_contains($normalized, 'TRANSIT') || str_contains($normalized, 'ON BOARD')) {
            return 'IN_TRANSIT';
        }

        if (str_contains($normalized, 'DELIVER')) {
            return 'DELIVERED';
        }

        if (str_contains($normalized, 'ARRIV') || str_contains($normalized, 'DISCHARG')) {
            return 'DISCHARGED';
        }

        if (str_contains($normalized, 'PENDING') || str_contains($normalized, 'BOOK')) {
            return 'PENDING';
        }

        return 'UNKNOWN';
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

    private function portalCarrierCode(string $scacOrCode): string
    {
        $upper = strtoupper(trim($scacOrCode));

        if ($upper === '') {
            return $this->carrierCode;
        }

        if (isset(self::SCAC_TO_CODE[$upper])) {
            return self::SCAC_TO_CODE[$upper];
        }

        // Already a portal code (maersk, cma_cgm…) or unknown — keep as-is lowercased.
        return strtolower($upper);
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
