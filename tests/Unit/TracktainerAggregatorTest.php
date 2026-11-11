<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DataTransferObjects\TrackingDataDTO;
use App\Exceptions\Tracking\CarrierApiException;
use App\Exceptions\Tracking\ContainerNotFoundException;
use App\Services\ContainerTracking\Aggregators\TracktainerAggregator;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TracktainerAggregatorTest extends TestCase
{
    private const BASE = 'https://api.tracktainer.com/v1';

    private function aggregator(): TracktainerAggregator
    {
        return TracktainerAggregator::make(
            [
                'url' => self::BASE,
                'api_key' => 'test-key',
                'timeout' => 5,
            ],
            'Maersk',
            'maersk',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function detailPayload(string $status = 'IN_TRANSIT'): array
    {
        return [
            'data' => [
                'type' => 'ocean-shipments',
                'id' => '683e563c-97b5-4f62-94a0-d2de419e21f2',
                'attributes' => [
                    'shipment_status' => $status,
                    'shipment_number' => 'MAEU1234567',
                    'shipment_number_type' => 'CONTAINER',
                    'carrier' => ['name' => 'Maersk', 'scac' => 'MAEU'],
                    'port_of_loading' => [
                        'name' => 'Shanghai',
                        'code' => 'CNSHA',
                        'country' => ['name' => 'China', 'code' => 'CN'],
                    ],
                    'port_of_discharge' => [
                        'name' => 'Rotterdam',
                        'code' => 'NLRTM',
                        'country' => ['name' => 'Netherlands', 'code' => 'NL'],
                    ],
                    'origin' => null,
                    'destination' => null,
                    'etd' => '2026-08-27',
                    'eta' => '2026-11-08',
                    'delay_days' => 0,
                    'tracking_active' => true,
                    'last_checked_at' => '2026-11-11T00:59:27Z',
                    'updated_at' => '2026-11-11T00:59:27Z',
                    'failure_reason' => null,
                    'containers' => [
                        [
                            'container_number' => 'MAEU1234567',
                            'movements' => [
                                [
                                    'event' => 'LOAD',
                                    'classifier' => 'ACT',
                                    'date' => '2026-08-27',
                                    'location' => ['name' => 'Shanghai', 'code' => 'CNSHA'],
                                    'vessel' => ['name' => 'SEASPAN EMISSARY', 'imo' => 9407158],
                                    'voyage' => '004E',
                                    'transport_mode' => 'VESSEL',
                                ],
                                [
                                    'event' => 'ARRI',
                                    'classifier' => 'ACT',
                                    'date' => '2026-09-23',
                                    'location' => ['name' => 'Tanger Med', 'code' => 'MAPTM'],
                                    'vessel' => null,
                                    'voyage' => null,
                                    'transport_mode' => 'VESSEL',
                                ],
                                [
                                    'event' => 'DISCH',
                                    'classifier' => 'ACT',
                                    'date' => '2026-09-25',
                                    'location' => ['name' => 'Rotterdam', 'code' => 'NLRTM'],
                                    'vessel' => null,
                                    'voyage' => null,
                                    'transport_mode' => 'VESSEL',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function listResponse(?string $id): array
    {
        return [
            'data' => $id === null ? [] : [
                ['type' => 'ocean-shipments', 'id' => $id, 'attributes' => ['shipment_number' => 'MAEU1234567']],
            ],
            'meta' => ['page' => ['size' => 25]],
        ];
    }

    public function test_maps_existing_shipment_to_normalized_dto(): void
    {
        Http::fake([
            self::BASE.'/ocean/shipments?*' => Http::response($this->listResponse('abc-123')),
            self::BASE.'/ocean/shipments/abc-123' => Http::response($this->detailPayload()),
        ]);

        $dto = $this->aggregator()->track('MAEU1234567');

        $this->assertSame(TrackingDataDTO::STATE_OK, $dto->state);
        $this->assertSame('IN_TRANSIT', $dto->status);
        $this->assertSame('tracktainer', $dto->provider);
        $this->assertSame('Shanghai', $dto->origin['port']);
        $this->assertSame('China', $dto->origin['country']);
        $this->assertSame('2026-08-27', $dto->origin['departure']);
        $this->assertSame('Rotterdam', $dto->destination['port']);
        $this->assertSame('2026-11-08', $dto->destination['eta']);
        $this->assertSame('SEASPAN EMISSARY', $dto->vessel['name']);
        $this->assertSame('004E', $dto->vessel['voyage']);
        $this->assertCount(3, $dto->events);
        $this->assertSame('Loaded', $dto->events[0]['description']);
        $this->assertSame('LOAD', $dto->events[0]['status_code']);
        $this->assertSame('Shanghai', $dto->events[0]['location']);
        $this->assertSame('VESSEL', $dto->events[0]['transport']);
        $this->assertSame('2026-11-11T00:59:27Z', $dto->lastUpdated);
    }

    public function test_creates_shipment_when_number_is_unknown(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, 'shipment_number')) {
                return Http::response($this->listResponse(null));
            }

            if ($request->method() === 'POST') {
                return Http::response([
                    'data' => ['id' => 'new-1', 'attributes' => ['shipment_status' => 'PENDING']],
                    'meta' => ['carrier_detected' => true],
                ], 200);
            }

            if (str_contains($url, '/ocean/shipments/new-1')) {
                return Http::response($this->detailPayload('PENDING'));
            }

            return Http::response(['title' => 'Unexpected request', 'url' => $url], 500);
        });

        $dto = $this->aggregator()->track('MAEU1234567');

        Http::assertSentCount(3);
        $this->assertSame(TrackingDataDTO::STATE_OK, $dto->state);
        $this->assertSame('PENDING', $dto->status);
    }

    public function test_uses_existing_id_when_create_returns_409(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, 'shipment_number')) {
                return Http::response($this->listResponse(null));
            }

            if ($request->method() === 'POST') {
                return Http::response([
                    'type' => 'https://docs.tracktainer.com/errors/duplicate-tracking-request',
                    'title' => 'Duplicate shipment',
                    'status' => 409,
                    'detail' => 'already tracked',
                    'existing_id' => 'dup-42',
                ], 409);
            }

            if (str_contains($url, '/ocean/shipments/dup-42')) {
                return Http::response($this->detailPayload());
            }

            return Http::response(['title' => 'Unexpected', 'url' => $url], 500);
        });

        $dto = $this->aggregator()->track('MAEU1234567');

        $this->assertSame(TrackingDataDTO::STATE_OK, $dto->state);
        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'dup-42'));
    }

    public function test_not_found_failure_reason_throws_container_not_found(): void
    {
        $payload = $this->detailPayload();
        $payload['data']['attributes']['failure_reason'] = 'NOT_FOUND';

        Http::fake([
            self::BASE.'/ocean/shipments?*' => Http::response($this->listResponse('x')),
            self::BASE.'/ocean/shipments/x' => Http::response($payload),
        ]);

        $this->expectException(ContainerNotFoundException::class);
        $this->aggregator()->track('MAEU1234567');
    }

    public function test_unauthorized_key_is_not_configured_state(): void
    {
        Http::fake([
            self::BASE.'/ocean/shipments?*' => Http::response(['title' => 'Unauthenticated'], 401),
        ]);

        try {
            $this->aggregator()->track('MAEU1234567');
            $this->fail('expected CarrierApiException');
        } catch (CarrierApiException $exception) {
            $this->assertSame(TrackingDataDTO::STATE_NOT_CONFIGURED, $exception->state);
        }
    }

    public function test_insufficient_credits_surfaces_as_error_state(): void
    {
        Http::fake([
            self::BASE.'/ocean/shipments?*' => Http::response($this->listResponse(null)),
            self::BASE.'/ocean/shipments' => Http::response([
                'title' => 'Insufficient credits',
                'status' => 402,
                'detail' => 'requires 1 credit',
                'credits_remaining' => 0,
            ], 402),
        ]);

        try {
            $this->aggregator()->track('MAEU1234567');
            $this->fail('expected CarrierApiException');
        } catch (CarrierApiException $exception) {
            $this->assertSame(TrackingDataDTO::STATE_ERROR, $exception->state);
            $this->assertStringContainsString('créditos', $exception->getMessage());
        }
    }

    public function test_missing_credentials_are_not_configured(): void
    {
        $aggregator = TracktainerAggregator::make(['url' => '', 'api_key' => ''], 'Maersk', 'maersk');

        try {
            $aggregator->track('MAEU1234567');
            $this->fail('expected CarrierApiException');
        } catch (CarrierApiException $exception) {
            $this->assertSame(TrackingDataDTO::STATE_NOT_CONFIGURED, $exception->state);
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function statusProvider(): iterable
    {
        yield 'pending' => ['PENDING', 'PENDING'];
        yield 'planned maps to pending' => ['PLANNED', 'PENDING'];
        yield 'in transit' => ['IN_TRANSIT', 'IN_TRANSIT'];
        yield 'arrived maps to discharged' => ['ARRIVED', 'DISCHARGED'];
        yield 'completed maps to delivered' => ['COMPLETED', 'DELIVERED'];
        yield 'cancelled maps to unknown' => ['CANCELLED', 'UNKNOWN'];
        yield 'unknown status' => ['SOMETHING_ELSE', 'UNKNOWN'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('statusProvider')]
    public function test_shipment_status_maps_to_portal_labels(string $apiStatus, string $expected): void
    {
        Http::fake([
            self::BASE.'/ocean/shipments?*' => Http::response($this->listResponse('s1')),
            self::BASE.'/ocean/shipments/s1' => Http::response($this->detailPayload($apiStatus)),
        ]);

        $dto = $this->aggregator()->track('MAEU1234567');

        $this->assertSame($expected, $dto->status);
    }

    public function test_completed_with_empty_return_event_stays_delivered(): void
    {
        $payload = $this->detailPayload('COMPLETED');
        $payload['data']['attributes']['containers'][0]['movements'][] = [
            'event' => 'EMRT',
            'classifier' => 'ACT',
            'date' => '2026-11-10',
            'location' => ['name' => 'Arica', 'code' => 'CLARI'],
            'vessel' => null,
            'voyage' => null,
            'transport_mode' => 'TRUCK',
        ];

        Http::fake([
            self::BASE.'/ocean/shipments?*' => Http::response($this->listResponse('s2')),
            self::BASE.'/ocean/shipments/s2' => Http::response($payload),
        ]);

        $dto = $this->aggregator()->track('MAEU1234567');

        $this->assertSame('DELIVERED', $dto->status);
        $events = $dto->events;
        $last = $events[array_key_last($events)];
        $this->assertSame('Empty returned', $last['description']);
    }
}
