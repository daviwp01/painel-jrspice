<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contract;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncContractArrivalsCommandTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = 'https://api.tracktainer.com/v1';

    private function configureTracktainer(): void
    {
        config()->set('tracking.aggregator', [
            'driver' => 'tracktainer',
            'url' => self::BASE,
            'api_key' => 'test-key',
            'timeout' => 5,
        ]);
    }

    private function makeContract(?string $container = 'MAEU1234567'): Contract
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
    private function listWithOneShipment(): array
    {
        return [
            'data' => [
                [
                    'type' => 'ocean-shipments',
                    'id' => 's-1',
                    'attributes' => [
                        'shipment_status' => 'COMPLETED',
                        'shipment_number' => 'MAEU1234567',
                        'eta' => '2026-09-25',
                        'containers' => [
                            [
                                'container_number' => 'MAEU1234567',
                                'movements' => [
                                    ['event' => 'DISCH', 'classifier' => 'ACT', 'date' => '2026-09-25'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'meta' => ['page' => ['size' => 100]],
            'links' => ['next' => null],
        ];
    }

    public function test_command_updates_contract_eta_and_advances_watermark(): void
    {
        $this->configureTracktainer();
        Http::fake([
            self::BASE.'/ocean/shipments?*' => Http::response($this->listWithOneShipment()),
        ]);

        $contract = $this->makeContract();

        $this->artisan('tracking:sync-arrivals')
            ->assertExitCode(0);

        $this->assertSame('2026-09-25', $contract->fresh()->eta_date?->toDateString());
        $this->assertNotNull(Setting::get('tracktainer_sync_after'));
    }

    public function test_dry_run_reports_without_writing_eta_or_watermark(): void
    {
        $this->configureTracktainer();
        Setting::set('tracktainer_sync_after', '2026-11-01T00:00:00Z');
        Http::fake([
            self::BASE.'/ocean/shipments?*' => Http::response($this->listWithOneShipment()),
        ]);

        $contract = $this->makeContract();

        $this->artisan('tracking:sync-arrivals --dry-run')
            ->assertExitCode(0);

        $this->assertNull($contract->fresh()->eta_date);
        $this->assertSame('2026-11-01T00:00:00Z', Setting::get('tracktainer_sync_after'));
    }

    public function test_unconfigured_client_skips_cleanly(): void
    {
        config()->set('tracking.aggregator', ['url' => '', 'api_key' => '']);

        $this->artisan('tracking:sync-arrivals')
            ->assertExitCode(0);

        $this->assertNull(Setting::get('tracktainer_sync_after'));
    }

    public function test_api_failure_keeps_watermark_for_retry(): void
    {
        $this->configureTracktainer();
        Setting::set('tracktainer_sync_after', '2026-11-01T00:00:00Z');
        Http::fake([
            self::BASE.'/ocean/shipments?*' => Http::response(['title' => 'Unauthenticated'], 401),
        ]);

        $this->artisan('tracking:sync-arrivals')
            ->assertExitCode(1);

        $this->assertSame('2026-11-01T00:00:00Z', Setting::get('tracktainer_sync_after'));
    }
}
