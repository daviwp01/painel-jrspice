<?php

declare(strict_types=1);

namespace App\Services\ContainerTracking\Tracktainer;

use App\DataTransferObjects\TrackingDataDTO;
use App\Exceptions\Tracking\CarrierApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * HTTP layer for the Tracktainer API (https://docs.tracktainer.com).
 *
 * Owns auth, transport and RFC 9457 problem+json error mapping only —
 * callers (aggregator, arrival sync) never build requests themselves.
 *
 *   GET  /ocean/shipments?filter[...]   list (free)
 *   POST /ocean/shipments               create (1 credit; 409 = existing, free)
 *   GET  /ocean/shipments/{id}          detail (free)
 */
final class TracktainerClient
{
    private readonly string $base;

    private readonly string $apiKey;

    private readonly int $timeout;

    /**
     * @param  array<string, mixed>  $config  the `tracking.aggregator` block
     */
    public function __construct(array $config)
    {
        $this->base = rtrim((string) ($config['url'] ?? ''), '/');
        $this->apiKey = (string) ($config['api_key'] ?? '');
        $this->timeout = (int) ($config['timeout'] ?? config('tracking.http.timeout', 15));
    }

    public static function fromConfig(): self
    {
        return new self((array) config('tracking.aggregator', []));
    }

    public function isConfigured(): bool
    {
        return $this->base !== '' && $this->apiKey !== '';
    }

    /**
     * Shipment id already tracking this number, or null when unknown.
     * Free; the list includes COMPLETED shipments.
     */
    public function findShipmentId(string $container): ?string
    {
        $rows = $this->listShipments(['filter[shipment_number]' => $container]);
        $first = $rows['data'][0]['id'] ?? null;

        return is_string($first) && $first !== '' ? $first : null;
    }

    /**
     * Starts tracking a container. Costs 1 credit; a 409 returns the existing id
     * without charging (already tracked or in flight).
     */
    public function createShipment(string $container, string $numberType = 'CONTAINER'): string
    {
        $response = $this->request(
            'POST',
            $this->base.'/ocean/shipments',
            [
                'data' => [
                    'type' => 'ocean-shipments',
                    'attributes' => [
                        'shipment_number' => $container,
                        'shipment_number_type' => $numberType,
                    ],
                ],
            ],
            allowConflict: true,
        );

        if ($response->status() === 409) {
            $existing = data_get($response->json(), 'existing_id');

            if (is_string($existing) && $existing !== '') {
                return $existing;
            }

            throw new CarrierApiException(
                'Tracktainer devolveu 409 sem existing_id.',
                TrackingDataDTO::STATE_ERROR,
            );
        }

        $id = data_get($response->json(), 'data.id');

        if (! is_string($id) || $id === '') {
            throw new CarrierApiException(
                'Tracktainer não devolveu um identificador de shipment.',
                TrackingDataDTO::STATE_ERROR,
            );
        }

        return $id;
    }

    /**
     * Full shipment payload (free): ports, dates, containers, movements.
     *
     * @return array<string, mixed>
     */
    public function getShipment(string $shipmentId): array
    {
        $response = $this->request('GET', $this->base.'/ocean/shipments/'.$shipmentId);

        return (array) $response->json();
    }

    /**
     * List page of shipments (free). Keys are raw query pairs as sent by the caller.
     *
     * @param  array<string, string>  $filters
     * @return array{data: list<array<string, mixed>>, meta: array<string, mixed>, links: array<string, mixed>}
     */
    public function listShipments(array $filters, int $page = 1): array
    {
        $query = array_merge($filters, ['page' => ['number' => $page]]);

        $response = $this->request(
            'GET',
            $this->base.'/ocean/shipments?'.http_build_query($query),
        );

        $json = (array) $response->json();

        return [
            'data' => array_values((array) ($json['data'] ?? [])),
            'meta' => (array) ($json['meta'] ?? []),
            'links' => (array) ($json['links'] ?? []),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $body
     */
    private function request(string $method, string $url, ?array $body = null, bool $allowConflict = false): Response
    {
        $this->assertConfigured();

        $client = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->apiKey,
            'Accept' => 'application/vnd.api+json',
            'Content-Type' => 'application/json',
        ])
            ->timeout($this->timeout)
            ->connectTimeout((int) config('tracking.http.connect_timeout', 5));

        try {
            $response = match (strtoupper($method)) {
                'POST' => $client->withBody((string) json_encode($body, JSON_UNESCAPED_UNICODE), 'application/json')->post($url),
                default => $client->get($url),
            };
        } catch (ConnectionException $exception) {
            throw new CarrierApiException(
                sprintf('Falha de conexão com o Tracktainer: %s', $exception->getMessage()),
                TrackingDataDTO::STATE_ERROR,
                $exception,
            );
        }

        if ($response->status() === 401) {
            throw new CarrierApiException(
                'Tracktainer rejeitou a chave de API. Verifique TRACKING_AGGREGATOR_KEY em .env.',
                TrackingDataDTO::STATE_NOT_CONFIGURED,
            );
        }

        if ($response->status() === 402) {
            $credits = data_get($response->json(), 'credits_remaining');

            throw new CarrierApiException(
                sprintf(
                    'Tracktainer sem créditos para criar o tracking%s.',
                    $credits !== null ? sprintf(' (restam %s)', $credits) : '',
                ),
                TrackingDataDTO::STATE_ERROR,
            );
        }

        if ($response->status() === 409 && $allowConflict) {
            return $response;
        }

        if ($response->status() === 429) {
            throw new CarrierApiException(
                'Tracktainer rate limit (429) atingido. Tente novamente em instantes.',
                TrackingDataDTO::STATE_ERROR,
            );
        }

        if ($response->failed()) {
            $detail = data_get($response->json(), 'detail')
                ?? data_get($response->json(), 'title')
                ?? $response->reason();

            throw new CarrierApiException(
                sprintf('Tracktainer respondeu HTTP %d: %s', $response->status(), is_string($detail) ? $detail : 'erro desconhecido'),
                TrackingDataDTO::STATE_ERROR,
            );
        }

        return $response;
    }

    private function assertConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new CarrierApiException(
                'Agregador não configurado. Informe TRACKING_AGGREGATOR_URL e TRACKING_AGGREGATOR_KEY (Tracktainer) em .env.',
                TrackingDataDTO::STATE_NOT_CONFIGURED,
            );
        }
    }
}
