<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contract;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TimeToCargoWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-webhook-secret';

    private const BASE = 'https://tracking.timetocargo.test/v2';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('tracking.timetocargo.webhook_secret', self::SECRET);
        config()->set('tracking.aggregator', [
            'driver' => 'timetocargo',
            'url' => self::BASE,
            'api_key' => 'test-key',
            'timeout' => 5,
        ]);
        Cache::flush();
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
     * @param  array<string, mixed>  $payload
     * @return \Illuminate\Testing\TestResponse
     */
    private function postSigned(array $payload, ?string $eventId = 'evt-1')
    {
        $raw = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $timestamp = (string) time();
        $signature = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$raw, self::SECRET);

        $server = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_TIMETOCARGO_TIMESTAMP' => $timestamp,
            'HTTP_X_TIMETOCARGO_SIGNATURE' => $signature,
        ];

        if ($eventId !== null) {
            $server['HTTP_X_TIMETOCARGO_EVENT_ID'] = $eventId;
        }

        return $this->call(
            'POST',
            route('api.webhooks.timetocargo'),
            [],
            [],
            [],
            $server,
            $raw,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function deliveredPayload(): array
    {
        return [
            'id' => 'evt-1',
            'type' => 'tracking.delivered',
            'resource' => ['track_number' => ['value' => 'MSDU8368827', 'type' => 'container']],
            'data' => [
                'shipment_status' => 'DELIVERED',
                'containers' => [[
                    'number' => 'MSDU8368827',
                    'events' => [
                        ['status_code' => 'CDD', 'date' => '2026-11-20T08:00:00', 'actual' => true],
                    ],
                ]],
                'summary' => ['pod' => ['date' => '2026-11-20T08:00:00']],
                'references' => ['booking_numbers' => [], 'bill_of_lading_numbers' => []],
            ],
        ];
    }

    public function test_valid_signature_updates_contract_eta_date(): void
    {
        $contract = $this->makeContract();

        $this->postSigned($this->deliveredPayload())
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertSame('2026-11-20', $contract->fresh()->eta_date?->toDateString());
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $contract = $this->makeContract();
        $raw = json_encode($this->deliveredPayload());

        $this->call(
            'POST',
            route('api.webhooks.timetocargo'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_TIMETOCARGO_TIMESTAMP' => (string) time(),
                'HTTP_X_TIMETOCARGO_SIGNATURE' => 'sha256=deadbeef',
                'HTTP_X_TIMETOCARGO_EVENT_ID' => 'evt-bad',
            ],
            $raw,
        )->assertStatus(401);

        $this->assertNull($contract->fresh()->eta_date);
    }

    public function test_missing_signature_is_rejected(): void
    {
        $this->postJson(route('api.webhooks.timetocargo'), $this->deliveredPayload())
            ->assertStatus(401);
    }

    public function test_duplicate_event_id_is_idempotent(): void
    {
        $contract = $this->makeContract();

        $this->postSigned($this->deliveredPayload(), 'dup-1')->assertOk();
        $this->assertSame('2026-11-20', $contract->fresh()->eta_date?->toDateString());

        // Same event id again: must short-circuit and not rewrite eta_date.
        $contract->forceFill(['eta_date' => '2026-01-01'])->save();
        $contract->refresh();

        $this->postSigned($this->deliveredPayload(), 'dup-1')
            ->assertOk()
            ->assertJsonPath('duplicate', true);

        $this->assertSame('2026-01-01', $contract->fresh()->eta_date?->toDateString());
    }

    public function test_unhandled_event_type_is_ignored(): void
    {
        $contract = $this->makeContract();

        $payload = $this->deliveredPayload();
        $payload['type'] = 'tracking.auto_archiving';

        $this->postSigned($payload, 'evt-archive')
            ->assertOk()
            ->assertJsonPath('ignored', 'tracking.auto_archiving');

        $this->assertNull($contract->fresh()->eta_date);
    }

    public function test_secret_falls_back_to_settings_table(): void
    {
        config()->set('tracking.timetocargo.webhook_secret', '');
        Setting::set('timetocargo_webhook_secret', 'settings-secret');

        $contract = $this->makeContract();
        $payload = $this->deliveredPayload();
        $raw = json_encode($payload);
        $timestamp = (string) time();
        $signature = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$raw, 'settings-secret');

        $this->call(
            'POST',
            route('api.webhooks.timetocargo'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_TIMETOCARGO_TIMESTAMP' => $timestamp,
                'HTTP_X_TIMETOCARGO_SIGNATURE' => $signature,
                'HTTP_X_TIMETOCARGO_EVENT_ID' => 'evt-settings',
            ],
            $raw,
        )->assertOk();

        $this->assertSame('2026-11-20', $contract->fresh()->eta_date?->toDateString());
    }

    public function test_refetches_tracking_when_payload_has_no_data(): void
    {
        Http::fake([
            self::BASE.'/tracking*' => Http::response([
                'success' => true,
                'status' => 'OK',
                'data' => [
                    'shipment_status' => 'DELIVERED',
                    'containers' => [[
                        'number' => 'MSDU8368827',
                        'events' => [
                            ['status_code' => 'CDD', 'date' => '2026-11-21T00:00:00', 'actual' => true],
                        ],
                    ]],
                    'summary' => ['pod' => ['date' => '2026-11-21T00:00:00']],
                ],
            ]),
        ]);

        $contract = $this->makeContract();

        $this->postSigned([
            'id' => 'evt-refetch',
            'type' => 'tracking.history_update',
            'resource' => ['track_number' => ['value' => 'MSDU8368827', 'type' => 'container']],
        ], 'evt-refetch')->assertOk();

        $this->assertSame('2026-11-21', $contract->fresh()->eta_date?->toDateString());
    }
}
