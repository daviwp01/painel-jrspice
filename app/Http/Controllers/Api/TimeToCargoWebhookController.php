<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\ContainerTracking\TimeToCargo\TimeToCargoArrivalExtractor;
use App\Services\ContainerTracking\Tracktainer\ContractArrivalSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * TimeToCargo webhook receiver.
 *
 * Docs: https://timetocargo.com/docs/tracking/webhooks
 * Signature: HMAC-SHA256 of `timestamp + '.' + rawBody` with the webhook secret,
 * prefixed with `sha256=`. Always answer 2xx within 10s; retries stop after 5 attempts.
 */
class TimeToCargoWebhookController extends Controller
{
    private const MAX_SKEW_SECONDS = 300;

    private const EVENT_ID_TTL_HOURS = 48;

    private const HANDLED_EVENTS = [
        'tracking.delivered',
        'tracking.eta_update',
        'tracking.history_update',
        'tracking.info_found',
    ];

    public function __construct(
        private readonly TimeToCargoArrivalExtractor $extractor,
        private readonly ContractArrivalSyncService $sync,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $secret = $this->secret();

        if ($secret === '') {
            // Unconfigured endpoint: still 2xx so TimeToCargo does not retry forever
            // while the operator has not stored the secret yet.
            Log::warning('timetocargo.webhook.secret_missing');

            return response()->json(['ok' => true, 'reason' => 'secret_not_configured']);
        }

        if (! $this->signatureIsValid($request, $secret)) {
            return response()->json(['ok' => false, 'reason' => 'invalid_signature'], 401);
        }

        $payload = (array) json_decode($request->getContent(), true);

        if ($payload === []) {
            return response()->json(['ok' => false, 'reason' => 'invalid_json'], 400);
        }

        $eventId = (string) ($request->header('x-timetocargo-event-id') ?: ($payload['id'] ?? ''));

        if ($eventId !== '') {
            $cacheKey = 'timetocargo.webhook.'.$eventId;

            if (Cache::get($cacheKey)) {
                return response()->json(['ok' => true, 'duplicate' => true]);
            }

            Cache::put($cacheKey, true, now()->addHours(self::EVENT_ID_TTL_HOURS));
        }

        $type = (string) ($payload['type'] ?? '');

        if ($type !== '' && ! in_array($type, self::HANDLED_EVENTS, true)) {
            return response()->json(['ok' => true, 'ignored' => $type]);
        }

        try {
            $this->process($payload);
        } catch (\Throwable $exception) {
            // Non-2xx would make TimeToCargo retry; log and accept so a poison
            // payload cannot block the queue. Polling is the safety net.
            Log::error('timetocargo.webhook.failed', [
                'event_id' => $eventId,
                'type' => $type,
                'error' => $exception->getMessage(),
            ]);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function process(array $payload): void
    {
        $data = $this->trackingData($payload);

        if ($data === null) {
            return;
        }

        $references = $this->referencesFromPayload($payload, $data);
        $extracted = $this->extractor->extract($data);

        $this->sync->syncFromNormalized(
            array_values(array_unique([...$references, ...$extracted['references']])),
            $extracted['arrivals'],
            persist: true,
        );
    }

    /**
     * Prefer embedded tracking data; otherwise leave null (caller may refetch).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function trackingData(array $payload): ?array
    {
        foreach (['data', 'resource'] as $key) {
            $node = $payload[$key] ?? null;

            if (! is_array($node)) {
                continue;
            }

            // Common shapes: data itself is the tracking object, or nested
            // data.data / resource.data.data (same as GET /v2/tracking).
            if (isset($node['containers']) || isset($node['shipment_status'])) {
                return $node;
            }

            if (isset($node['data']['containers']) || isset($node['data']['shipment_status'])) {
                return (array) $node['data'];
            }

            if (isset($node['data']['data']['containers']) || isset($node['data']['data']['shipment_status'])) {
                return (array) $node['data']['data'];
            }
        }

        $trackNumber = $this->trackNumberFromPayload($payload);

        if ($trackNumber === null) {
            return null;
        }

        return $this->refetchTracking($trackNumber);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private function referencesFromPayload(array $payload, array $data): array
    {
        $refs = [];

        $trackNumber = $this->trackNumberFromPayload($payload);

        if ($trackNumber !== null) {
            $refs[] = $trackNumber;
        }

        foreach ((array) data_get($data, 'references.booking_numbers', []) as $booking) {
            if (is_string($booking) && $booking !== '') {
                $refs[] = $booking;
            }
        }

        foreach ((array) data_get($data, 'references.bill_of_lading_numbers', []) as $bl) {
            if (is_string($bl) && $bl !== '') {
                $refs[] = $bl;
            }
        }

        return array_values(array_unique($refs));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function trackNumberFromPayload(array $payload): ?string
    {
        foreach (['resource', 'data'] as $key) {
            $value = data_get($payload, $key.'.track_number.value')
                ?? data_get($payload, $key.'.track_number')
                ?? data_get($payload, $key.'.tracked_item.track_number');

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function refetchTracking(string $trackNumber): ?array
    {
        $client = \App\Services\ContainerTracking\TimeToCargo\TimeToCargoClient::fromConfig();

        if (! $client->isConfigured()) {
            return null;
        }

        $type = preg_match('/^[A-Z]{4}[0-9]{7}$/', strtoupper($trackNumber)) === 1 ? 'container' : null;
        $body = $client->track($trackNumber, type: $type);

        if (($body['status'] ?? '') !== 'OK') {
            return null;
        }

        $data = $body['data'] ?? null;

        return is_array($data) ? $data : null;
    }

    private function secret(): string
    {
        $fromEnv = (string) config('tracking.timetocargo.webhook_secret', '');

        if ($fromEnv !== '') {
            return $fromEnv;
        }

        return (string) Setting::get('timetocargo_webhook_secret', '');
    }

    private function signatureIsValid(Request $request, string $secret): bool
    {
        $timestamp = (string) $request->header('x-timetocargo-timestamp', '');
        $signature = (string) $request->header('x-timetocargo-signature', '');

        if ($timestamp === '' || $signature === '') {
            return false;
        }

        if (! ctype_digit($timestamp)) {
            return false;
        }

        $age = abs(time() - (int) $timestamp);

        if ($age > self::MAX_SKEW_SECONDS) {
            return false;
        }

        $raw = $request->getContent();
        $expected = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$raw, $secret);

        return hash_equals($expected, $signature);
    }
}
