<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

/**
 * Normalized output of every carrier driver.
 *
 * Every driver must be able to produce this exact structure so the front-end
 * never has to care which carrier answered.
 */
final readonly class TrackingDataDTO
{
    public const STATE_OK = 'ok';

    public const STATE_NOT_CONFIGURED = 'not_configured';

    public const STATE_NOT_FOUND = 'not_found';

    public const STATE_UNSUPPORTED = 'unsupported';

    public const STATE_ERROR = 'error';

    /**
     * @param  array{port?: string, country?: string, departure?: string}  $origin
     * @param  array{port?: string, country?: string, eta?: string}  $destination
     * @param  array{name?: string, voyage?: string}  $vessel
     * @param  list<array{date?: string, location?: string, description?: string, status_code?: string, transport?: string, voyage?: string}>  $events
     */
    public function __construct(
        public string $containerNumber,
        public string $carrierName,
        public string $carrierCode,
        public string $status,
        public string $state,
        public array $origin = [],
        public array $destination = [],
        public array $vessel = [],
        public array $events = [],
        public ?string $lastUpdated = null,
        public ?string $message = null,
        public ?string $provider = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            containerNumber: (string) ($payload['container_number'] ?? ''),
            carrierName: (string) ($payload['carrier_name'] ?? ''),
            carrierCode: (string) ($payload['carrier_code'] ?? ''),
            status: (string) ($payload['status'] ?? ''),
            state: (string) ($payload['state'] ?? self::STATE_OK),
            origin: self::stringMap($payload['origin'] ?? []),
            destination: self::stringMap($payload['destination'] ?? []),
            vessel: self::stringMap($payload['vessel'] ?? []),
            events: self::eventList($payload['events'] ?? []),
            lastUpdated: isset($payload['last_updated']) ? (string) $payload['last_updated'] : null,
            message: isset($payload['message']) ? (string) $payload['message'] : null,
            provider: isset($payload['provider']) ? (string) $payload['provider'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'container_number' => $this->containerNumber,
            'carrier_name' => $this->carrierName,
            'carrier_code' => $this->carrierCode,
            'status' => $this->status,
            'state' => $this->state,
            'origin' => $this->origin,
            'destination' => $this->destination,
            'vessel' => $this->vessel,
            'events' => $this->events,
            'last_updated' => $this->lastUpdated,
            'message' => $this->message,
            'provider' => $this->provider,
        ];
    }

    public function isSuccess(): bool
    {
        return $this->state === self::STATE_OK;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, string>
     */
    private static function stringMap(array $values): array
    {
        $normalized = [];

        foreach ($values as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $normalized[(string) $key] = is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return $normalized;
    }

    /**
     * @return list<array{date?: string, location?: string, description?: string, status_code?: string, transport?: string, voyage?: string}>
     */
    private static function eventList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $events = [];

        foreach ($values as $value) {
            if (! is_array($value)) {
                continue;
            }

            $events[] = [
                'date' => isset($value['date']) ? (string) $value['date'] : null,
                'location' => isset($value['location']) ? (string) $value['location'] : null,
                'description' => isset($value['description']) ? (string) $value['description'] : null,
                'status_code' => isset($value['status_code']) ? (string) $value['status_code'] : null,
                'transport' => isset($value['transport']) ? (string) $value['transport'] : null,
                'voyage' => isset($value['voyage']) ? (string) $value['voyage'] : null,
            ];
        }

        return $events;
    }
}
