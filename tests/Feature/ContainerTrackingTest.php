<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contract;
use App\Models\ContractItem;
use App\Models\ContractProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ContainerTrackingTest extends TestCase
{
    use RefreshDatabase;

    private function fakeMaerskApi(): void
    {
        config()->set('tracking.carriers.maersk.backend', 'api');
        config()->set('tracking.carriers.maersk.api', [
            'url' => 'https://api.maersk.test/track/{container}',
            'method' => 'GET',
            'headers' => ['Authorization' => 'Bearer test-key'],
            'query' => [],
            'body' => [],
            'timeout' => 5,
            'map' => [
                'status' => 'data.status',
                'origin.port' => 'data.origin.name',
                'origin.country' => 'data.origin.country',
                'origin.departure' => 'data.origin.atd',
                'destination.port' => 'data.destination.name',
                'destination.country' => 'data.destination.country',
                'destination.eta' => 'data.destination.eta',
                'vessel.name' => 'data.vessel.name',
                'vessel.voyage' => 'data.vessel.voyage',
                'last_updated' => 'data.updated_at',
                'events' => 'data.events',
                'events.date' => 'occurred_at',
                'events.location' => 'place',
                'events.description' => 'text',
                'events.status_code' => 'code',
            ],
        ]);
    }

    private function makeContract(?string $containerNumber = 'MAEU1234567'): Contract
    {
        $exporter = Client::create(['name' => 'Golden Spices', 'type' => 'exporter', 'country' => 'Brazil']);
        $importer = Client::create(['name' => 'Expin', 'type' => 'importer', 'country' => 'India']);
        $product = ContractProduct::create(['name' => 'Alho Granulado', 'normalized_name' => 'alhogranulado']);

        $contract = Contract::create([
            'date' => '2026-01-10',
            'contract_number' => '4242',
            'exporter_id' => $exporter->id,
            'importer_id' => $importer->id,
            'country' => 'Brazil',
            'status' => 'A embarcar dia',
            'container_number' => $containerNumber,
            'shipping_company' => 'Maersk',
        ]);

        ContractItem::create([
            'contract_id' => $contract->id,
            'position' => 1,
            'product_id' => $product->id,
            'quantity_tons' => 10,
            'price_per_ton_usd' => 2000,
            'sales_usd' => 20000,
        ]);

        return $contract;
    }

    public function test_rejects_container_number_outside_iso_6346(): void
    {
        $master = User::factory()->master()->create();

        $response = $this->actingAs($master)->getJson(route('tracking.show', 'MSC123'));

        $response->assertStatus(422)
            ->assertJsonPath('state', 'error')
            ->assertJsonPath('container_number', 'MSC123');
    }

    public function test_reports_unsupported_prefix_as_a_state_not_as_a_500(): void
    {
        $master = User::factory()->master()->create();

        $response = $this->actingAs($master)->getJson(route('tracking.show', 'ZZZZ1234567'));

        $response->assertOk()
            ->assertJsonPath('state', 'unsupported')
            ->assertJsonPath('status', 'UNKNOWN');
    }

    public function test_reports_missing_container_on_the_contract(): void
    {
        $contract = $this->makeContract(null);
        $master = User::factory()->master()->create();

        $this->actingAs($master)
            ->getJson(route('my-products.tracking', $contract))
            ->assertOk()
            ->assertJsonPath('state', 'unavailable');
    }

    public function test_rejects_users_that_are_not_linked_to_the_contract(): void
    {
        $contract = $this->makeContract();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->getJson(route('my-products.tracking', $contract))
            ->assertForbidden();
    }

    public function test_returns_normalized_dto_from_the_configured_api_backend(): void
    {
        $this->fakeMaerskApi();

        Http::fake([
            'api.maersk.test/*' => Http::response([
                'data' => [
                    'status' => 'in transit',
                    'origin' => ['name' => 'Port of Santos', 'country' => 'Brazil', 'atd' => '2026-03-10 14:00:00'],
                    'destination' => ['name' => 'Port of Rotterdam', 'country' => 'Netherlands', 'eta' => '2026-04-15 08:00:00'],
                    'vessel' => ['name' => 'MSC ISABELLA', 'voyage' => '2026E'],
                    'updated_at' => '2026-03-15T10:00:00Z',
                    'events' => [
                        ['occurred_at' => '2026-03-10 14:00:00', 'place' => 'Santos, SP', 'text' => 'Loaded on vessel', 'code' => 'LOAD'],
                    ],
                ],
            ], 200),
        ]);

        $master = User::factory()->master()->create();

        $response = $this->actingAs($master)->getJson(route('tracking.show', 'MAEU1234567'));

        $response->assertOk()
            ->assertJsonPath('container_number', 'MAEU1234567')
            ->assertJsonPath('carrier_code', 'maersk')
            ->assertJsonPath('carrier_name', 'Maersk')
            ->assertJsonPath('status', 'IN_TRANSIT')
            ->assertJsonPath('state', 'ok')
            ->assertJsonPath('origin.port', 'Port of Santos')
            ->assertJsonPath('destination.eta', '2026-04-15 08:00:00')
            ->assertJsonPath('vessel.name', 'MSC ISABELLA')
            ->assertJsonPath('events.0.status_code', 'LOAD');

        Http::assertSent(fn ($request) => $request->url() === 'https://api.maersk.test/track/MAEU1234567'
            && $request->hasHeader('Authorization', 'Bearer test-key'));
    }

    public function test_caches_successful_lookups_and_skips_the_carrier_on_the_second_call(): void
    {
        $this->fakeMaerskApi();

        Http::fake([
            'api.maersk.test/*' => Http::response(['data' => ['status' => 'delivered']], 200),
        ]);

        $master = User::factory()->master()->create();

        $this->actingAs($master)->getJson(route('tracking.show', 'MAEU1234567'))->assertOk();
        $this->actingAs($master)->getJson(route('tracking.show', 'MAEU1234567'))->assertOk();

        Http::assertSentCount(1);
    }

    public function test_does_not_cache_failures_so_a_fix_takes_effect_immediately(): void
    {
        config()->set('tracking.carriers.maersk.backend', 'api');
        config()->set('tracking.carriers.maersk.api.url', null);

        $master = User::factory()->master()->create();

        $this->actingAs($master)
            ->getJson(route('tracking.show', 'MAEU1234567'))
            ->assertOk()
            ->assertJsonPath('state', 'not_configured');

        $cacheKey = 'tracking.container.maersk.'.md5('MAEU1234567');
        $this->assertNull(Cache::get($cacheKey), 'uma falha não pode ser cacheada');
    }

    public function test_contract_endpoint_uses_the_container_stored_on_the_contract(): void
    {
        $this->fakeMaerskApi();
        Http::fake(['api.maersk.test/*' => Http::response(['data' => ['status' => 'in transit']], 200)]);

        $contract = $this->makeContract('MAEU7654321');
        $master = User::factory()->master()->create();

        $this->actingAs($master)
            ->getJson(route('my-products.tracking', $contract))
            ->assertOk()
            ->assertJsonPath('container_number', 'MAEU7654321')
            ->assertJsonPath('state', 'ok');

        Http::assertSent(fn ($request) => str_contains($request->url(), 'MAEU7654321'));
    }
}
