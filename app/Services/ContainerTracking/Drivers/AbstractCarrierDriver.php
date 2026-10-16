<?php

declare(strict_types=1);

namespace App\Services\ContainerTracking\Drivers;

use App\Contracts\ContainerTrackerInterface;
use App\DataTransferObjects\TrackingDataDTO;
use App\Exceptions\Tracking\CarrierApiException;
use App\Exceptions\Tracking\ContainerNotFoundException;
use App\Services\ContainerTracking\Aggregators\Terminal49Aggregator;
use App\Services\ContainerTracking\ContainerPrefixResolver;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Shared execution of the three supported backends (api / scraper / aggregator).
 *
 * Carrier specific classes only declare identity and configuration; every
 * payload is read from config/tracking.php so no endpoint or credential is
 * hard-coded in PHP.
 */
abstract class AbstractCarrierDriver implements ContainerTrackerInterface
{
    protected const STATUS_DELIVERED = 'DELIVERED';

    protected const STATUS_IN_TRANSIT = 'IN_TRANSIT';

    protected const STATUS_DISCHARGED = 'DISCHARGED';

    protected const STATUS_PENDING = 'PENDING';

    protected const STATUS_UNKNOWN = 'UNKNOWN';

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(protected readonly array $config) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function make(array $config): static
    {
        return new static($config);
    }

    public function track(string $containerNumber, array $context = []): TrackingDataDTO
    {
        $container = ContainerPrefixResolver::normalize($containerNumber);

        // Real captured snapshots take precedence over any network call, so a
        // container documented by hand keeps showing its real history offline.
        $snapshot = $this->trackViaSnapshot($container);

        if ($snapshot !== null) {
            return $snapshot;
        }

        $backend = $this->backend();

        return match ($backend) {
            'api' => $this->trackViaApi($container),
            'aggregator' => $this->trackViaAggregator($container),
            'scraper' => $this->trackViaScraper($container),
            default => throw new CarrierApiException(
                sprintf(
                    'Backend não definido para %s. Informe TRACKING_%s_BACKEND em .env com api, aggregator ou scraper.',
                    $this->carrierName(),
                    strtoupper($this->carrierCode()),
                ),
                TrackingDataDTO::STATE_NOT_CONFIGURED,
            ),
        };
    }

    protected function backend(): string
    {
        $backend = $this->config['backend'] ?? config('tracking.default_backend');

        return is_string($backend) && $backend !== '' ? $backend : '';
    }

    protected function trackViaApi(string $container): TrackingDataDTO
    {
        $settings = $this->config['api'] ?? [];
        $url = $settings['url'] ?? null;

        if (! is_string($url) || $url === '') {
            throw new CarrierApiException(
                sprintf('URL da API oficial de %s não configurada.', $this->carrierName()),
                TrackingDataDTO::STATE_NOT_CONFIGURED,
            );
        }

        $response = $this->send(
            method: strtoupper((string) ($settings['method'] ?? 'GET')),
            url: $url,
            headers: (array) ($settings['headers'] ?? []),
            payload: (array) ($settings['body'] ?? []),
            query: (array) ($settings['query'] ?? []),
            timeout: (int) ($settings['timeout'] ?? config('tracking.http.timeout', 15)),
            container: $container,
        );

        return $this->mapPayload($container, (array) $response->json(), (array) ($settings['map'] ?? []), 'api');
    }

    /**
     * Loads a real tracking snapshot captured by hand from the carrier portal.
     *
     * Files live in database/tracking/{CONTAINER}.json and are keyed by the
     * container number, so a snapshot only ever applies to that container.
     */
    protected function trackViaSnapshot(string $container): ?TrackingDataDTO
    {
        $directory = (string) (config('tracking.snapshot_path') ?: database_path('tracking'));
        $path = rtrim($directory, '/').'/'.$container.'.json';

        if (! is_file($path)) {
            return null;
        }

        $payload = json_decode((string) file_get_contents($path), true);

        if (! is_array($payload)) {
            return null;
        }

        return TrackingDataDTO::fromArray($payload);
    }

    protected function trackViaAggregator(string $container): TrackingDataDTO
    {
        $aggregator = array_merge(
            (array) config('tracking.aggregator', []),
            (array) ($this->config['aggregator'] ?? []),
        );

        $aggregator['url'] = $aggregator['url'] ?? null;
        $aggregator['scac'] = $this->config['scac'] ?? strtoupper(substr($container, 0, 4));

        $driver = (string) ($aggregator['driver'] ?? 'terminal49');

        if ($driver === 'terminal49') {
            return (new Terminal49Aggregator($aggregator, $this->carrierName(), $this->carrierCode()))
                ->track($container, (string) $aggregator['scac']);
        }

        throw new CarrierApiException(
            sprintf('Agregador [%s] não suportado.', $driver),
            TrackingDataDTO::STATE_NOT_CONFIGURED,
        );
    }

