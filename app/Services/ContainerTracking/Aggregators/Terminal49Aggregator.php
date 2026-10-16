<?php

declare(strict_types=1);

namespace App\Services\ContainerTracking\Aggregators;

use App\DataTransferObjects\TrackingDataDTO;
use App\Exceptions\Tracking\CarrierApiException;
use App\Exceptions\Tracking\ContainerNotFoundException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Terminal49 integration (https://terminal49.com/docs).
 *
 * Contract validated against the live API on 2026-10-16:
 *   - JSON:API (`application/vnd.api+json`), auth header `Authorization: Token <key>`
 *   - POST /v2/tracking_requests  {data:{type:"tracking_request",attributes:{request_number,request_type,scac}}}
 *   - GET  /v2/tracking_requests/{id}      -> relationships.container / relationships.shipment
 *   - GET  /v2/containers/{id}             -> status, ports, eta
 *   - GET  /v2/containers/{id}/transport_events -> normalized milestones
 *
 * Only the field paths are configurable (see config/tracking.php -> aggregator.map),
 * so schema drift is fixed in configuration rather than in PHP.
 */
final class Terminal49Aggregator
{
    /**
     * @param  array<string, mixed>  $config  the `tracking.aggregator` block
     */
    public function __construct(
        private readonly array $config,
        private readonly string $carrierName,
        private readonly string $carrierCode,
    ) {}

    public function track(string $container, string $scac): TrackingDataDTO
    {
        $base = rtrim((string) ($this->config['url'] ?? ''), '/');
        $headers = (array) ($this->config['headers'] ?? []);
        $timeout = (int) ($this->config['timeout'] ?? 20);
        $map = (array) ($this->config['map'] ?? []);
        $create = (array) ($this->config['create'] ?? []);

        if ($base === '') {
            throw new CarrierApiException(
                'Agregador não configurado. Informe TRACKING_AGGREGATOR_URL em .env.',
                TrackingDataDTO::STATE_NOT_CONFIGURED,
            );
        }

        // 1) register the container so Terminal49 starts polling the carriers
        $created = $this->request(
            'POST',
            $base.(string) ($create['path'] ?? '/tracking_requests'),
            $headers,
            $timeout,
            [
                'data' => [
                    'type' => (string) ($create['type'] ?? 'tracking_request'),
                    'attributes' => [
                        'request_number' => $container,
                        'request_type' => (string) ($create['request_type'] ?? 'container'),
                        'scac' => $scac,
                    ],
                ],
            ],
        );

        $requestId = data_get($created->json(), 'data.id');

        if ($requestId === null) {
            throw new CarrierApiException(
                sprintf('%s não devolveu um identificador de tracking request.', $this->carrierName()),
            );
        }

        // 2) read back the request to discover the container/shipment it resolved
        $detail = $this->request('GET', $base.'/tracking_requests/'.$requestId, $headers, $timeout)->json();

        $containerId = data_get($detail, 'data.relationships.container.data.id');
        $shipmentId = data_get($detail, 'data.relationships.shipment.data.id');

        if ($containerId === null && $shipmentId === null) {
            throw new ContainerNotFoundException($container);
        }

        // 3) container detail + normalized transport events
        $containerPayload = null;
        $events = [];

        if ($containerId !== null) {
            $containerPayload = $this->request('GET', $base.'/containers/'.$containerId, $headers, $timeout)->json();

            $eventsPayload = $this->request(
                'GET',
                $base.'/containers/'.$containerId.'/transport_events',
                $headers,
                $timeout,
            )->json();

            $events = $this->mapEvents($eventsPayload, $map);
        }

        $shipmentPayload = $shipmentId !== null
            ? $this->request('GET', $base.'/shipments/'.$shipmentId, $headers, $timeout)->json()
            : null;

        return $this->toDto($container, $containerPayload, $shipmentPayload, $events);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array{date: ?string, location: ?string, description: ?string, status_code: ?string}>
     */
    private function mapEvents(mixed $payload, array $map): array
    {
        $rows = data_get($payload ?? [], $map['events'] ?? 'data');

        if (! is_array($rows)) {
            return [];
        }

        $events = [];

        foreach (array_values($rows) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $attributes = (array) data_get($row, 'attributes', $row);

            $events[] = [
                'date' => $this->stringOrNull($attributes['timestamp'] ?? $attributes['created_at'] ?? null),
                'location' => $this->stringOrNull($attributes['location_locode'] ?? $attributes['location_name'] ?? null),
                'description' => $this->humanize($this->stringOrNull($attributes['event'] ?? null)),
                'status_code' => $this->stringOrNull($attributes['event'] ?? null),
            ];
        }

        return $events;
    }

    /**
     * @param  array<string, mixed>|null  $container
     * @param  array<string, mixed>|null  $shipment
     * @param  list<array{date: ?string, location: ?string, description: ?string, status_code: ?string}>  $events
     */
    private function toDto(string $container, ?array $containerPayload, ?array $shipmentPayload, array $events): TrackingDataDTO
    {
        $shipmentAttributes = (array) data_get($shipmentPayload ?? [], 'data.attributes', []);
        $containerAttributes = (array) data_get($containerPayload ?? [], 'data.attributes', []);

        $status = $shipmentAttributes['status'] ?? $containerAttributes['status'] ?? null;

        $vessel = null;
        foreach (array_reverse($events) as $event) {
            if (($event['status_code'] ?? null) !== null) {
                $vessel = null; // vessel name is not part of the transport_events payload
                break;
            }
        }

        return new TrackingDataDTO(
            containerNumber: $container,
            carrierName: $this->carrierName,
            carrierCode: $this->carrierCode,
            status: $this->normalizeStatus($status),
            state: TrackingDataDTO::STATE_OK,
            origin: $this->section([
                'port' => $shipmentAttributes['port_of_lading_name'] ?? null,
                'country' => $shipmentAttributes['port_of_lading_country'] ?? null,
                'departure' => $shipmentAttributes['pod_original_ata_at'] ?? $shipmentAttributes['atd_at'] ?? null,
            ]),
            destination: $this->section([
                'port' => $shipmentAttributes['port_of_discharge_name'] ?? null,
                'country' => $shipmentAttributes['port_of_discharge_country'] ?? null,
                'eta' => $shipmentAttributes['pod_eta_at'] ?? null,
            ]),
            vessel: $this->section($vessel ?? []),
            events: $events,
            lastUpdated: $this->stringOrNull(
                $containerPayload !== null
                    ? data_get($containerPayload, 'data.attributes.updated_at')
                    : data_get($shipmentPayload ?? [], 'data.attributes.updated_at'),
            ),
            provider: 'terminal49',
        );
    }

    /**
     * @param  array<string, string|null>  $values
     * @return array<string, string>
     */
    private function section(array $values): array
    {
        $section = [];

        foreach ($values as $key => $value) {
            if ($value !== null && $value !== '') {
                $section[$key] = (string) $value;
            }
        }

        return $section;
    }

    private function normalizeStatus(mixed $value): string
    {
        $status = strtoupper(trim((string) ($value ?? '')));

        if ($status === '') {
            return 'UNKNOWN';
        }

        return match (true) {
            str_contains($status, 'DELIVER'), str_contains($status, 'POD') => 'DELIVERED',
            str_contains($status, 'DISCHARG'), str_contains($status, 'CUSTOMS') => 'DISCHARGED',
            str_contains($status, 'TRANSIT'), str_contains($status, 'ON BOARD'), str_contains($status, 'LOAD'), str_contains($status, 'DEPART') => 'IN_TRANSIT',
            str_contains($status, 'PENDING'), str_contains($status, 'BOOK'), str_contains($status, 'CREATED') => 'PENDING',
            default => 'UNKNOWN',
        };
    }

    private function humanize(?string $event): ?string
    {
        if ($event === null) {
            return null;
        }

        return ucwords(str_replace(['.', '_', '-'], ' ', strtolower($event)));
    }

    private function stringOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '' || is_array($value)) {
            return null;
        }

        return trim((string) $value);
    }

    /**
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>|null  $body
     */
    private function request(string $method, string $url, array $headers, int $timeout, ?array $body = null): Response
    {
        $client = Http::withHeaders($headers)
            ->timeout($timeout)
            ->connectTimeout((int) config('tracking.http.connect_timeout', 5));

        try {
            $response = match (strtoupper($method)) {
                'POST' => $body === null
                    ? $client->post($url)
                    : $client->withBody(json_encode($body, JSON_UNESCAPED_UNICODE), 'application/vnd.api+json')->post($url),
                default => $client->get($url),
            };
        } catch (ConnectionException $exception) {
            throw new CarrierApiException(
                sprintf('Falha de conexão com o Terminal49: %s', $exception->getMessage()),
                TrackingDataDTO::STATE_ERROR,
                $exception,
            );
        }

        if ($response->status() === 401) {
            throw new CarrierApiException(
                'Terminal49 autenticou a chave, mas bloqueou a leitura. Ative o trial (Developers → Start trial) ou um plano pago em app.terminal49.com/settings/billing.',
                TrackingDataDTO::STATE_NOT_CONFIGURED,
            );
        }

        if ($response->failed()) {
            $detail = data_get($response->json(), 'errors.0.detail') ?? $response->reason();

            throw new CarrierApiException(
                sprintf('Terminal49 respondeu HTTP %d: %s', $response->status(), is_string($detail) ? $detail : 'erro desconhecido'),
                TrackingDataDTO::STATE_ERROR,
            );
        }

        return $response;
    }
}
