<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contract;
use App\Services\ContainerTracking\Tracktainer\ContractArrivalSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractArrivalSyncTest extends TestCase
{
    use RefreshDatabase;

    private ContractArrivalSyncService $sync;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sync = app(ContractArrivalSyncService::class);
    }

    private function makeContract(?string $etaDate = null): Contract
    {
        $exporter = Client::firstOrCreate(['name' => 'Golden Spices', 'type' => 'exporter'], ['country' => 'Brazil']);
        $importer = Client::firstOrCreate(['name' => 'Expin', 'type' => 'importer'], ['country' => 'India']);

        return Contract::create([
            'date' => '2026-01-10',
            'contract_number' => '4242',
            'exporter_id' => $exporter->id,
            'importer_id' => $importer->id,
            'country' => 'Brazil',
            'status' => 'Transbordo até',
            'container_number' => 'MAEU1234567',
            'eta_date' => $etaDate,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function arrivedAttributes(string $date = '2026-09-25', string $container = 'MAEU1234567'): array
    {
        return [
            'shipment_status' => 'COMPLETED',
            'shipment_number' => $container,
            'eta' => $date,
            'containers' => [
                [
                    'container_number' => $container,
                    'movements' => [
                        ['event' => 'ARRI', 'classifier' => 'ACT', 'date' => $date],
                        ['event' => 'DISCH', 'classifier' => 'ACT', 'date' => $date],
                    ],
                ],
            ],
        ];
    }

    public function test_fills_empty_eta_from_arrival(): void
    {
        $contract = $this->makeContract(null);

        $updated = $this->sync->syncShipment($this->arrivedAttributes());

        $this->assertCount(1, $updated);
        $this->assertSame('2026-09-25', $contract->fresh()->eta_date?->toDateString());
    }

    public function test_overwrites_stale_eta_with_actual_arrival(): void
    {
        $contract = $this->makeContract('2026-12-01');

        $this->sync->syncShipment($this->arrivedAttributes('2026-09-25'));

        $this->assertSame('2026-09-25', $contract->fresh()->eta_date?->toDateString());
    }

    public function test_matching_eta_is_a_no_op(): void
    {
        $contract = $this->makeContract('2026-09-25');

        $updated = $this->sync->syncShipment($this->arrivedAttributes('2026-09-25'));

        $this->assertSame([], $updated);
        $this->assertSame('2026-09-25', $contract->fresh()->eta_date?->toDateString());
    }

    public function test_no_arrival_signal_writes_nothing(): void
    {
        $contract = $this->makeContract(null);

        $updated = $this->sync->syncShipment([
            'shipment_status' => 'IN_TRANSIT',
            'shipment_number' => 'MAEU1234567',
            'eta' => '2026-12-15',
            'containers' => [],
        ]);

        $this->assertSame([], $updated);
        $this->assertNull($contract->fresh()->eta_date);
    }

    public function test_unknown_container_is_ignored(): void
    {
        $contract = $this->makeContract(null);

        $updated = $this->sync->syncShipment($this->arrivedAttributes(container: 'MSKU9999999'));

        $this->assertSame([], $updated);
        $this->assertNull($contract->fresh()->eta_date);
    }

    public function test_case_insensitive_container_match(): void
    {
        $contract = $this->makeContract(null);
        $contract->forceFill(['container_number' => 'maeu1234567'])->saveQuietly();

        $updated = $this->sync->syncShipment($this->arrivedAttributes(container: 'MAEU1234567'));

        $this->assertCount(1, $updated);
        $this->assertSame('2026-09-25', $contract->fresh()->eta_date?->toDateString());
    }

    public function test_booking_shipment_matches_contract_by_container_number(): void
    {
        // Contract stores the ISO container; Tracktainer shipment is tracked by booking.
        $contract = $this->makeContract(null);
        $contract->forceFill(['container_number' => 'ONEU9410240'])->saveQuietly();

        $updated = $this->sync->syncShipment([
            'shipment_status' => 'COMPLETED',
            'shipment_number' => 'TAOGQ5798900',
            'eta' => '2026-11-10',
            'containers' => [
                [
                    'container_number' => 'ONEU9410240',
                    'movements' => [
                        ['event' => 'DISCH', 'classifier' => 'ACT', 'date' => '2026-11-10'],
                    ],
                ],
            ],
        ]);

        $this->assertCount(1, $updated);
        $this->assertSame('2026-11-10', $contract->fresh()->eta_date?->toDateString());
    }

    public function test_multi_container_shipment_uses_each_containers_arrival(): void
    {
        $contractA = $this->makeContract(null);
        $contractA->forceFill(['container_number' => 'AAAA1111111'])->saveQuietly();
        $contractB = $this->makeContract(null);
        $contractB->forceFill(['container_number' => 'BBBB2222222'])->saveQuietly();

        $updated = $this->sync->syncShipment([
            'shipment_status' => 'COMPLETED',
            'shipment_number' => 'BOOK12345678',
            'eta' => '2026-12-01',
            'containers' => [
                [
                    'container_number' => 'AAAA1111111',
                    'movements' => [
                        ['event' => 'DISCH', 'classifier' => 'ACT', 'date' => '2026-11-01'],
                    ],
                ],
                [
                    'container_number' => 'BBBB2222222',
                    'movements' => [
                        ['event' => 'ARRI', 'classifier' => 'ACT', 'date' => '2026-11-15'],
                    ],
                ],
            ],
        ]);

        $this->assertSame('2026-11-01', $contractA->fresh()->eta_date?->toDateString());
        $this->assertSame('2026-11-15', $contractB->fresh()->eta_date?->toDateString());
    }

    public function test_dry_run_reports_change_without_writing(): void
    {
        $contract = $this->makeContract(null);

        $updated = $this->sync->syncShipment($this->arrivedAttributes(), persist: false);

        $this->assertCount(1, $updated);
        $this->assertNull($contract->fresh()->eta_date);
    }
}
