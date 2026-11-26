<?php

declare(strict_types=1);

namespace App\Services\ContainerTracking\TimeToCargo;

use App\DataTransferObjects\TrackingDataDTO;
use App\Exceptions\Tracking\CarrierApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * HTTP layer for the TimeToCargo API (https://timetocargo.com/docs/tracking).
 *
 * Owns auth (api_key query param), transport and status-code mapping only —
 * the aggregator never builds requests itself.
 *
 *   GET /v2/tracking?api_key=&company=&value=&type=  single reference
 */
final class TimeToCargoClient
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
     * Single-number tracking. Synchronous: the result arrives in the same
     * HTTP response under `data` when `status === 'OK'`.
     *
     * `type` null omits the parameter so the API auto-detects container/bl/bk.
     *
     * @return array<string, mixed> full decoded body
     */
    public function track(string $value, ?string $type = 'container', string $company = 'AUTO'): array
    {
        $this->assertConfigured();

        $query = [
            'api_key' => $this->apiKey,
            'company' => $company,
            'value' => $value,
        ];

        if ($type !== null && $type !== '' && strtoupper($type) !== 'AUTO') {
            $query['type'] = $type;
        }

        $response = $this->request('GET', $this->base.'/tracking', $query);

        return (array) $response->json();
    }

    /**
     * Page of tracked shipments (list is free relative to single tracking).
     * Nested tracking payload lives under tracked_item.data.data when present.
     *
     * @return array{rows: list<array<string, mixed>>, has_more: bool}
     */
    public function listShipments(int $page = 1, int $limit = 100, string $status = 'ACTIVE'): array
    {
        $this->assertConfigured();

        $response = $this->request('GET', $this->base.'/shipments', [
            'api_key' => $this->apiKey,
            'page' => $page,
            'limit' => $limit,
            'status' => $status,
        ]);

        $json = (array) $response->json();
        $data = (array) ($json['data'] ?? []);
        $rows = array_values((array) ($data['data'] ?? []));
        $meta = (array) ($data['meta'] ?? []);
        $lastPage = (int) ($meta['last_page'] ?? $meta['total_pages'] ?? $page);

        return [
            'rows' => $rows,
            'has_more' => $page < $lastPage || count($rows) >= $limit,
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function request(string $method, string $url, array $query = []): Response
    {
        $this->assertConfigured();

        $client = Http::withHeaders(['Accept' => 'application/json'])
            ->timeout($this->timeout)
            ->connectTimeout((int) config('tracking.http.connect_timeout', 5));

        try {
            $response = match (strtoupper($method)) {
                'POST' => $client->post($url, $query),
                default => $client->get($url, $query),
            };
        } catch (ConnectionException $exception) {
            throw new CarrierApiException(
                sprintf('Falha de conexão com o TimeToCargo: %s', $exception->getMessage()),
                TrackingDataDTO::STATE_ERROR,
                $exception,
            );
        }

        if ($response->status() === 401 || $response->status() === 403) {
            throw new CarrierApiException(
                'TimeToCargo rejeitou a chave de API. Verifique TRACKING_AGGREGATOR_KEY em .env.',
                TrackingDataDTO::STATE_NOT_CONFIGURED,
            );
        }

        if ($response->status() === 429) {
            throw new CarrierApiException(
                'TimeToCargo rate limit (429) atingido. Tente novamente em instantes.',
                TrackingDataDTO::STATE_ERROR,
            );
        }

        if ($response->failed()) {
            $detail = data_get($response->json(), 'status_description')
                ?? data_get($response->json(), 'status')
                ?? $response->reason();

            throw new CarrierApiException(
                sprintf('TimeToCargo respondeu HTTP %d: %s', $response->status(), is_string($detail) ? $detail : 'erro desconhecido'),
                TrackingDataDTO::STATE_ERROR,
            );
        }

        return $response;
    }

    private function assertConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new CarrierApiException(
                'Agregador não configurado. Informe TRACKING_AGGREGATOR_URL e TRACKING_AGGREGATOR_KEY (TimeToCargo) em .env.',
                TrackingDataDTO::STATE_NOT_CONFIGURED,
            );
        }
    }
}
