<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncContractArrivalsTimeToCargoTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = 'https://tracking.timetocargo.test/v2';

    private function configureTimeToCargo(): void
    {
        config()->set('tracking.aggregator', [
            'driver' => 'timetocargo',
            'url' => self::BASE,
            'api_key' => 'test-key',
            'timeout' => 5,
        ]);
    }

    private function makeContract(?string $container = 'MSDU8368827'): Contract
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
            'container_number' => $container,
            'eta_date' => null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function shipmentsList(): array
    {
        return [
            'success' => true,
            'status' => 'OK',
            'data' => [
                'data' => [[
                    'id' => 'ship-1',
                    'tracked_item' => [
                        'track_number' => 'MSDU8368827',
                        'track_number_type' => 'container',
                        'data' => [
                            'success' => true,
                            'status' => 'OK',
                            'data' => [
                                'shipment_status' => 'DELIVERED',
                                'containers' => [[
                                    'number' => 'MSDU8368827',
                                    'events' => [
                                        ['status_code' => 'CDD', 'date' => '2026-11-20T08:00:00', 'actual' => true],
                                    ],
                                ]],
                                'summary' => ['pod' => ['date' => '2026-11-20T08:00:00']],
                                'references' => [
                                    'booking_numbers' => [],
                                    'bill_of_lading_numbers' => [],
                                ],
                            ],
                        ],
                    ],
                ]],
                'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 1],
                'links' => [],
            ],
        ];
    }

    public function test_command_updates_eta_from_timetocargo_shipments(): void
    {
        $this->configureTimeToCargo();
        Http::fake([
            self::BASE.'/shipments*' => Http::response($this->shipmentsList()),
        ]);

        $contract = $this->makeContract();

        $this->artisan('tracking:sync-arrivals')->assertExitCode(0);

        $this->assertSame('2026-11-20', $contract->fresh()->eta_date?->toDateString());
    }

    public function test_dry_run_does_not_write_eta(): void
    {
        $this->configureTimeToCargo();
        Http::fake([
            self::BASE.'/shipments*' => Http::response($this->shipmentsList()),
        ]);

        $contract = $this->makeContract();

        $this->artisan('tracking:sync-arrivals --dry-run')->assertExitCode(0);

        $this->assertNull($contract->fresh()->eta_date);
    }

    public function test_tracktainer_driver_still_uses_tracktainer_path(): void
    {
        config()->set('tracking.aggregator', [
            'driver' => 'tracktainer',
            'url' => 'https://api.tracktainer.test/v1',
            'api_key' => 'k',
            'timeout' => 5,
        ]);

        Http::fake([
            'api.tracktainer.test/*' => Http::response(['data' => [], 'links' => ['next' => null]]),
        ]);

        $this->artisan('tracking:sync-arrivals')->assertExitCode(0);

        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'ocean/shipments'));
    }

    public function test_unconfigured_timetocargo_skips_cleanly(): void
    {
        config()->set('tracking.aggregator', [
            'driver' => 'timetocargo',
            'url' => '',
            'api_key' => '',
        ]);

        $this->artisan('tracking:sync-arrivals')->assertExitCode(0);
    }
}
