<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ContainerTracking\TimeToCargo\TimeToCargoArrivalExtractor;
use PHPUnit\Framework\TestCase;

class TimeToCargoArrivalExtractorTest extends TestCase
{
    private TimeToCargoArrivalExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = new TimeToCargoArrivalExtractor;
    }

    public function test_returns_actual_vad_arrival(): void
    {
        $result = $this->extractor->extract([
            'shipment_status' => 'IN_TRANSIT',
            'containers' => [[
                'number' => 'MSDU8368827',
                'events' => [
                    ['status_code' => 'VAD', 'date' => '2026-12-13T00:00:00', 'actual' => false],
                    ['status_code' => 'CDD', 'date' => '2026-11-20T08:00:00', 'actual' => true],
                ],
            ]],
            'summary' => ['pod' => ['date' => '2026-12-13T00:00:00']],
        ]);

        $this->assertSame('2026-11-20', $result['arrivals']['MSDU8368827']);
        $this->assertContains('MSDU8368827', $result['references']);
    }

    public function test_ignores_estimated_arrival_events(): void
    {
        $result = $this->extractor->extract([
            'shipment_status' => 'IN_TRANSIT',
            'containers' => [[
                'number' => 'MSDU8368827',
                'events' => [
                    ['status_code' => 'VAD', 'date' => '2026-12-13T00:00:00', 'actual' => false],
                ],
            ]],
            'summary' => ['pod' => ['date' => '2026-12-13T00:00:00']],
        ]);

        $this->assertSame([], $result['arrivals']);
    }

    public function test_falls_back_to_pod_date_when_delivered(): void
    {
        $result = $this->extractor->extract([
            'shipment_status' => 'DELIVERED',
            'containers' => [[
                'number' => 'MSDU8368827',
                'events' => [],
            ]],
            'summary' => ['pod' => ['date' => '2026-11-20T10:00:00']],
        ]);

        $this->assertSame('2026-11-20', $result['arrivals']['MSDU8368827']);
    }

    public function test_in_transit_without_actual_has_no_arrival(): void
    {
        $result = $this->extractor->extract([
            'shipment_status' => 'IN_TRANSIT',
            'containers' => [[
                'number' => 'MSDU8368827',
                'events' => [
                    ['status_code' => 'CLL', 'date' => '2026-10-30T00:00:00', 'actual' => true],
                ],
            ]],
            'summary' => ['pod' => ['date' => '2026-12-13T00:00:00']],
        ]);

        $this->assertSame([], $result['arrivals']);
    }

    public function test_collects_booking_and_bl_references(): void
    {
        $result = $this->extractor->extract([
            'shipment_status' => 'IN_TRANSIT',
            'references' => [
                'booking_numbers' => ['BOOK123'],
                'bill_of_lading_numbers' => ['MEDUABN47753'],
            ],
            'containers' => [['number' => 'MSDU8368827', 'events' => []]],
        ]);

        $this->assertContains('BOOK123', $result['references']);
        $this->assertContains('MEDUABN47753', $result['references']);
        $this->assertContains('MSDU8368827', $result['references']);
    }
}