    protected function trackViaScraper(string $container): TrackingDataDTO
    {
        $settings = $this->config['scraper'] ?? [];
        $url = $settings['url'] ?? null;

        if (! is_string($url) || $url === '') {
            throw new CarrierApiException(
                sprintf('Portal público de %s não configurado.', $this->carrierName()),
                TrackingDataDTO::STATE_NOT_CONFIGURED,
            );
        }

        $response = $this->send(
            method: strtoupper((string) ($settings['method'] ?? 'GET')),
            url: $url,
            headers: ['Accept' => 'text/html,application/xhtml+xml'],
            payload: (array) ($settings['body'] ?? []),
            query: [],
            timeout: (int) ($settings['timeout'] ?? 20),
            container: $container,
        );

        return $this->mapFromHtml(
            $container,
            $response->body(),
            (array) ($settings['selectors'] ?? []),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $map
     */
    protected function mapPayload(string $container, array $payload, array $map, string $provider): TrackingDataDTO
    {
        if ($payload === []) {
            throw new ContainerNotFoundException($container);
        }

        $value = fn (string $key): mixed => data_get($payload, $map[$key] ?? $key);

        $events = [];
        foreach ($this->rawEvents($payload, $map) as $raw) {
            if (! is_array($raw)) {
                continue;
            }

            $events[] = [
                'date' => $this->stringOrNull(data_get($raw, $map['events.date'] ?? 'date')),
                'location' => $this->stringOrNull(data_get($raw, $map['events.location'] ?? 'location')),
                'description' => $this->stringOrNull(data_get($raw, $map['events.description'] ?? 'description')),
                'status_code' => $this->stringOrNull(data_get($raw, $map['events.status_code'] ?? 'status_code')),
            ];
        }

        return new TrackingDataDTO(
            containerNumber: $container,
            carrierName: $this->carrierName(),
            carrierCode: $this->carrierCode(),
            status: $this->normalizeStatus($value('status')),
            state: TrackingDataDTO::STATE_OK,
            origin: $this->section($value('origin.port'), $value('origin.country'), $value('origin.departure'), ['port', 'country', 'departure']),
            destination: $this->section($value('destination.port'), $value('destination.country'), $value('destination.eta'), ['port', 'country', 'eta']),
            vessel: $this->section($value('vessel.name'), $value('vessel.voyage'), null, ['name', 'voyage']),
            events: $events,
            lastUpdated: $this->stringOrNull($value('last_updated')),
            provider: $provider,
        );
    }

    /**
     * Reads a carrier portal using CSS selectors declared in configuration.
     *
     * @param  array<string, string|null>  $selectors
     */
    protected function mapFromHtml(string $container, string $html, array $selectors): TrackingDataDTO
    {
        $crawler = new Crawler($html);

        if ($crawler->filter('body')->count() === 0) {
            throw new CarrierApiException(
                sprintf('Resposta vazia do portal de %s.', $this->carrierName()),
            );
        }

        $text = function (string $key) use ($crawler, $selectors): ?string {
            $selector = $selectors[$key] ?? null;

            if (! is_string($selector) || $selector === '') {
                return null;
            }

            try {
                $node = $crawler->filter($selector);

                if ($node->count() === 0) {
                    return null;
                }

                return trim(preg_replace('/\s+/u', ' ', $node->text('', false)) ?: '');
            } catch (\Throwable) {
                return null;
            }
        };

        $events = [];
        $rowsSelector = $selectors['events'] ?? null;

        if (is_string($rowsSelector) && $rowsSelector !== '') {
            try {
                $crawler->filter($rowsSelector)->each(function (Crawler $row) use (&$events, $selectors): void {
                    $read = function (string $key) use ($row, $selectors): ?string {
                        $selector = $selectors[$key] ?? null;

                        if (! is_string($selector) || $selector === '') {
                            return null;
                        }

                        try {
                            $node = $row->filter($selector);

                            return $node->count() === 0 ? null : trim($node->text('', false));
                        } catch (\Throwable) {
                            return null;
                        }
                    };

                    $events[] = [
                        'date' => $read('events.date'),
                        'location' => $read('events.location'),
                        'description' => $read('events.description'),
                        'status_code' => $read('events.status_code'),
                    ];
                });
            } catch (\Throwable) {
                $events = [];
            }
        }

        $events = array_values(array_filter($events, static fn (array $event): bool => array_filter($event) !== []));

        $status = $text('status');

        if ($status === null && $events === []) {
            throw new ContainerNotFoundException($container);
        }

        return new TrackingDataDTO(
            containerNumber: $container,
            carrierName: $this->carrierName(),
            carrierCode: $this->carrierCode(),
            status: $this->normalizeStatus($status),
            state: TrackingDataDTO::STATE_OK,
            origin: $this->section($text('origin.port'), $text('origin.country'), $text('origin.departure'), ['port', 'country', 'departure']),
            destination: $this->section($text('destination.port'), $text('destination.country'), $text('destination.eta'), ['port', 'country', 'eta']),
            vessel: $this->section($text('vessel.name'), $text('vessel.voyage'), null, ['name', 'voyage']),
            events: $events,
            lastUpdated: $text('last_updated'),
            provider: 'scraper',
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $map
     * @return list<mixed>
     */
    private function rawEvents(array $payload, array $map): array
    {
        $raw = data_get($payload, $map['events'] ?? 'events');

        return is_array($raw) ? array_values($raw) : [];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $query
     * @return \Illuminate\Http\Client\Response
     */
    private function send(
        string $method,
        string $url,
        array $headers,
        array $payload,
        array $query,
        int $timeout,
        string $container,
    ) {
        $url = str_replace('{container}', rawurlencode($container), $url);
        $payload = $this->fill($payload, $container);
        $query = $this->fill($query, $container);

        $pending = Http::withHeaders($headers)
            ->timeout($timeout)
            ->connectTimeout((int) config('tracking.http.connect_timeout', 5));

        try {
            return match ($method) {
                'POST' => $pending->post($url, $payload),
                'PUT' => $pending->put($url, $payload),
                'PATCH' => $pending->patch($url, $payload),
                default => $query === []
                    ? $pending->get($url)
                    : $pending->get($url, $query),
            };
        } catch (ConnectionException $exception) {
            throw new CarrierApiException(
                sprintf('Falha de conexão com %s: %s', $this->carrierName(), $exception->getMessage()),
                TrackingDataDTO::STATE_ERROR,
                $exception,
            );
        } catch (RequestException $exception) {
            throw new CarrierApiException(
                sprintf('%s respondeu HTTP %s.', $this->carrierName(), $exception->response?->status() ?? '?'),
                TrackingDataDTO::STATE_ERROR,
                $exception,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function fill(array $values, string $container): array
    {
        $filled = [];

        foreach ($values as $key => $value) {
            $filled[$key] = is_array($value)
                ? $this->fill($value, $container)
                : (is_string($value) ? str_replace('{container}', rawurlencode($container), $value) : $value);
        }

        return $filled;
    }

    protected function normalizeStatus(mixed $value): string
    {
        if (! is_scalar($value)) {
            return self::STATUS_UNKNOWN;
        }

        $status = strtoupper(trim((string) $value));

        if ($status === '') {
            return self::STATUS_UNKNOWN;
        }

        return match (true) {
            str_contains($status, 'DELIVER'), str_contains($status, 'POD'), str_contains($status, 'CLOSED') => self::STATUS_DELIVERED,
            str_contains($status, 'DISCHARG'), str_contains($status, 'CUSTOMS') => self::STATUS_DISCHARGED,
            str_contains($status, 'TRANSIT'), str_contains($status, 'ON BOARD'), str_contains($status, 'SAILED'), str_contains($status, 'DEPART') => self::STATUS_IN_TRANSIT,
            str_contains($status, 'PENDING'), str_contains($status, 'BOOKED'), str_contains($status, 'CREATED') => self::STATUS_PENDING,
            in_array($status, [self::STATUS_DELIVERED, self::STATUS_IN_TRANSIT, self::STATUS_DISCHARGED, self::STATUS_PENDING], true) => $status,
            default => self::STATUS_UNKNOWN,
        };
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, string>
     */
    private function section(mixed ...$values): array
    {
        $keys = array_pop($values);
        $section = [];

        foreach ($keys as $index => $key) {
            $value = $this->stringOrNull($values[$index] ?? null);

            if ($value !== null) {
                $section[$key] = $value;
            }
        }

        return $section;
    }

    protected function stringOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return is_scalar($value) ? trim((string) $value) : null;
    }
}
