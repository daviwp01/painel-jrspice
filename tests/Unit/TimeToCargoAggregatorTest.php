<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DataTransferObjects\TrackingDataDTO;
use App\Exceptions\Tracking\CarrierApiException;
use App\Exceptions\Tracking\ContainerNotFoundException;
use App\Services\ContainerTracking\Aggregators\TimeToCargoAggregator;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TimeToCargoAggregatorTest extends TestCase
{
    private const BASE = 'https://tracking.timetocargo.com/v2';

    private function aggregator(): TimeToCargoAggregator
    {
        return TimeToCargoAggregator::make(
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
     * Shape validated against the live demo endpoint on 2026-11-26.
     *
     * @return array<string, mixed>
     */
    private function okPayload(string $shipmentStatus = 'IN_TRANSIT'): array
    {
        return [
            'success' => true,
            'status' => 'OK',
            'status_description' => 'Information found',
            'data' => [
                'summary' => [
                    'origin' => ['location' => 0, 'terminal' => 0, 'date' => null],
                    'pol' => ['location' => 0, 'terminal' => 2, 'date' => '2026-07-21T01:29:00'],
                    'pod' => ['location' => 2, 'terminal' => 3, 'date' => '2026-08-10T06:53:00'],
                    'destination' => ['location' => 1, 'terminal' => 1, 'date' => null],
                    'company' => [
                        'name' => 'COSCO',
                        'full_name' => 'China Ocean Shipping Group Company',
                        'scacs' => ['COSU'],
                    ],
                ],
                'locations' => [
                    ['id' => 0, 'name' => 'Qingdao', 'country' => 'China'],
                    ['id' => 1, 'name' => 'Los Angeles', 'country' => 'United States of America'],
                    ['id' => 2, 'name' => 'Long Beach', 'country' => 'United States of America'],
                ],
                'containers' => [
                    [
                        'number' => 'CSQU3054383',
                        'events' => [
                            [
                                'location' => 2,
                                'status' => 'Vessel arrival at final POD',
                                'status_code' => 'VAD',
                                'date' => '2026-08-10T06:53:00',
                                'actual' => true,
                                'vessel' => 'DEMO EXPRESS',
                                'voyage' => '001E',
                            ],
                            [
                                'location' => 0,
                                'status' => 'Vessel departure from first POL',
                                'status_code' => 'VDL',
                                'date' => '2026-07-21T01:29:00',
                                'actual' => true,
                                'vessel' => 'DEMO EXPRESS',
                                'voyage' => '001E',
                            ],
                            [
                                'location' => 0,
                                'status' => 'Loaded at First POL',
                                'status_code' => 'CLL',
                                'date' => '2026-07-20T22:26:00',
                                'actual' => true,
                                'vessel' => null,
                                'voyage' => null,
                            ],
                        ],
                    ],
                ],
                'shipment_status' => $shipmentStatus,
                'tracking_metadata' => [
                    'id' => 'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
                    'status' => 'ACTIVE',
                    'updated_at' => '2026-08-10T07:00:00.000Z',
                ],
            ],
        ];
    }

    public function test_maps_ok_payload_to_normalized_dto(): void
    {
        Http::fake([
            self::BASE.'/tracking*' => Http::response($this->okPayload()),
        ]);

        $dto = $this->aggregator()->track('CSQU3054383');

        $this->assertSame(TrackingDataDTO::STATE_OK, $dto->state);
        $this->assertSame('IN_TRANSIT', $dto->status);
        $this->assertSame('timetocargo', $dto->provider);
        $this->assertSame('China Ocean Shipping Group Company', $dto->carrierName);
        $this->assertSame('cosco', $dto->carrierCode);
        $this->assertSame('Qingdao', $dto->origin['port']);
        $this->assertSame('China', $dto->origin['country']);
        $this->assertSame('2026-07-21T01:29:00', $dto->origin['departure']);
        $this->assertSame('Long Beach', $dto->destination['port']);
        $this->assertSame('United States of America', $dto->destination['country']);
        $this->assertSame('2026-08-10T06:53:00', $dto->destination['eta']);
        $this->assertSame('DEMO EXPRESS', $dto->vessel['name']);
        $this->assertSame('001E', $dto->vessel['voyage']);
        $this->assertCount(3, $dto->events);
        $this->assertSame('Loaded at First POL', $dto->events[0]['description']);
        $this->assertSame('CLL', $dto->events[0]['status_code']);
        $this->assertSame('Qingdao', $dto->events[0]['location']);
        $this->assertSame('2026-08-10T07:00:00.000Z', $dto->lastUpdated);
    }

    public function test_sends_api_key_and_container_type_for_iso_number(): void
    {
        Http::fake([
            self::BASE.'/tracking*' => Http::response($this->okPayload()),
        ]);

        $this->aggregator()->track('CSQU3054383');

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), 'api_key=test-key')
                && str_contains($request->url(), 'value=CSQU3054383')
                && str_contains($request->url(), 'type=container')
                && str_contains($request->url(), 'company=AUTO');
        });
    }

    public function test_omits_type_for_booking_reference(): void
    {
        Http::fake([
            self::BASE.'/tracking*' => Http::response($this->okPayload()),
        ]);

        $this->aggregator()->track('BOOKING123');

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), 'value=BOOKING123')
                && ! str_contains($request->url(), 'type=');
        });
    }

    public function test_info_not_found_throws_container_not_found(): void
    {
        Http::fake([
            self::BASE.'/tracking*' => Http::response([
                'success' => true,
                'status' => 'INFO_NOT_FOUND',
                'status_description' => 'No tracking information',
                'data' => null,
            ]),
        ]);

        $this->expectException(ContainerNotFoundException::class);
        $this->aggregator()->track('CSQU3054383');
    }

    public function test_unauthorized_key_is_not_configured_state(): void
    {
        Http::fake([
            self::BASE.'/tracking*' => Http::response(['status' => 'UNAUTHORIZED'], 401),
        ]);

        try {
            $this->aggregator()->track('CSQU3054383');
            $this->fail('expected CarrierApiException');
        } catch (CarrierApiException $exception) {
            $this->assertSame(TrackingDataDTO::STATE_NOT_CONFIGURED, $exception->state);
        }
    }

    public function test_rate_limit_surfaces_as_error_state(): void
    {
        Http::fake([
            self::BASE.'/tracking*' => Http::response(['status' => 'RATE_LIMITED'], 429),
        ]);

        try {
            $this->aggregator()->track('CSQU3054383');
            $this->fail('expected CarrierApiException');
        } catch (CarrierApiException $exception) {
            $this->assertSame(TrackingDataDTO::STATE_ERROR, $exception->state);
        }
    }

    public function test_missing_credentials_are_not_configured(): void
    {
        $aggregator = TimeToCargoAggregator::make(['url' => '', 'api_key' => ''], 'Maersk', 'maersk');

        try {
            $aggregator->track('CSQU3054383');
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
        yield 'delivered' => ['DELIVERED', 'DELIVERED'];
        yield 'completed maps to delivered' => ['COMPLETED', 'DELIVERED'];
        yield 'cancelled maps to unknown' => ['CANCELLED', 'UNKNOWN'];
        yield 'unknown status' => ['SOMETHING_ELSE', 'UNKNOWN'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('statusProvider')]
    public function test_shipment_status_maps_to_portal_labels(string $apiStatus, string $expected): void
    {
        Http::fake([
            self::BASE.'/tracking*' => Http::response($this->okPayload($apiStatus)),
        ]);

        $dto = $this->aggregator()->track('CSQU3054383');

        $this->assertSame($expected, $dto->status);
    }
}
