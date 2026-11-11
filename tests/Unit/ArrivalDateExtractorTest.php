<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ContainerTracking\Tracktainer\ArrivalDateExtractor;
use PHPUnit\Framework\TestCase;

class ArrivalDateExtractorTest extends TestCase
{
    private ArrivalDateExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = new ArrivalDateExtractor;
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(array $overrides = []): array
    {
        return array_merge([
            'shipment_status' => 'IN_TRANSIT',
            'shipment_number' => 'MAEU1234567',
            'eta' => '2026-12-01',
            'containers' => [],
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    private function containerWithMovements(array $movements): array
    {
        return [
            ['container_number' => 'MAEU1234567', 'movements' => $movements],
        ];
    }

    public function test_returns_actual_arrival_from_latest_act_movement(): void
    {
        $attributes = $this->attributes([
            'shipment_status' => 'COMPLETED',
            'eta' => '2026-12-01',
            'containers' => $this->containerWithMovements([
                ['event' => 'DEPA', 'classifier' => 'ACT', 'date' => '2026-08-28'],
                ['event' => 'ARRI', 'classifier' => 'ACT', 'date' => '2026-09-23'],
                ['event' => 'DISCH', 'classifier' => 'ACT', 'date' => '2026-09-25'],
            ]),
        ]);

        $this->assertSame('2026-09-25', $this->extractor->extract($attributes));
    }

    public function test_prefers_actual_movement_over_eta_attribute(): void
    {
        $attributes = $this->attributes([
            'shipment_status' => 'ARRIVED',
            'eta' => '2026-11-30',
            'containers' => $this->containerWithMovements([
                ['event' => 'ARRI', 'classifier' => 'ACT', 'date' => '2026-11-12'],
            ]),
        ]);

        $this->assertSame('2026-11-12', $this->extractor->extract($attributes));
    }

    public function test_ignores_estimated_movements_when_no_act_arrival(): void
    {
        $attributes = $this->attributes([
            'shipment_status' => 'IN_TRANSIT',
            'containers' => $this->containerWithMovements([
                ['event' => 'ARRI', 'classifier' => 'EST', 'date' => '2026-11-30'],
            ]),
        ]);

        // IN_TRANSIT + only EST forecast → no trustworthy arrival yet.
        $this->assertNull($this->extractor->extract($attributes));
    }

    public function test_falls_back_to_eta_when_arrived_without_movements(): void
    {
        $attributes = $this->attributes([
            'shipment_status' => 'ARRIVED',
            'eta' => '2026-11-08',
            'containers' => [],
        ]);

        $this->assertSame('2026-11-08', $this->extractor->extract($attributes));
    }

    public function test_completed_without_movements_uses_eta(): void
    {
        $attributes = $this->attributes([
            'shipment_status' => 'COMPLETED',
            'eta' => '2026-11-10',
        ]);

        $this->assertSame('2026-11-10', $this->extractor->extract($attributes));
    }

    public function test_in_transit_without_movement_returns_null_even_with_eta(): void
    {
        $attributes = $this->attributes([
            'shipment_status' => 'IN_TRANSIT',
            'eta' => '2026-12-15',
        ]);

        $this->assertNull($this->extractor->extract($attributes));
    }

    public function test_pending_and_cancelled_never_yield_an_arrival(): void
    {
        foreach (['PENDING', 'PLANNED', 'CANCELLED'] as $status) {
            $attributes = $this->attributes([
                'shipment_status' => $status,
                'eta' => '2026-11-08',
                'containers' => $this->containerWithMovements([
                    ['event' => 'DISCH', 'classifier' => 'ACT', 'date' => '2026-11-08'],
                ]),
            ]);

            $this->assertNull($this->extractor->extract($attributes), $status);
        }
    }

    public function test_ignores_malformed_dates(): void
    {
        $attributes = $this->attributes([
            'shipment_status' => 'COMPLETED',
            'eta' => 'not-a-date',
            'containers' => $this->containerWithMovements([
                ['event' => 'ARRI', 'classifier' => 'ACT', 'date' => null],
            ]),
        ]);

        $this->assertNull($this->extractor->extract($attributes));
    }
}
